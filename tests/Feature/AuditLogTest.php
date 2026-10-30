<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Audit log dan hash chaining (FR-12, FR-13, FR-15, NFR-05, NFR-06). Bagian dari T-10 dan T-11 (Bab V).
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_log_is_valid(): void
    {
        $result = AuditLogger::verifyChain();

        $this->assertTrue($result['ok']);
        $this->assertSame(0, $result['checked']);
    }

    public function test_first_entry_uses_genesis_and_next_entry_links_to_it(): void
    {
        $first = AuditLogger::record('uji_a');
        $second = AuditLogger::record('uji_b');

        $this->assertSame(AuditLogger::genesis(), $first->previous_hash);
        $this->assertSame($first->entry_hash, $second->previous_hash);
        $this->assertNotSame($first->entry_hash, $second->entry_hash);
    }

    public function test_untouched_chain_is_valid(): void
    {
        AuditLogger::record('uji_a', 'berhasil', null, 'x', 1, null, ['k' => 'v'], 'alasan');
        AuditLogger::record('uji_b');
        AuditLogger::record('uji_c', 'gagal');

        $result = AuditLogger::verifyChain();

        $this->assertTrue($result['ok'], $result['message']);
        $this->assertSame(3, $result['checked']);
    }

    public function test_modified_entry_is_detected(): void
    {
        AuditLogger::record('uji_a');
        $second = AuditLogger::record('uji_b', 'berhasil', null, null, null, null, null, 'asli');
        AuditLogger::record('uji_c');

        DB::table('audit_logs')->where('id', $second->id)->update(['reason' => 'diubah']);

        $result = AuditLogger::verifyChain();

        $this->assertFalse($result['ok']);
        $this->assertSame($second->id, $result['first_invalid_id']);
    }

    public function test_deleted_entry_is_detected(): void
    {
        AuditLogger::record('uji_a');
        $second = AuditLogger::record('uji_b');
        $third = AuditLogger::record('uji_c');

        DB::table('audit_logs')->where('id', $second->id)->delete();

        $result = AuditLogger::verifyChain();

        $this->assertFalse($result['ok']);
        $this->assertSame($third->id, $result['first_invalid_id']);
    }

    public function test_sensitive_keys_are_never_stored(): void
    {
        $log = AuditLogger::record('uji', 'berhasil', null, null, null, null, [
            'password' => 'rahasia',
            'nested' => ['token' => 'abc', 'aman' => 'ya'],
        ]);

        $stored = AuditLog::find($log->id);

        $this->assertSame(['nested' => ['aman' => 'ya']], $stored->after_data);
        $this->assertStringNotContainsString('rahasia', json_encode($stored->getAttributes()));
    }

    public function test_checkpoint_detects_removal_of_the_last_entry(): void
    {
        config(['audit.checkpoint_enabled' => true]);
        Storage::fake('local');

        AuditLogger::record('uji_a');
        AuditLogger::record('uji_b');
        $third = AuditLogger::record('uji_c');

        $this->assertTrue(AuditLogger::verifyChain()['ok']);

        // Rantai tetap utuh bila entri terakhir dihapus; hanya checkpoint yang dapat mendeteksinya.
        DB::table('audit_logs')->where('id', $third->id)->delete();

        $result = AuditLogger::verifyChain();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Checkpoint', $result['message']);
    }

    public function test_creating_a_user_is_audited_without_the_password(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $new = User::factory()->create(['name' => 'Guru Baru']);

        $entry = AuditLog::query()
            ->where('event_type', 'user_created')
            ->where('target_id', $new->id)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame($admin->id, $entry->actor_user_id);
        $this->assertArrayNotHasKey('password', $entry->after_data);
        $this->assertArrayHasKey('kata_sandi', $entry->after_data);
        $this->assertStringNotContainsString($new->password, json_encode($entry->after_data));
    }

    public function test_login_events_are_audited(): void
    {
        $user = User::factory()->create();

        event(new Login('web', $user, false));
        event(new Failed('web', null, ['email' => 'salah@contoh.test', 'password' => 'kata-sandi-salah']));

        $success = AuditLog::where('event_type', 'login_success')->first();
        $failed = AuditLog::where('event_type', 'login_failed')->first();

        $this->assertNotNull($success);
        $this->assertSame($user->id, $success->actor_user_id);

        $this->assertNotNull($failed);
        $this->assertSame('gagal', $failed->action_result);
        $this->assertSame('salah@contoh.test', $failed->after_data['identifier']);
        $this->assertStringNotContainsString('kata-sandi-salah', json_encode($failed->getAttributes()));
    }

    public function test_only_admin_can_open_audit_log_page(): void
    {
        $this->get('/admin/audit-logs')->assertRedirect(route('filament.admin.auth.login'));

        $this->actingAs(User::factory()->admin()->create())->get('/admin/audit-logs')->assertOk();
        $this->actingAs(User::factory()->create())->get('/admin/audit-logs')->assertForbidden();
        $this->actingAs(User::factory()->guruPiket()->create())->get('/admin/audit-logs')->assertForbidden();
    }

    public function test_forbidden_access_is_audited(): void
    {
        $guru = User::factory()->create();

        $this->actingAs($guru)->get('/admin/audit-logs')->assertForbidden();

        $this->assertTrue(
            AuditLog::query()
                ->where('event_type', 'access_denied')
                ->where('actor_user_id', $guru->id)
                ->exists()
        );
    }
}