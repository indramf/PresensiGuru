<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Support\AttendanceRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AttendanceRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $time): void
    {
        Carbon::setTestNow(Carbon::parse("2026-11-02 {$time}"));
    }

    private function makeSchedule(User $guru, int $day = 1, string $start = '07:00:00', string $end = '07:45:00'): Schedule
    {
        $teacher = Teacher::firstOrCreate(
            ['user_id' => $guru->id],
            ['teacher_code' => 'T-'.$guru->id, 'full_name' => $guru->name]
        );
        $class = SchoolClass::firstOrCreate(['class_name' => 'X-1', 'academic_year' => '2026/2027']);
        $subject = Subject::firstOrCreate(['subject_name' => 'Matematika']);

        return Schedule::create([
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    public function test_attendance_opens_ten_minutes_before_start(): void
    {
        $guru = User::factory()->create();
        $schedule = $this->makeSchedule($guru);

        $this->at('06:49:00');
        $this->assertSame('Presensi untuk jam ini belum dibuka.', AttendanceRules::validate($guru, $schedule));

        $this->at('06:50:00');
        $this->assertNull(AttendanceRules::validate($guru, $schedule));
    }

    public function test_attendance_closes_at_end_time(): void
    {
        $guru = User::factory()->create();
        $schedule = $this->makeSchedule($guru);

        $this->at('07:45:00');
        $this->assertNull(AttendanceRules::validate($guru, $schedule));

        $this->at('07:46:00');
        $this->assertSame('Waktu presensi untuk jam ini sudah lewat.', AttendanceRules::validate($guru, $schedule));
    }

    public function test_schedule_of_another_day_is_rejected(): void
    {
        $guru = User::factory()->create();
        $schedule = $this->makeSchedule($guru, day: 2);

        $this->at('07:05:00');
        $this->assertSame('Jadwal ini bukan untuk hari ini.', AttendanceRules::validate($guru, $schedule));
    }

    public function test_schedule_of_another_teacher_is_rejected(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $schedule = $this->makeSchedule($owner);
        $this->makeSchedule($intruder, start: '09:00:00', end: '09:45:00');

        $this->at('07:05:00');
        $this->assertSame('Jadwal ini bukan milik Anda.', AttendanceRules::validate($intruder, $schedule));
    }

    public function test_only_guru_can_record_attendance(): void
    {
        $guru = User::factory()->create();
        $schedule = $this->makeSchedule($guru);

        $this->at('07:05:00');

        foreach ([User::factory()->guruPiket()->create(), User::factory()->admin()->create()] as $other) {
            $this->assertSame('Hanya guru yang dapat mencatat presensi.', AttendanceRules::validate($other, $schedule));
        }
    }

    public function test_duplicate_attendance_is_rejected(): void
    {
        $guru = User::factory()->create();
        $schedule = $this->makeSchedule($guru);

        $this->at('07:05:00');
        AttendanceRules::record($guru, $schedule, Attendance::STATUS_HADIR, null);

        $this->assertSame('Presensi untuk jam ini sudah dicatat.', AttendanceRules::validate($guru, $schedule));
    }

    public function test_status_becomes_terlambat_only_after_tolerance(): void
    {
        $guru = User::factory()->create();
        $schedule = $this->makeSchedule($guru);

        $this->at('07:05:00');
        $this->assertSame(Attendance::STATUS_HADIR, AttendanceRules::resolveStatus($schedule, Attendance::STATUS_HADIR));

        $this->at('07:10:00');
        $this->assertSame(Attendance::STATUS_HADIR, AttendanceRules::resolveStatus($schedule, Attendance::STATUS_HADIR));

        $this->at('07:11:00');
        $this->assertSame(Attendance::STATUS_TERLAMBAT, AttendanceRules::resolveStatus($schedule, Attendance::STATUS_HADIR));
        $this->assertSame(Attendance::STATUS_IZIN, AttendanceRules::resolveStatus($schedule, Attendance::STATUS_IZIN));
    }

    public function test_record_sets_server_side_fields(): void
    {
        $guru = User::factory()->create();
        $schedule = $this->makeSchedule($guru);

        $this->at('07:05:00');
        $attendance = AttendanceRules::record($guru, $schedule, Attendance::STATUS_HADIR, null);

        $this->assertSame($guru->id, $attendance->recorded_by);
        $this->assertSame($schedule->teacher_id, $attendance->teacher_id);
        $this->assertSame('2026-11-02', $attendance->attendance_date->toDateString());
        $this->assertSame(Attendance::STATUS_HADIR, $attendance->status);
    }

    public function test_izin_requires_notes(): void
    {
        $guru = User::factory()->create();
        $schedule = $this->makeSchedule($guru);

        $this->at('07:05:00');
        $this->expectException(ValidationException::class);

        AttendanceRules::record($guru, $schedule, Attendance::STATUS_IZIN, null);
    }

    public function test_izin_with_notes_is_recorded(): void
    {
        $guru = User::factory()->create();
        $schedule = $this->makeSchedule($guru);

        $this->at('07:05:00');
        $attendance = AttendanceRules::record($guru, $schedule, Attendance::STATUS_IZIN, 'Sakit');

        $this->assertSame(Attendance::STATUS_IZIN, $attendance->status);
        $this->assertSame('Sakit', $attendance->notes);
    }

    public function test_today_options_contain_only_own_unrecorded_schedules(): void
    {
        $guru = User::factory()->create();
        $other = User::factory()->create();
        $first = $this->makeSchedule($guru);
        $second = $this->makeSchedule($guru, start: '07:45:00', end: '08:30:00');
        $this->makeSchedule($other, start: '09:00:00', end: '09:45:00');
        $this->makeSchedule($guru, day: 2, start: '10:00:00', end: '10:45:00');

        $this->at('07:05:00');
        $this->assertEqualsCanonicalizing([$first->id, $second->id], array_keys(AttendanceRules::todayScheduleOptionsFor($guru)));

        AttendanceRules::record($guru, $first, Attendance::STATUS_HADIR, null);
        $this->assertSame([$second->id], array_keys(AttendanceRules::todayScheduleOptionsFor($guru)));
    }

    public function test_unconfirmed_options_include_only_started_sessions_without_attendance(): void
    {
        $guru = User::factory()->create();
        $started = $this->makeSchedule($guru);
        $this->makeSchedule($guru, start: '08:00:00', end: '08:45:00');

        $this->at('07:30:00');
        $this->assertSame([$started->id], array_keys(AttendanceRules::unconfirmedScheduleOptions()));

        AttendanceRules::record($guru, $started, Attendance::STATUS_HADIR, null);
        $this->assertSame([], array_keys(AttendanceRules::unconfirmedScheduleOptions()));
    }

    public function test_guru_piket_can_confirm_unconfirmed_session(): void
    {
        $guru = User::factory()->create();
        $piket = User::factory()->guruPiket()->create();
        $schedule = $this->makeSchedule($guru);

        $this->at('07:20:00');
        $report = AttendanceRules::recordClassReport($piket, $schedule->id, 'kelas_kosong', 'Guru belum hadir');

        $this->assertSame($piket->id, $report->reported_by);
        $this->assertSame('2026-11-02', $report->report_date->toDateString());
        $this->assertSame('belum', $report->follow_up_status);
    }

    public function test_class_report_is_rejected_for_wrong_role_or_confirmed_session(): void
    {
        $guru = User::factory()->create();
        $piket = User::factory()->guruPiket()->create();
        $schedule = $this->makeSchedule($guru);

        $this->at('07:20:00');

        try {
            AttendanceRules::recordClassReport($guru, $schedule->id, 'kelas_kosong', null);
            $this->fail('Guru seharusnya tidak dapat mencatat konfirmasi kelas.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        AttendanceRules::record($guru, $schedule, Attendance::STATUS_HADIR, null);

        $this->expectException(ValidationException::class);
        AttendanceRules::recordClassReport($piket, $schedule->id, 'kelas_kosong', null);
    }
}
