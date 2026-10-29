<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Akun UJI (data sintetis, bukan data nyata sekolah).
 * Password diambil dari SEED_DEFAULT_PASSWORD di .env agar tidak ada password tetap di repository.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SEED_DEFAULT_PASSWORD');

        if (! $password) {
            throw new \RuntimeException(
                'Isi SEED_DEFAULT_PASSWORD di file .env sebelum menjalankan seeder (minimal 8 karakter).'
            );
        }

        $accounts = [
            ['name' => 'Administrator Uji', 'email' => 'admin@presensi.test', 'role' => User::ROLE_ADMIN],
            ['name' => 'Guru Piket Uji', 'email' => 'piket1@presensi.test', 'role' => User::ROLE_GURU_PIKET],
            ['name' => 'Guru Uji Satu', 'email' => 'guru1@presensi.test', 'role' => User::ROLE_GURU],
            ['name' => 'Guru Uji Dua', 'email' => 'guru2@presensi.test', 'role' => User::ROLE_GURU],
            ['name' => 'Guru Uji Tiga', 'email' => 'guru3@presensi.test', 'role' => User::ROLE_GURU],
        ];

        foreach ($accounts as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                $account + ['password' => $password, 'is_active' => true, 'email_verified_at' => now()]
            );
        }
    }
}
