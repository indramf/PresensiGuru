<?php

namespace App\Filament\Resources\Attendances\Tables;

use App\Filament\Resources\Attendances\AttendanceResource;
use App\Models\Attendance;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AttendancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('attendance_date')
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
                TextColumn::make('teacher.full_name')
                    ->label('Guru')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AttendanceResource::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Attendance::STATUS_HADIR => 'success',
                        Attendance::STATUS_TERLAMBAT => 'warning',
                        default => 'info',
                    }),
                TextColumn::make('recorded_at')
                    ->label('Dicatat')
                    ->time('H:i'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(AttendanceResource::statusLabels()),
            ])
            ->defaultSort('recorded_at', 'desc');
    }
}
