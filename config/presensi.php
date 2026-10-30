<?php

/**
 * Aturan waktu presensi per jam (FR-06, FR-07).
 * Nilai awal adalah ASUMSI tim dan harus dikonfirmasi dengan pihak sekolah (Bab I 1.7, asumsi 1).
 * Dapat diubah lewat .env tanpa mengubah kode.
 */
return [
    // Presensi sebuah jam pelajaran baru dapat diisi mulai sekian menit sebelum jam mulai.
    // Batas akhir pengisian adalah jam selesai pelajaran tersebut.
    'open_before_minutes' => (int) env('PRESENSI_OPEN_BEFORE_MINUTES', 10),

    // Presensi "hadir" yang dicatat setelah jam mulai + sekian menit dihitung "terlambat".
    'late_after_minutes' => (int) env('PRESENSI_LATE_AFTER_MINUTES', 10),
];
