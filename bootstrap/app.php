<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() === 403 && $request->user()) {
                try {
                    \App\Support\AuditLogger::record(
                        'access_denied',
                        'ditolak',
                        (int) $request->user()->getKey(),
                        'url',
                        null,
                        null,
                        ['method' => $request->method(), 'path' => $request->path()],
                    );
                } catch (\Throwable $inner) {
                    report($inner);
                }
            }

            // null = lanjutkan ke tampilan galat bawaan.
            return null;
        });
    })->create();
