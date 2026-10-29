<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Akses panel (FR-01, FR-03) dan policy per peran. Bagian dari T-01 dan T-07 (Bab V).
 */
class PanelAccessAndPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_panel_login(): void
    {
        $this->get('/admin')->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_admin_can_open_panel(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
    }

    public function test_guru_can_open_panel(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertOk();
    }

    public function test_guru_piket_can_open_panel(): void
    {
        $this->actingAs(User::factory()->guruPiket()->create())->get('/admin')->assertOk();
    }

    public function test_inactive_user_cannot_open_panel(): void
    {
        $this->actingAs(User::factory()->inactive()->create())->get('/admin')->assertForbidden();
    }

    public function test_admin_has_full_access_to_master_data_policies(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([User::class, Teacher::class, SchoolClass::class, Subject::class, Schedule::class] as $model) {
            $this->assertTrue($admin->can('viewAny', $model), "viewAny {$model}");
            $this->assertTrue($admin->can('create', $model), "create {$model}");
        }
    }

    public function test_guru_cannot_manage_users_or_master_data(): void
    {
        $guru = User::factory()->create();

        foreach ([User::class, Teacher::class, SchoolClass::class, Subject::class] as $model) {
            $this->assertFalse($guru->can('viewAny', $model), "viewAny {$model}");
            $this->assertFalse($guru->can('create', $model), "create {$model}");
        }
    }

    public function test_guru_piket_cannot_manage_users_or_master_data(): void
    {
        $piket = User::factory()->guruPiket()->create();

        foreach ([User::class, Teacher::class, SchoolClass::class, Subject::class] as $model) {
            $this->assertFalse($piket->can('viewAny', $model), "viewAny {$model}");
            $this->assertFalse($piket->can('create', $model), "create {$model}");
        }
    }

    public function test_guru_and_guru_piket_can_only_view_schedules(): void
    {
        foreach ([User::factory()->create(), User::factory()->guruPiket()->create()] as $user) {
            $this->assertTrue($user->can('viewAny', Schedule::class));
            $this->assertFalse($user->can('create', Schedule::class));
        }
    }

    public function test_password_is_stored_as_hash(): void
    {
        $user = User::factory()->create();

        $this->assertNotSame('password', $user->password);
        $this->assertTrue(Hash::check('password', $user->password));
    }
}
