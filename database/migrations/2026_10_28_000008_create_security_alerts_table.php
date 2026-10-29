<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('rule_code', 10)->index();      // AN-01 ... AN-07
            $table->string('severity', 10);                // rendah | sedang | tinggi
            $table->foreignId('related_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('related_audit_log_id')->nullable()->constrained('audit_logs')->nullOnDelete();
            $table->text('description');
            $table->timestamp('detected_at')->useCurrent();
            $table->string('status', 15)->default('baru'); // baru | ditinjau | ditutup
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_alerts');
    }
};
