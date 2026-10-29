<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');   // 1 = Senin ... 7 = Minggu (ISO-8601)
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            // Satu kelas dan satu guru tidak boleh punya dua jadwal pada jam mulai yang sama.
            $table->unique(['class_id', 'day_of_week', 'start_time']);
            $table->unique(['teacher_id', 'day_of_week', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
