<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Support\AuditLogger;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verifikasi')
                ->label('Periksa integritas log')
                ->icon('heroicon-o-shield-check')
                ->requiresConfirmation()
                ->modalDescription('Seluruh rantai hash dan checkpoint akan diperiksa ulang. Pemeriksaan ini sendiri dicatat ke audit log.')
                ->action(function (): void {
                    $result = AuditLogger::verifyChain();

                    $notification = Notification::make()
                        ->title($result['ok'] ? 'Rantai log utuh' : 'Rantai log tidak utuh')
                        ->body($result['message']);

                    $result['ok'] ? $notification->success() : $notification->danger()->persistent();

                    $notification->send();

                    AuditLogger::record(
                        'audit_verified',
                        $result['ok'] ? 'berhasil' : 'gagal',
                        (int) auth()->id(),
                        'audit_log',
                        $result['first_invalid_id'],
                        null,
                        ['checked' => $result['checked']],
                        $result['ok'] ? null : $result['message'],
                    );
                }),
        ];
    }
}
