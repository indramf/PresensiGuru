<?php

namespace App\Filament\Resources\Monitoring;

use App\Filament\Resources\ClassReports\ClassReportResource;
use App\Filament\Resources\Monitoring\Pages\ListMonitoring;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Dashboard monitoring status kehadiran per jam untuk hari ini (FR-09).
 * Hanya baca. Sesi tanpa presensi tampil "Belum terkonfirmasi" (bukan "tidak hadir")
 * setelah jam mulainya, dan "Belum dimulai" sebelumnya.
 */
class MonitoringResource extends Resource
{
    public const BELUM_DIMULAI = 'belum_dimulai';

    public const BELUM_TERKONFIRMASI = 'belum_terkonfirmasi';

    protected static ?string $model = Schedule::class;

    protected static ?string $slug = 'monitoring';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-eye';

    protected static string|UnitEnum|null $navigationGroup = 'Monitoring Kelas';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Monitoring Hari Ini';

    protected static ?string $modelLabel = 'sesi';

    protected static ?string $pluralModelLabel = 'sesi hari ini';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasRole(User::ROLE_ADMIN, User::ROLE_GURU_PIKET);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function statusLabels(): array
    {
        return [
            Attendance::STATUS_HADIR => 'Hadir',
            Attendance::STATUS_TERLAMBAT => 'Terlambat',
            Attendance::STATUS_IZIN => 'Izin',
            self::BELUM_DIMULAI => 'Belum dimulai',
            self::BELUM_TERKONFIRMASI => 'Belum terkonfirmasi',
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $today = now();

        return parent::getEloquentQuery()
            ->with([
                'teacher',
                'schoolClass',
                'subject',
                'attendances' => fn ($query) => $query->whereDate('attendance_date', $today->toDateString()),
                'classReports' => fn ($query) => $query->whereDate('report_date', $today->toDateString())->latest('id'),
            ])
            ->where('day_of_week', $today->dayOfWeekIso);
    }

    public static function sessionStatus(Schedule $schedule): string
    {
        $attendance = $schedule->attendances->first();

        if ($attendance) {
            return $attendance->status;
        }

        return now()->format('H:i:s') < $schedule->start_time
            ? self::BELUM_DIMULAI
            : self::BELUM_TERKONFIRMASI;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('start_time')
                    ->label('Jam')
                    ->formatStateUsing(fn (Schedule $record): string => substr($record->start_time, 0, 5).'-'.substr($record->end_time, 0, 5))
                    ->sortable(),
                TextColumn::make('schoolClass.class_name')
                    ->label('Kelas'),
                TextColumn::make('subject.subject_name')
                    ->label('Mata pelajaran'),
                TextColumn::make('teacher.full_name')
                    ->label('Guru'),
                TextColumn::make('status')
                    ->label('Status kehadiran')
                    ->state(fn (Schedule $record): string => self::sessionStatus($record))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Attendance::STATUS_HADIR => 'success',
                        Attendance::STATUS_TERLAMBAT => 'warning',
                        Attendance::STATUS_IZIN => 'info',
                        self::BELUM_TERKONFIRMASI => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('konfirmasi')
                    ->label('Konfirmasi kelas')
                    ->state(fn (Schedule $record): string => $record->classReports->first()?->report_status ?? '-')
                    ->formatStateUsing(fn (string $state): string => ClassReportResource::statusLabels()[$state] ?? $state),
            ])
            ->filters([
                Filter::make('belum_terkonfirmasi')
                    ->label('Hanya yang belum terkonfirmasi')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->where('start_time', '<=', now()->format('H:i:s'))
                        ->whereDoesntHave('attendances', fn (Builder $attendance) => $attendance->whereDate('attendance_date', now()->toDateString()))),
            ])
            ->defaultSort('start_time')
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMonitoring::route('/'),
        ];
    }
}
