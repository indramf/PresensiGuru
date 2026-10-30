<?php

namespace App\Filament\Resources\ClassReports\Tables;

use App\Filament\Resources\ClassReports\ClassReportResource;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ClassReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('report_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('schedule.start_time')
                    ->label('Jam')
                    ->time('H:i'),
                TextColumn::make('schedule.schoolClass.class_name')
                    ->label('Kelas'),
                TextColumn::make('schedule.subject.subject_name')
                    ->label('Mata pelajaran'),
                TextColumn::make('schedule.teacher.full_name')
                    ->label('Guru'),
                TextColumn::make('report_status')
                    ->label('Kondisi')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ClassReportResource::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'kelas_terisi' => 'success',
                        'guru_terlambat' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('follow_up_status')
                    ->label('Tindak lanjut')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ClassReportResource::followUpLabels()[$state] ?? $state),
                TextColumn::make('reporter.name')
                    ->label('Dicatat oleh'),
            ])
            ->filters([
                SelectFilter::make('follow_up_status')
                    ->label('Tindak lanjut')
                    ->options(ClassReportResource::followUpLabels()),
            ])
            ->defaultSort('report_date', 'desc')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
