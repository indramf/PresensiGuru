<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\SecurityAlert;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Support\AttendanceRules;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Deteksi anomali AN-01 sampai AN-07 dan ubah status presensi (FR-11, FR-14, FR-15, NFR-07).
 * Bagian dari T-05 dan T-12 (Bab V). Waktu uji: Senin, 2 November 2026.
 */
class AnomalyDetectionTest extends TestCase
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

    private function alerts(string $rule)
    {
        return SecurityAlert::query()->where('rule_code', $rule);
    }

    private function makeSchedule(User $guru, int $day = 1, string $start = '07:00:00', string $end = '07:45:00'): Schedule
    {
        $teacher = Teacher::firstOrCreate(
            ['user_id' => $guru->id],
            ['teacher_code' => 'T-' . $guru->id, 'full_name' => $guru->name]
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

    private function failedLogin(string $email): void
    {
        event(new Failed('web', null, ['email' => $email, 'password' => 'salah']));
    }

    // ---------- AN-01 ----------

    public function test_an01_five_failed_logins_for_same_identity_raise_an_alert(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->failedLogin('target@contoh.test');
        }

        $this->assertSame(1, $this->alerts('AN-01')->count());
        $this->assertSame('sedang', $this->alerts('AN-01')->first()->severity);
    }

    public function test_an01_normal_logins_do_not_raise_an_alert(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 4; $i++) {
            $this->failedLogin('target@contoh.test');
        }
        event(new Login('web', $user, false));

        // lima kegagalan tetapi untuk identitas berbeda-beda
        foreach (['a', 'b', 'c', 'd', 'e'] as $name) {
            $this->failedLogin("{$name}@contoh.test");
        }

        $this->assertSame(0, $this->alerts('AN-01')->count());
    }

    // ---------- AN-02 ----------

    public function test_an02_attendance_outside_window_raises_an_alert(): void
    {
        $guru = User::factory()->create();
        $schedule = $this->makeSchedule($guru);

        $this->at('06:30:00');
        $this->assertNotNull(AttendanceRules::validateAndLog($guru, $schedule));

        $this->assertSame(1, $this->alerts('AN-02')->count());
        $this->assertTrue(AuditLog::where('event_type', 'attendance_rejected')->exists());
    }

    public function test_an02_valid_attendance_does_not_raise_an_alert(): void
    {
        $guru = User::factory()->create();
        $schedule = $this->makeSchedule($guru);

        $this->at('07:05:00');
        $this->assertNull(AttendanceRules::validateAndLog($guru, $schedule));
        AttendanceRules::record($guru, $schedule, Attendance::STATUS_HADIR, null);

        $this->assertSame(0, $this->alerts('AN-02')->count());
        $this->assertFalse(AuditLog::where('event_type', 'attendance_rejected')->exists());
    }

    // ---------- AN-03 ----------

    public function test_an03_duplicate_attempt_raises_an_alert(): void
    {
        $guru = User::factory()->create();
        $schedule = $this->makeSchedule($guru);

        $this->at('07:05:00');
        AttendanceRules::record($guru, $schedule, Attendance::STATUS_HADIR, null);

        try {
            AttendanceRules::record($guru, $schedule, Attendance::STATUS_HADIR, null);
            $this->fail('Presensi ganda seharusnya ditolak.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->assertSame(1, $this->alerts('AN-03')->count());
    }

    public function test_an03_first_attempt_does_not_raise_an_alert(): void
    {
        $guru = User::factory()->create();
        $schedule = $this->makeSchedule($guru);

        $this->at('07:05:00');
        AttendanceRules::record($guru, $schedule, Attendance::STATUS_HADIR, null);

        $this->assertSame(0, $this->alerts('AN-03')->count());
    }

    public function test_repeated_identical_attempts_do_not_flood_alerts(): void
    {
        $guru = User::factory()->create();
        $schedule = $this->makeSchedule($guru);

        $this->at('07:05:00');
        AttendanceRules::record($guru, $schedule, Attendance::STATUS_HADIR, null);

        for ($i = 0; $i < 3; $i++) {
            AttendanceRules::validateAndLog($guru, $schedule);
        }

        $this->assertSame(1, $this->alerts('AN-03')->count());
    }

    // ---------- AN-04 ----------

    public function test_an04_forbidden_access_raises_an_alert(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/audit-logs')->assertForbidden();

        $this->assertSame(1, $this->alerts('AN-04')->count());
    }

    public function test_an04_attendance_for_someone_elses_schedule_raises_an_alert(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $schedule = $this->makeSchedule($owner);
        $this->makeSchedule($intruder, start: '09:00:00', end: '09:45:00');

        $this->at('07:05:00');
        AttendanceRules::validateAndLog($intruder, $schedule);

        $this->assertSame(1, $this->alerts('AN-04')->count());
    }

    public function test_an04_allowed_access_does_not_raise_an_alert(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/audit-logs')->assertOk();

        $this->assertSame(0, $this->alerts('AN-04')->count());
    }

    // ---------- AN-05 dan FR-11 ----------

    private function recordedAttendance(User $guru): Attendance
    {
        $schedule = $this->makeSchedule($guru);
        $this->at('07:05:00');

        return AttendanceRules::record($guru, $schedule, Attendance::STATUS_HADIR, null);
    }

    public function test_an05_status_change_with_reason_is_audited_and_raises_an_alert(): void
    {
        $guru = User::factory()->create();
        $piket = User::factory()->guruPiket()->create();
        $attendance = $this->recordedAttendance($guru);

        AttendanceRules::changeStatus($piket, $attendance, Attendance::STATUS_IZIN, 'Guru sakit, surat menyusul');

        $this->assertSame(Attendance::STATUS_IZIN, $attendance->fresh()->status);

        $entry = AuditLog::where('event_type', 'attendance_status_changed')->first();
        $this->assertSame(['status' => 'hadir'], $entry->before_data);
        $this->assertSame(['status' => 'izin'], $entry->after_data);
        $this->assertSame('Guru sakit, surat menyusul', $entry->reason);
        $this->assertSame($piket->id, $entry->actor_user_id);

        $this->assertSame(1, $this->alerts('AN-05')->count());
        // perubahan dicatat satu kali, bukan dua kali oleh observer
        $this->assertFalse(AuditLog::where('event_type', 'attendance_updated')->exists());
    }

    public function test_status_change_requires_reason_authority_and_a_real_change(): void
    {
        $guru = User::factory()->create();
        $piket = User::factory()->guruPiket()->create();
        $attendance = $this->recordedAttendance($guru);

        foreach (
            [
                fn() => AttendanceRules::changeStatus($piket, $attendance, Attendance::STATUS_IZIN, ''),
                fn() => AttendanceRules::changeStatus($guru, $attendance, Attendance::STATUS_IZIN, 'alasan'),
                fn() => AttendanceRules::changeStatus($piket, $attendance, Attendance::STATUS_HADIR, 'alasan'),
                fn() => AttendanceRules::changeStatus($piket, $attendance, 'tidak-valid', 'alasan'),
            ] as $attempt
        ) {
            try {
                $attempt();
                $this->fail('Perubahan status seharusnya ditolak.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame(Attendance::STATUS_HADIR, $attendance->fresh()->status);
        $this->assertSame(0, $this->alerts('AN-05')->count());
    }

    public function test_only_guru_piket_and_admin_can_open_attendance_edit_page(): void
    {
        $guru = User::factory()->create();
        $attendance = $this->recordedAttendance($guru);

        $this->actingAs(User::factory()->guruPiket()->create())->get("/admin/attendances/{$attendance->id}/edit")->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get("/admin/attendances/{$attendance->id}/edit")->assertOk();
        $this->actingAs($guru)->get("/admin/attendances/{$attendance->id}/edit")->assertForbidden();
    }

    // ---------- AN-06 ----------

    public function test_an06_schedule_change_for_today_by_a_user_raises_an_alert(): void
    {
        $this->at('10:00:00'); // Senin
        $guru = User::factory()->create();
        $this->makeSchedule($guru);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);
        $this->makeSchedule($guru, day: 1, start: '08:00:00', end: '08:45:00');

        $this->assertSame(1, $this->alerts('AN-06')->count());
    }

    public function test_an06_other_day_or_no_actor_does_not_raise_an_alert(): void
    {
        $this->at('10:00:00'); // Senin
        $guru = User::factory()->create();
        $admin = User::factory()->admin()->create();

        // tanpa pelaku (seperti seeder), hari ini
        $this->makeSchedule($guru, day: 1);

        // dengan pelaku, tetapi hari Selasa
        $this->actingAs($admin);
        $this->makeSchedule($guru, day: 2, start: '08:00:00', end: '08:45:00');

        $this->assertSame(0, $this->alerts('AN-06')->count());
    }

    // ---------- AN-07 ----------

    public function test_an07_more_than_ten_changes_by_an_admin_raise_an_alert(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        for ($i = 1; $i <= 11; $i++) {
            Subject::create(['subject_name' => "Mapel {$i}"]);
        }

        $this->assertSame(1, $this->alerts('AN-07')->count());
    }

    public function test_an07_ten_changes_or_non_admin_do_not_raise_an_alert(): void
    {
        // Semua pengguna dibuat lebih dulu, tanpa pelaku, agar tidak menambah hitungan perubahan admin.
        $admin = User::factory()->admin()->create();
        $piket = User::factory()->guruPiket()->create();

        $this->actingAs($admin);

        for ($i = 1; $i <= 10; $i++) {
            Subject::create(['subject_name' => "Mapel {$i}"]);
        }

        $this->assertSame(0, $this->alerts('AN-07')->count());

        $this->actingAs($piket);

        for ($i = 11; $i <= 25; $i++) {
            Subject::create(['subject_name' => "Mapel {$i}"]);
        }

        $this->assertSame(0, $this->alerts('AN-07')->count());
    }

    // ---------- Halaman peringatan (FR-15) ----------

    public function test_only_admin_can_open_security_alert_pages(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->failedLogin('target@contoh.test');
        }
        $alert = $this->alerts('AN-01')->first();

        $this->get('/admin/security-alerts')->assertRedirect(route('filament.admin.auth.login'));

        $this->actingAs(User::factory()->admin()->create());
        $this->get('/admin/security-alerts')->assertOk();
        $this->get("/admin/security-alerts/{$alert->id}/edit")->assertOk();
        $this->get('/admin/security-alerts/create')->assertNotFound();

        $this->actingAs(User::factory()->create())->get('/admin/security-alerts')->assertForbidden();
        $this->actingAs(User::factory()->guruPiket()->create())->get('/admin/security-alerts')->assertForbidden();
    }
}
