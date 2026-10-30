<?php

return [
    // Menyimpan checkpoint (id, hash, jumlah entri terakhir) di berkas terpisah dari database.
    'checkpoint_enabled' => env('AUDIT_CHECKPOINT_ENABLED', true),

    // Nama berkas pada disk "local" (storage/app/private pada Laravel 11 ke atas).
    'checkpoint_file' => 'audit-checkpoint.json',

    // Kunci yang tidak boleh masuk log (NFR-05). Dibandingkan tanpa membedakan huruf besar-kecil.
    'redacted_keys' => ['password', 'password_confirmation', 'remember_token', 'token', 'secret', 'api_key'],
];