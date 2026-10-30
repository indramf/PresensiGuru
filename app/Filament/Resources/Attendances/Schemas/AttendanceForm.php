<?php

namespace App\Filament\Resources\Attendances\Schemas;

use App\Models\Attendance;
use App\Support\AttendanceRules;
use Closure;
// use Filament\Forms\Components\DatePicker;
// use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
// use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AttendanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('schedule_id')
                ->label('Jadwal hari ini')
                ->options(fn(): array => AttendanceRules::todayScheduleOptionsFor(auth()->user()))
                ->required()
                ->helperText('Hanya jadwal Anda hari ini yang belum dicatat. Presensi dibuka beberapa menit sebelum jam mulai dan ditutup saat jam selesai.')
                ->rule(fn(): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    $schedule = \App\Models\Schedule::with('teacher')->find($value);

                    if (! $schedule) {
                        $fail('Jadwal tidak ditemukan.');

                        return;
                    }

                    $message = AttendanceRules::validate(auth()->user(), $schedule);

                    if ($message !== null) {
                        $fail($message);
                    }
                }),
            Select::make('status')
                ->label('Status')
                ->options([
                    Attendance::STATUS_HADIR => 'Hadir',
                    Attendance::STATUS_IZIN => 'Izin',
                ])
                ->default(Attendance::STATUS_HADIR)
                ->required()
                ->live()
                ->helperText('Status "Terlambat" ditetapkan otomatis bila Anda mencatat hadir setelah batas toleransi.'),
            Textarea::make('notes')
                ->label('Catatan')
                ->maxLength(500)
                ->required(fn(Get $get): bool => $get('status') === Attendance::STATUS_IZIN)
                ->helperText('Wajib diisi bila status izin.'),
        ]);
    }
}
