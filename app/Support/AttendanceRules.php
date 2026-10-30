<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\ClassReport;
use App\Models\Schedule;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;


class AttendanceRules
{
    public const REJECT_ROLE = 'role';
    public const REJECT_NOT_OWNER = 'not_owner';
    public const REJECT_WRONG_DAY = 'wrong_day';
    public const REJECT_NOT_OPEN = 'not_open';
    public const REJECT_CLOSED = 'closed';
    public const REJECT_DUPLICATE = 'duplicate';

    public static function startAt(Schedule $schedule, Carbon $now): Carbon
    {
        return $now->copy()->startOfDay()->setTimeFromTimeString($schedule->start_time);
    }

    public static function endAt(Schedule $schedule, Carbon $now): Carbon
    {
        return $now->copy()->startOfDay()->setTimeFromTimeString($schedule->end_time);
    }

    public static function label(Schedule $schedule): string
    {
        return substr($schedule->start_time, 0, 5) . '-' . substr($schedule->end_time, 0, 5)
            . ' | ' . ($schedule->schoolClass?->class_name ?? '-')
            . ' | ' . ($schedule->subject?->subject_name ?? '-');
    }

    /**
     * Mengembalikan ['code' => ..., 'message' => ...] bila guru tidak boleh mencatat presensi, atau null bila boleh.
     */
    public static function check(User $user, Schedule $schedule, ?Carbon $now = null): ?array
    {
        $now ??= now();

        if (! $user->hasRole(User::ROLE_GURU)) {
            return self::problem(self::REJECT_ROLE, 'Hanya guru yang dapat mencatat presensi.');
        }

        if ($schedule->teacher?->user_id !== $user->getKey()) {
            return self::problem(self::REJECT_NOT_OWNER, 'Jadwal ini bukan milik Anda.');
        }

        if ((int) $schedule->day_of_week !== $now->dayOfWeekIso) {
            return self::problem(self::REJECT_WRONG_DAY, 'Jadwal ini bukan untuk hari ini.');
        }

        $opensAt = self::startAt($schedule, $now)->subMinutes((int) config('presensi.open_before_minutes', 10));

        if ($now->lt($opensAt)) {
            return self::problem(self::REJECT_NOT_OPEN, 'Presensi untuk jam ini belum dibuka.');
        }

        if ($now->gt(self::endAt($schedule, $now))) {
            return self::problem(self::REJECT_CLOSED, 'Waktu presensi untuk jam ini sudah lewat.');
        }

        $alreadyRecorded = Attendance::query()
            ->where('schedule_id', $schedule->getKey())
            ->whereDate('attendance_date', $now->toDateString())
            ->exists();

        if ($alreadyRecorded) {
            return self::problem(self::REJECT_DUPLICATE, 'Presensi untuk jam ini sudah dicatat.');
        }

        return null;
    }

    /**
     * Mengembalikan pesan galat atau null (kompatibel dengan pemakaian sebelumnya).
     */
    public static function validate(User $user, Schedule $schedule, ?Carbon $now = null): ?string
    {
        return self::check($user, $schedule, $now)['message'] ?? null;
    }

    /**
     * Sama seperti validate(), tetapi mencatat penolakan ke audit log (bahan AN-02, AN-03, AN-04).
     */
    public static function validateAndLog(User $user, Schedule $schedule, ?Carbon $now = null): ?string
    {
        $problem = self::check($user, $schedule, $now);

        if ($problem === null) {
            return null;
        }

        try {
            AuditLogger::record(
                'attendance_rejected',
                'ditolak',
                $user,
                'schedule',
                (int) $schedule->getKey(),
                null,
                ['code' => $problem['code']],
                $problem['message'],
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return $problem['message'];
    }

    private static function problem(string $code, string $message): array
    {
        return ['code' => $code, 'message' => $message];
    }

    /**
     * Status akhir: izin tetap izin; hadir menjadi terlambat bila melewati batas toleransi.
     */
    public static function resolveStatus(Schedule $schedule, string $chosenStatus, ?Carbon $now = null): string
    {
        $now ??= now();

        if ($chosenStatus === Attendance::STATUS_IZIN) {
            return Attendance::STATUS_IZIN;
        }

        $lateAt = self::startAt($schedule, $now)->addMinutes((int) config('presensi.late_after_minutes', 10));

        return $now->gt($lateAt) ? Attendance::STATUS_TERLAMBAT : Attendance::STATUS_HADIR;
    }

    /**
     * Mencatat presensi setelah memeriksa ulang seluruh aturan di sisi server.
     *
     * @throws ValidationException
     */
    public static function record(User $user, Schedule $schedule, string $chosenStatus, ?string $notes, ?Carbon $now = null): Attendance
    {
        $now ??= now();

        $schedule->loadMissing('teacher');

        if ($error = self::validateAndLog($user, $schedule, $now)) {
            throw ValidationException::withMessages(['data.schedule_id' => $error]);
        }

        if ($chosenStatus === Attendance::STATUS_IZIN && blank($notes)) {
            throw ValidationException::withMessages(['data.notes' => 'Catatan wajib diisi untuk status izin.']);
        }

        return Attendance::create([
            'schedule_id' => $schedule->getKey(),
            'teacher_id' => $schedule->teacher_id,
            'attendance_date' => $now->toDateString(),
            'status' => self::resolveStatus($schedule, $chosenStatus, $now),
            'recorded_by' => $user->getKey(),
            'recorded_at' => $now,
            'notes' => $notes,
        ]);
    }

    /**
     * Pilihan jadwal hari ini milik guru yang belum dicatat presensinya.
     *
     * @return array<int, string>
     */
    public static function todayScheduleOptionsFor(User $user, ?Carbon $now = null): array
    {
        $now ??= now();

        return Schedule::query()
            ->with(['schoolClass', 'subject'])
            ->whereHas('teacher', fn($teacher) => $teacher->where('user_id', $user->getKey()))
            ->where('day_of_week', $now->dayOfWeekIso)
            ->whereDoesntHave('attendances', fn($a) => $a->whereDate('attendance_date', $now->toDateString()))
            ->orderBy('start_time')
            ->get()
            ->mapWithKeys(fn(Schedule $schedule) => [$schedule->getKey() => self::label($schedule)])
            ->all();
    }

    /**
     * Pilihan sesi hari ini yang sudah dimulai tetapi belum terkonfirmasi (belum ada presensi).
     * $include menambahkan satu jadwal tertentu (dipakai saat mengubah laporan yang sudah ada).
     *
     * @return array<int, string>
     */
    public static function unconfirmedScheduleOptions(?Carbon $now = null, ?int $include = null): array
    {
        $now ??= now();

        return Schedule::query()
            ->with(['schoolClass', 'subject'])
            ->where(function ($query) use ($now, $include) {
                $query->where(function ($open) use ($now) {
                    $open->where('day_of_week', $now->dayOfWeekIso)
                        ->where('start_time', '<=', $now->format('H:i:s'))
                        ->whereDoesntHave('attendances', fn($a) => $a->whereDate('attendance_date', $now->toDateString()));
                });

                if ($include) {
                    $query->orWhere('id', $include);
                }
            })
            ->orderBy('start_time')
            ->get()
            ->mapWithKeys(fn(Schedule $schedule) => [$schedule->getKey() => self::label($schedule)])
            ->all();
    }

    /**
     * Mencatat konfirmasi kondisi kelas oleh guru piket (FR-10).
     *
     * @throws ValidationException
     */
    public static function recordClassReport(User $user, int $scheduleId, string $reportStatus, ?string $description, string $followUpStatus = 'belum', ?Carbon $now = null): ClassReport
    {
        $now ??= now();

        if (! $user->hasRole(User::ROLE_GURU_PIKET)) {
            throw ValidationException::withMessages(['data.schedule_id' => 'Hanya guru piket yang dapat mencatat konfirmasi kelas.']);
        }

        if (! array_key_exists($scheduleId, self::unconfirmedScheduleOptions($now))) {
            throw ValidationException::withMessages([
                'data.schedule_id' => 'Sesi ini tidak dapat dikonfirmasi: bukan sesi hari ini, belum dimulai, atau presensinya sudah dicatat.',
            ]);
        }

        return ClassReport::create([
            'schedule_id' => $scheduleId,
            'report_date' => $now->toDateString(),
            'reported_by' => $user->getKey(),
            'report_status' => $reportStatus,
            'description' => $description,
            'follow_up_status' => $followUpStatus,
        ]);
    }

    /**
     * Mengubah status presensi oleh guru piket atau administrator, dengan alasan wajib (FR-11).
     * Nilai sebelum dan sesudah serta alasan dicatat ke audit log (bahan AN-05).
     *
     * @throws ValidationException
     */
    public static function changeStatus(User $user, Attendance $attendance, string $newStatus, ?string $reason): Attendance
    {
        if (! $user->hasRole(User::ROLE_ADMIN, User::ROLE_GURU_PIKET)) {
            throw ValidationException::withMessages(['data.status' => 'Anda tidak berwenang mengubah status presensi.']);
        }

        $allowed = [Attendance::STATUS_HADIR, Attendance::STATUS_TERLAMBAT, Attendance::STATUS_IZIN];

        if (! in_array($newStatus, $allowed, true)) {
            throw ValidationException::withMessages(['data.status' => 'Status tidak valid.']);
        }

        if (blank($reason)) {
            throw ValidationException::withMessages(['data.reason' => 'Alasan perubahan wajib diisi.']);
        }

        if ($attendance->status === $newStatus) {
            throw ValidationException::withMessages(['data.status' => 'Status tidak berubah.']);
        }

        $before = $attendance->status;

        // saveQuietly: perubahan ini dicatat satu kali di bawah (lengkap dengan alasan), bukan dua kali oleh observer.
        $attendance->status = $newStatus;
        $attendance->saveQuietly();

        AuditLogger::record(
            'attendance_status_changed',
            'berhasil',
            $user,
            'attendance',
            (int) $attendance->getKey(),
            ['status' => $before],
            ['status' => $newStatus],
            trim($reason),
        );

        return $attendance;
    }
}
