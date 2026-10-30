<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Schedule;
use App\Models\SecurityAlert;
use App\Models\User;

/**
 * Deteksi anomali berbasis aturan AN-01 sampai AN-07 (FR-14).
 * Dipanggil setiap kali sebuah entri audit log ditulis. Peringatan adalah petunjuk
 * untuk ditinjau petugas, bukan bukti pelanggaran.
 */
class AnomalyDetector
{
    public static function evaluate(AuditLog $log): void
    {
        match ($log->event_type) {
            'login_failed' => self::an01($log),
            'attendance_rejected' => self::attendanceRejected($log),
            'access_denied' => self::an04Access($log),
            'attendance_status_changed' => self::an05($log),
            default => null,
        };

        if (str_starts_with($log->event_type, 'schedule_')) {
            self::an06($log);
        }

        if (self::isDataChange($log->event_type)) {
            self::an07($log);
        }
    }

    // AN-01: login gagal berulang untuk identitas yang sama.
    private static function an01(AuditLog $log): void
    {
        $identifier = $log->after_data['identifier'] ?? null;

        if (blank($identifier)) {
            return;
        }

        $limit = (int) config('anomaly.an01.attempts', 5);
        $minutes = (int) config('anomaly.an01.minutes', 5);

        $count = AuditLog::query()
            ->where('event_type', 'login_failed')
            ->where('occurred_at', '>=', $log->occurred_at->copy()->subMinutes($minutes))
            ->get()
            ->filter(fn (AuditLog $entry) => ($entry->after_data['identifier'] ?? null) === $identifier)
            ->count();

        if ($count >= $limit) {
            self::raise(
                'AN-01',
                'sedang',
                $log,
                "Login gagal berulang untuk identitas {$identifier} (batas {$limit} kali dalam {$minutes} menit terlampaui).",
                User::query()->where('email', $identifier)->value('id'),
            );
        }
    }

    // AN-02, AN-03, dan sebagian AN-04: penolakan presensi.
    private static function attendanceRejected(AuditLog $log): void
    {
        $code = $log->after_data['code'] ?? null;
        $name = self::actorName($log);
        $schedule = $log->target_id;

        if (in_array($code, [AttendanceRules::REJECT_NOT_OPEN, AttendanceRules::REJECT_CLOSED, AttendanceRules::REJECT_WRONG_DAY], true)) {
            self::raise('AN-02', 'rendah', $log, "Presensi dicoba di luar rentang waktu atau hari yang diizinkan oleh {$name} (jadwal #{$schedule}).", $log->actor_user_id);
        } elseif ($code === AttendanceRules::REJECT_DUPLICATE) {
            self::raise('AN-03', 'rendah', $log, "Percobaan presensi ganda oleh {$name} untuk jadwal #{$schedule}.", $log->actor_user_id);
        } elseif (in_array($code, [AttendanceRules::REJECT_NOT_OWNER, AttendanceRules::REJECT_ROLE], true)) {
            self::raise('AN-04', 'sedang', $log, "{$name} mencoba mencatat presensi yang bukan haknya (jadwal #{$schedule}).", $log->actor_user_id);
        }
    }

    // AN-04: akses ditolak (403).
    private static function an04Access(AuditLog $log): void
    {
        $name = self::actorName($log);
        $path = $log->after_data['path'] ?? '-';

        self::raise('AN-04', 'sedang', $log, "Akses ditolak untuk {$name} ke /{$path}.", $log->actor_user_id);
    }

    // AN-05: status presensi diubah setelah dicatat.
    private static function an05(AuditLog $log): void
    {
        $name = self::actorName($log);
        $before = $log->before_data['status'] ?? '?';
        $after = $log->after_data['status'] ?? '?';

        self::raise(
            'AN-05',
            'rendah',
            $log,
            "Status presensi #{$log->target_id} diubah dari {$before} menjadi {$after} oleh {$name}. Alasan: {$log->reason}",
            $log->actor_user_id,
        );
    }

    // AN-06: jadwal berubah untuk hari ini oleh pengguna yang sedang login.
    private static function an06(AuditLog $log): void
    {
        if ($log->actor_user_id === null) {
            return;
        }

        $day = $log->after_data['day_of_week']
            ?? $log->before_data['day_of_week']
            ?? Schedule::query()->whereKey($log->target_id)->value('day_of_week');

        if ((int) $day !== $log->occurred_at->dayOfWeekIso) {
            return;
        }

        $action = match (true) {
            str_ends_with($log->event_type, '_created') => 'dibuat',
            str_ends_with($log->event_type, '_deleted') => 'dihapus',
            default => 'diubah',
        };

        self::raise('AN-06', 'sedang', $log, "Jadwal #{$log->target_id} untuk hari ini {$action} oleh ".self::actorName($log).'.', $log->actor_user_id);
    }

    // AN-07: terlalu banyak perubahan data oleh satu administrator dalam waktu singkat.
    private static function an07(AuditLog $log): void
    {
        if ($log->actor_user_id === null) {
            return;
        }

        $actor = User::query()->find($log->actor_user_id);

        if (! $actor || ! $actor->hasRole(User::ROLE_ADMIN)) {
            return;
        }

        $limit = (int) config('anomaly.an07.changes', 10);
        $minutes = (int) config('anomaly.an07.minutes', 10);

        $count = AuditLog::query()
            ->where('actor_user_id', $actor->getKey())
            ->where('occurred_at', '>=', $log->occurred_at->copy()->subMinutes($minutes))
            ->get()
            ->filter(fn (AuditLog $entry) => self::isDataChange($entry->event_type))
            ->count();

        if ($count > $limit) {
            self::raise('AN-07', 'sedang', $log, "{$actor->name} melakukan lebih dari {$limit} perubahan data dalam {$minutes} menit.", $actor->getKey());
        }
    }

    private static function isDataChange(string $event): bool
    {
        return ! str_starts_with($event, 'security_alert')
            && preg_match('/_(created|updated|deleted)$/', $event) === 1;
    }

    private static function actorName(AuditLog $log): string
    {
        return $log->actor_user_id
            ? (User::query()->whereKey($log->actor_user_id)->value('name') ?? "pengguna #{$log->actor_user_id}")
            : 'pengguna tidak dikenal';
    }

    /**
     * Membuat peringatan, kecuali peringatan yang sama sudah ada dalam rentang dedup_minutes.
     */
    private static function raise(string $rule, string $severity, AuditLog $log, string $description, int|string|null $relatedUserId = null): ?SecurityAlert
    {
        $window = (int) config('anomaly.dedup_minutes', 10);

        $exists = SecurityAlert::query()
            ->where('rule_code', $rule)
            ->where('description', $description)
            ->where('detected_at', '>=', now()->subMinutes($window))
            ->exists();

        if ($exists) {
            return null;
        }

        return SecurityAlert::create([
            'rule_code' => $rule,
            'severity' => $severity,
            'related_user_id' => $relatedUserId ? (int) $relatedUserId : null,
            'related_audit_log_id' => $log->getKey(),
            'description' => $description,
            'detected_at' => now(),
            'status' => 'baru',
        ]);
    }
}