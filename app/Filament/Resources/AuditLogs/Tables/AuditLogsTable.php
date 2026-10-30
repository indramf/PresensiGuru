<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Models\AuditLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),
                TextColumn::make('actor.name')
                    ->label('Pelaku')
                    ->placeholder('Sistem'),
                TextColumn::make('event_type')
                    ->label('Peristiwa')
                    ->badge()
                    ->searchable(),
                TextColumn::make('target_type')
                    ->label('Objek')
                    ->formatStateUsing(fn(?string $state, AuditLog $record): string => $state
                        ? $state . ($record->target_id ? " #{$record->target_id}" : '')
                        : '-'),
                TextColumn::make('action_result')
                    ->label('Hasil')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'berhasil' => 'success',
                        'gagal' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('reason')
                    ->label('Alasan')
                    ->placeholder('-')
                    ->limit(40),
                TextColumn::make('after_data')
                    ->label('Data setelah')
                    ->state(fn(AuditLog $record): string => $record->after_data
                        ? json_encode($record->after_data, JSON_UNESCAPED_UNICODE)
                        : '-')
                    ->limit(60)
                    ->tooltip(fn(AuditLog $record): ?string => $record->after_data
                        ? json_encode($record->after_data, JSON_UNESCAPED_UNICODE)
                        : null)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event_type')
                    ->label('Peristiwa')
                    ->options(fn(): array => AuditLog::query()->distinct()->orderBy('event_type')->pluck('event_type', 'event_type')->all()),
                SelectFilter::make('action_result')
                    ->label('Hasil')
                    ->options(['berhasil' => 'Berhasil', 'gagal' => 'Gagal', 'ditolak' => 'Ditolak']),
            ])
            ->defaultSort('id', 'desc');
    }
}
