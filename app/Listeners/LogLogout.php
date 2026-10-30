<?php

namespace App\Listeners;

use App\Support\AuditLogger;
use Illuminate\Auth\Events\Logout;

class LogLogout
{
    public function handle(Logout $event): void
    {
        try {
            $id = $event->user ? (int) $event->user->getAuthIdentifier() : null;

            AuditLogger::record('logout', 'berhasil', $id, 'user', $id);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}