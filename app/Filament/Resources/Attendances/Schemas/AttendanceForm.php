<?php

namespace App\Filament\Resources\Attendances\Schemas;

use App\Filament\Resources\Attendances\AttendanceResource;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Support\AttendanceRules;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
                ->visibleOn('create')
                ->helperText('Hanya jadwal Anda hari ini yang belum dicatat. Presensi dibuka beberapa menit sebelum jam mulai dan ditutup saat jam selesai.')
                ->rule(fn(): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    $schedule = Schedule::with('teacher')->find($value);

                    if (! $schedule) {
                        $fail('Jadwal tidak ditemukan.');

                        return;
                    }

                    // validateAndLog: penolakan ikut tercatat di audit log (bahan AN-02, AN-03, AN-04).
                    $message = AttendanceRules::validateAndLog(auth()->user(), $schedule);

                    if ($message !== null) {
                        $fail($message);
                    }
                }),
            Select::make('status')
                ->label('Status')
                ->options(fn(string $operation): array => $operation === 'edit'
                    ? AttendanceResource::statusLabels()
                    : [
                        Attendance::STATUS_HADIR => 'Hadir',
                        Attendance::STATUS_IZIN => 'Izin',
                    ])
                ->default(Attendance::STATUS_HADIR)
                ->required()
                ->live()
                ->helperText('Saat mencatat, status "Terlambat" ditetapkan otomatis bila hadir setelah batas toleransi.'),
            Textarea::make('notes')
                ->label('Catatan')
                ->maxLength(500)
                ->visibleOn('create')
                ->required(fn(Get $get): bool => $get('status') === Attendance::STATUS_IZIN)
                ->helperText('Wajib diisi bila status izin.'),
            Textarea::make('reason')
                ->label('Alasan perubahan status')
                ->maxLength(500)
                ->required()
                ->visibleOn('edit')
                ->helperText('Wajib. Alasan beserta status sebelum dan sesudah dicatat ke audit log.'),
        ]);
    }
}
