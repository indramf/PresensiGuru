<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->date('report_date');
            $table->foreignId('reported_by')->constrained('users');
            $table->string('report_status', 30);          // mis. kelas_terisi | kelas_kosong
            $table->text('description')->nullable();
            $table->string('follow_up_status', 30)->default('belum');
            $table->timestamps();

            $table->index(['schedule_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_reports');
    }
};
