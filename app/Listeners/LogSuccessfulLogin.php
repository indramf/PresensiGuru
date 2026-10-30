<?php

namespace App\Listeners;

use App\Support\AuditLogger;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        try {
            $id = (int) $event->user->getAuthIdentifier();

            AuditLogger::record('login_success', 'berhasil', $id, 'user', $id);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}