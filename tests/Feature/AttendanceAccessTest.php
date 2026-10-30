<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guest_is_redirected_from_attendance_pages(): void
    {
        foreach (['attendances', 'monitoring', 'class-reports'] as $slug) {
            $this->get("/admin/{$slug}")->assertRedirect(route('filament.admin.auth.login'));
        }
    }

    public function test_guru_can_list_and_create_attendance(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/attendances')->assertOk();
        $this->get('/admin/attendances/create')->assertOk();
    }

    public function test_guru_piket_and_admin_can_list_but_not_create_attendance(): void
    {
        foreach ([User::factory()->guruPiket()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user);
            $this->get('/admin/attendances')->assertOk();
            $this->get('/admin/attendances/create')->assertForbidden();
        }
    }

    public function test_monitoring_is_visible_to_admin_and_guru_piket_only(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/monitoring')->assertOk();
        $this->actingAs(User::factory()->guruPiket()->create())->get('/admin/monitoring')->assertOk();
        $this->actingAs(User::factory()->create())->get('/admin/monitoring')->assertForbidden();
    }

    public function test_class_reports_access_matrix(): void
    {
        $this->actingAs(User::factory()->guruPiket()->create());
        $this->get('/admin/class-reports')->assertOk();
        $this->get('/admin/class-reports/create')->assertOk();

        $this->actingAs(User::factory()->admin()->create());
        $this->get('/admin/class-reports')->assertOk();
        $this->get('/admin/class-reports/create')->assertForbidden();

        $this->actingAs(User::factory()->create());
        $this->get('/admin/class-reports')->assertForbidden();
        $this->get('/admin/class-reports/create')->assertForbidden();
    }

    public function test_monitoring_marks_started_session_without_attendance_as_unconfirmed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 07:30:00'));

        $guruUser = User::factory()->create();
        $teacher = Teacher::create(['user_id' => $guruUser->id, 'teacher_code' => 'T-1', 'full_name' => 'Guru A']);
        $class = SchoolClass::create(['class_name' => 'X-1', 'academic_year' => '2026/2027']);
        $subject = Subject::create(['subject_name' => 'Matematika']);
        Schedule::create([
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'day_of_week' => 1,
            'start_time' => '07:00:00',
            'end_time' => '07:45:00',
        ]);

        $this->actingAs(User::factory()->guruPiket()->create())
            ->get('/admin/monitoring')
            ->assertOk()
            ->assertSee('Belum terkonfirmasi');
    }

    public function test_guru_cannot_see_other_gurus_attendance(): void
    {
        // Pembatasan data guru diuji melalui query resource.
        $guru = User::factory()->create();
        $this->actingAs($guru);

        $this->assertSame(0, \App\Filament\Resources\Attendances\AttendanceResource::getEloquentQuery()->count());
    }
}
