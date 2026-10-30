<?php

namespace Tests\Feature;

use App\Filament\Resources\Schedules\ScheduleResource;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Akses ke halaman kelola data master per peran (FR-03, FR-04, FR-05). Bagian dari T-02 dan T-07 (Bab V).
 */
class MasterDataResourceTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_ONLY = ['users', 'teachers', 'school-classes', 'subjects'];

    public function test_admin_can_open_every_master_data_page(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        foreach ([...self::ADMIN_ONLY, 'schedules'] as $slug) {
            $this->get("/admin/{$slug}")->assertOk();
            $this->get("/admin/{$slug}/create")->assertOk();
        }
    }

    public function test_admin_can_open_user_edit_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get("/admin/users/{$admin->id}/edit")->assertOk();
    }

    public function test_guest_is_redirected_from_master_data_pages(): void
    {
        foreach ([...self::ADMIN_ONLY, 'schedules'] as $slug) {
            $this->get("/admin/{$slug}")->assertRedirect(route('filament.admin.auth.login'));
        }
    }

    public function test_guru_cannot_open_admin_only_pages(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (self::ADMIN_ONLY as $slug) {
            $this->get("/admin/{$slug}")->assertForbidden();
            $this->get("/admin/{$slug}/create")->assertForbidden();
        }
    }

    public function test_guru_piket_cannot_open_admin_only_pages(): void
    {
        $this->actingAs(User::factory()->guruPiket()->create());

        foreach (self::ADMIN_ONLY as $slug) {
            $this->get("/admin/{$slug}")->assertForbidden();
            $this->get("/admin/{$slug}/create")->assertForbidden();
        }
    }

    public function test_guru_and_guru_piket_can_view_but_not_create_schedules(): void
    {
        foreach ([User::factory()->create(), User::factory()->guruPiket()->create()] as $user) {
            $this->actingAs($user);
            $this->get('/admin/schedules')->assertOk();
            $this->get('/admin/schedules/create')->assertForbidden();
        }
    }

    public function test_admin_cannot_delete_own_account_but_can_delete_others(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();

        $this->assertFalse($admin->can('delete', $admin));
        $this->assertTrue($admin->can('delete', $other));
    }

    public function test_guru_only_sees_own_schedules_while_piket_and_admin_see_all(): void
    {
        $guruUser = User::factory()->create();
        $otherUser = User::factory()->create();
        $mine = Teacher::create(['user_id' => $guruUser->id, 'teacher_code' => 'T-1', 'full_name' => 'Guru A']);
        $theirs = Teacher::create(['user_id' => $otherUser->id, 'teacher_code' => 'T-2', 'full_name' => 'Guru B']);
        $class = SchoolClass::create(['class_name' => 'X-1', 'academic_year' => '2026/2027']);
        $subject = Subject::create(['subject_name' => 'Matematika']);

        Schedule::create(['teacher_id' => $mine->id, 'class_id' => $class->id, 'subject_id' => $subject->id, 'day_of_week' => 1, 'start_time' => '07:00:00', 'end_time' => '07:45:00']);
        Schedule::create(['teacher_id' => $theirs->id, 'class_id' => $class->id, 'subject_id' => $subject->id, 'day_of_week' => 1, 'start_time' => '07:45:00', 'end_time' => '08:30:00']);

        $this->actingAs($guruUser);
        $this->assertSame(1, ScheduleResource::getEloquentQuery()->count());

        $this->actingAs(User::factory()->guruPiket()->create());
        $this->assertSame(2, ScheduleResource::getEloquentQuery()->count());

        $this->actingAs(User::factory()->admin()->create());
        $this->assertSame(2, ScheduleResource::getEloquentQuery()->count());
    }

    public function test_schedule_conflict_message_detects_same_class_or_teacher(): void
    {
        $user = User::factory()->create();
        $teacher = Teacher::create(['user_id' => $user->id, 'teacher_code' => 'T-1', 'full_name' => 'Guru A']);
        $class = SchoolClass::create(['class_name' => 'X-1', 'academic_year' => '2026/2027']);
        $subject = Subject::create(['subject_name' => 'Matematika']);
        $schedule = Schedule::create(['teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id, 'day_of_week' => 1, 'start_time' => '07:00:00', 'end_time' => '07:45:00']);

        // kelas sama, jam sama
        $this->assertNotNull(ScheduleResource::conflictMessage(1, '07:00', $class->id, 999, null));
        // guru sama, jam sama
        $this->assertNotNull(ScheduleResource::conflictMessage(1, '07:00', 999, $teacher->id, null));
        // jam berbeda: tidak bentrok
        $this->assertNull(ScheduleResource::conflictMessage(1, '08:30', $class->id, $teacher->id, null));
        // mengedit jadwal itu sendiri: tidak dianggap bentrok
        $this->assertNull(ScheduleResource::conflictMessage(1, '07:00', $class->id, $teacher->id, $schedule->id));
    }
}
