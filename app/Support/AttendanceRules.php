<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\ClassReport;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AttendanceRules
{
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
        return substr($schedule->start_time, 0, 5).'-'.substr($schedule->end_time, 0, 5)
            .' | '.($schedule->schoolClass?->class_name ?? '-')
            .' | '.($schedule->subject?->subject_name ?? '-');
    }

    /**
     * Mengembalikan pesan galat bila guru tidak boleh mencatat presensi untuk jadwal ini, atau null bila boleh.
     */
    public static function validate(User $user, Schedule $schedule, ?Carbon $now = null): ?string
    {
        $now ??= now();

        if (! $user->hasRole(User::ROLE_GURU)) {
            return 'Hanya guru yang dapat mencatat presensi.';
        }

        if ($schedule->teacher?->user_id !== $user->getKey()) {
            return 'Jadwal ini bukan milik Anda.';
        }

        if ((int) $schedule->day_of_week !== $now->dayOfWeekIso) {
            return 'Jadwal ini bukan untuk hari ini.';
        }

        $opensAt = self::startAt($schedule, $now)->subMinutes((int) config('presensi.open_before_minutes', 10));

        if ($now->lt($opensAt)) {
            return 'Presensi untuk jam ini belum dibuka.';
        }

        if ($now->gt(self::endAt($schedule, $now))) {
            return 'Waktu presensi untuk jam ini sudah lewat.';
        }

        $alreadyRecorded = Attendance::query()
            ->where('schedule_id', $schedule->getKey())
            ->whereDate('attendance_date', $now->toDateString())
            ->exists();

        if ($alreadyRecorded) {
            return 'Presensi untuk jam ini sudah dicatat.';
        }

        return null;
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

        if ($error = self::validate($user, $schedule, $now)) {
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
            ->whereHas('teacher', fn ($teacher) => $teacher->where('user_id', $user->getKey()))
            ->where('day_of_week', $now->dayOfWeekIso)
            ->whereDoesntHave('attendances', fn ($a) => $a->whereDate('attendance_date', $now->toDateString()))
            ->orderBy('start_time')
            ->get()
            ->mapWithKeys(fn (Schedule $schedule) => [$schedule->getKey() => self::label($schedule)])
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
                        ->whereDoesntHave('attendances', fn ($a) => $a->whereDate('attendance_date', $now->toDateString()));
                });

                if ($include) {
                    $query->orWhere('id', $include);
                }
            })
            ->orderBy('start_time')
            ->get()
            ->mapWithKeys(fn (Schedule $schedule) => [$schedule->getKey() => self::label($schedule)])
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
}
