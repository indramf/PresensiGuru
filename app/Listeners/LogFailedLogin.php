<?php

namespace App\Listeners;

use App\Support\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Str;

class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        try {
            // Hanya identitas yang dicoba (dipangkas). Password tidak pernah dicatat.
            $identifier = Str::limit((string) ($event->credentials['email'] ?? $event->credentials['username'] ?? ''), 100, '');

            AuditLogger::record(
                'login_failed',
                'gagal',
                $event->user ? (int) $event->user->getAuthIdentifier() : null,
                'login',
                null,
                null,
                ['identifier' => $identifier],
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}