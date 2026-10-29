<?php

namespace Database\Seeders;

use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data master dan jadwal UJI (sintetis). Ganti dengan data jadwal sekolah mitra bila sudah diizinkan.
 */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = collect(['Matematika', 'Bahasa Indonesia', 'Bahasa Inggris', 'IPA', 'Informatika'])
            ->map(fn (string $name) => Subject::firstOrCreate(['subject_name' => $name]))
            ->values();

        $classes = collect(['X-1', 'X-2', 'XI-1'])
            ->map(fn (string $name) => SchoolClass::firstOrCreate([
                'class_name' => $name,
                'academic_year' => '2026/2027',
            ]))
            ->values();

        $teachers = collect(['guru1@presensi.test' => 'G-001', 'guru2@presensi.test' => 'G-002', 'guru3@presensi.test' => 'G-003'])
            ->map(function (string $code, string $email) {
                $user = User::where('email', $email)->firstOrFail();

                return Teacher::updateOrCreate(
                    ['teacher_code' => $code],
                    ['user_id' => $user->id, 'full_name' => $user->name, 'is_active' => true]
                );
            })
            ->values();

        $slots = [['07:00', '07:45'], ['07:45', '08:30'], ['08:30', '09:15']];

        // Senin-Jumat (1-5). Rotasi dibuat agar tidak ada guru atau kelas bentrok pada jam yang sama.
        for ($day = 1; $day <= 5; $day++) {
            foreach ($classes as $c => $class) {
                foreach ($slots as $s => [$start, $end]) {
                    Schedule::updateOrCreate(
                        [
                            'class_id' => $class->id,
                            'day_of_week' => $day,
                            'start_time' => $start.':00',
                        ],
                        [
                            'teacher_id' => $teachers[($c + $s + $day) % 3]->id,
                            'subject_id' => $subjects[($c + $s) % 5]->id,
                            'end_time' => $end.':00',
                        ]
                    );
                }
            }
        }
    }
}
