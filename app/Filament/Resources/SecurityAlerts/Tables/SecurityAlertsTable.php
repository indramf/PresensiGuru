<?php

namespace App\Filament\Resources\SecurityAlerts\Tables;

use App\Models\SecurityAlert;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SecurityAlertsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('detected_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('rule_code')
                    ->label('Aturan')
                    ->badge()
                    ->sortable(),
                TextColumn::make('severity')
                    ->label('Tingkat')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'tinggi' => 'danger',
                        'sedang' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('description')
                    ->label('Deskripsi')
                    ->limit(90)
                    ->tooltip(fn(SecurityAlert $record): string => $record->description)
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'baru' => 'danger',
                        'ditinjau' => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('reviewer.name')
                    ->label('Peninjau')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(['baru' => 'Baru', 'ditinjau' => 'Ditinjau', 'ditutup' => 'Ditutup']),
                SelectFilter::make('severity')
                    ->label('Tingkat')
                    ->options(['rendah' => 'Rendah', 'sedang' => 'Sedang', 'tinggi' => 'Tinggi']),
                SelectFilter::make('rule_code')
                    ->label('Aturan')
                    ->options(collect(range(1, 7))->mapWithKeys(fn(int $n): array => ['AN-0' . $n => 'AN-0' . $n])->all()),
            ])
            ->defaultSort('detected_at', 'desc')
            ->recordActions([
                EditAction::make()->label('Tinjau'),
            ]);
    }
}
