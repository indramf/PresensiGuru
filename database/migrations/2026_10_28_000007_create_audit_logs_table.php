<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel audit log. Sengaja tanpa updated_at: entri tidak diubah setelah ditulis.
 * previous_hash dan entry_hash dipakai hash chaining (FR-13), diisi pada batch berikutnya.
 * Password dan token tidak boleh dimasukkan ke before_data / after_data (NFR-05).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 50)->index();
            $table->string('target_type', 50)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('action_result', 20);          // berhasil | gagal | ditolak
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->char('previous_hash', 64)->nullable();
            $table->char('entry_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
