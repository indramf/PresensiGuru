# Aplikasi Monitoring Presensi Guru

Lapisan tambahan untuk proyek Laravel baru yang memakai **satu panel Filament** untuk semua peran (administrator, guru, guru piket). Paket ini menggantikan paket Batch 1 yang memakai Blade dan Bootstrap.

| Isi | Rujukan proposal |
| --- | --- |
| Skema database 9 tabel (users, teachers, classes, subjects, schedules, attendances, class_reports, audit_logs, security_alerts) | Bab III 3.8; WBS 2.3, 3.1 |
| Akses panel hanya untuk pengguna aktif dengan peran valid (`canAccessPanel`) | FR-01, FR-03 |
| Policy per model: pengelolaan pengguna dan data master hanya administrator; jadwal dapat dilihat guru dan guru piket | FR-03, FR-04, FR-05 |
| Data uji sintetis (akun, guru, kelas, mata pelajaran, jadwal Senin-Jumat) | Bab IV 4.12 |
| 10 pengujian otomatis (akses panel dan policy) | T-01 (sebagian), T-07 (sebagian) |

Belum ada: resource (halaman kelola) di panel, presensi, dashboard monitoring, konfirmasi kelas, audit log yang terisi, hash chaining, dan deteksi anomali. Resource dibuat pada batch berikutnya dari hasil perintah `make:filament-resource`.

## Prasyarat

- PHP 8.2 sampai 8.5, Composer, Git, MySQL 8 atau MariaDB
- Laravel 12 atau 13 (Filament 5 mensyaratkan Laravel 11.28 atau lebih)
- Ekstensi PHP `pdo_mysql` dan `pdo_sqlite` aktif (SQLite dipakai oleh `php artisan test`)

## Langkah

Urutannya penting: pasang Filament **sebelum** menyalin paket ini, karena model `User` memakai kontrak Filament.

1. Buat proyek (pilih satu versi untuk seluruh tim):
   ```
   composer create-project laravel/laravel presensi-guru          (Laravel 13)
   composer create-project "laravel/laravel:^12.0" presensi-guru   (Laravel 12)
   cd presensi-guru
   ```
2. Pasang Filament (di PowerShell Windows gunakan `~5.0` sebagai ganti `^5.0`):
   ```
   composer require filament/filament:"^5.0"
   php artisan filament:install --panels
   ```
   Saat ditanya ID panel, terima nilai bawaan `admin`. Panel akan berada di `/admin`. Pengujian pada paket ini mengandalkan ID `admin`.
3. Salin seluruh isi folder paket ini ke root `presensi-guru`, timpa file yang sama (migrasi `users`, `routes/web.php`, `UserFactory`, `DatabaseSeeder`). Hapus tes bawaan:
   ```
   del tests\Feature\ExampleTest.php        (Windows)
   rm tests/Feature/ExampleTest.php         (macOS/Linux)
   ```
4. Buat database:
   ```sql
   CREATE DATABASE presensi_guru CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
5. Edit `.env`:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=presensi_guru
   DB_USERNAME=root
   DB_PASSWORD=
   SEED_DEFAULT_PASSWORD=ganti-dengan-password-uji-anda
   ```
   Pastikan zona waktu aplikasi `Asia/Jakarta` (di `config/app.php` atau `APP_TIMEZONE` pada `.env`, tergantung struktur file versi Anda).
6. Migrasi, seeder, tes, lalu jalankan:
   ```
   php artisan migrate --seed
   php artisan test
   php artisan serve
   ```
   Buka http://127.0.0.1:8000 (otomatis diarahkan ke `/admin`) dan masuk dengan akun uji.

## Akun uji

Semua memakai password dari `SEED_DEFAULT_PASSWORD`. Login memakai **email**.

| Email | Peran |
| --- | --- |
| admin@presensi.test | admin |
| piket1@presensi.test | guru_piket |
| guru1@presensi.test, guru2@presensi.test, guru3@presensi.test | guru |

Akun dan jadwal ini hanya data uji. Jangan dipakai di lingkungan sekolah.

## Langkah berikutnya (Batch 2)

Buat resource untuk data master, lalu kirimkan hasil/galatnya agar saya sesuaikan:

```
php artisan make:filament-resource User --generate
php artisan make:filament-resource Teacher --generate
php artisan make:filament-resource SchoolClass --generate
php artisan make:filament-resource Subject --generate
php artisan make:filament-resource Schedule --generate
```

Karena policy sudah terpasang, menu yang tampil otomatis mengikuti peran: administrator melihat semuanya, guru dan guru piket hanya melihat Jadwal. Jika sebuah menu tidak muncul, periksa dulu apakah metode `viewAny()` pada policy-nya mengembalikan `true` untuk peran itu.

## Alur Git (sesuai Bab IV 4.8)

```
git init
git add .
git commit -m "chore: inisialisasi proyek Laravel dan Filament"
git checkout -b develop
git checkout -b feature/fondasi-panel-database
```

`.env` sudah diabaikan oleh `.gitignore` bawaan Laravel. Jangan di-commit. Commit juga `composer.lock`.

## Catatan

- Kode di paket ini **belum dijalankan** di mesin saya (tidak ada PHP di lingkungan saya). Jika `migrate` atau `test` menampilkan galat, kirim pesan galatnya apa adanya.
- Halaman login Filament sudah punya pembatasan percobaan login bawaan, tetapi ambangnya belum diverifikasi terhadap AN-01 (5 kegagalan dalam 5 menit). Pencocokan dan audit login akan ditangani pada batch audit log.
- Penolakan akses dan kegagalan login belum masuk audit log.
