<?php

use Illuminate\Support\Facades\Route;

// Seluruh aplikasi berada di panel Filament (satu panel untuk semua peran).
Route::redirect('/', '/admin');
