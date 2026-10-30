<?php

namespace App\Filament\Resources\Schedules;

use App\Filament\Resources\Schedules\Pages\CreateSchedule;
use App\Filament\Resources\Schedules\Pages\EditSchedule;
use App\Filament\Resources\Schedules\Pages\ListSchedules;
use App\Filament\Resources\Schedules\Schemas\ScheduleForm;
use App\Filament\Resources\Schedules\Tables\SchedulesTable;
use App\Models\Schedule;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ScheduleResource extends Resource
{
    protected static ?string $model = Schedule::class;

    protected static ?string $slug = 'schedules';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Jadwal';

    protected static ?string $modelLabel = 'jadwal';

    protected static ?string $pluralModelLabel = 'jadwal';


    // protected static ?string $model = Schedule::class;

    // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    // protected static ?string $recordTitleAttribute = 'id';

    public static function dayLabels(): array
    {
        return [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['teacher', 'schoolClass', 'subject']);

        $user = auth()->user();

        if ($user?->hasRole(User::ROLE_GURU)) {
            $query->whereHas('teacher', fn (Builder $teacher) => $teacher->where('user_id', $user->getKey()));
        }

        return $query;
    }

    public static function conflictMessage(int $day, string $startTime, int $classId, int $teacherId, mixed $ignoreId = null): ?string
    {
        if ($day === 0 || $startTime === '') {
            return null;
        }

        $time = substr($startTime, 0, 5).':00';

        $base = Schedule::query()
            ->where('day_of_week', $day)
            ->where('start_time', $time)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId));

        if ($classId > 0 && (clone $base)->where('class_id', $classId)->exists()) {
            return 'Kelas ini sudah memiliki jadwal pada hari dan jam mulai yang sama.';
        }

        if ($teacherId > 0 && (clone $base)->where('teacher_id', $teacherId)->exists()) {
            return 'Guru ini sudah memiliki jadwal pada hari dan jam mulai yang sama.';
        }

        return null;
    }

    public static function form(Schema $schema): Schema
    {
        return ScheduleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SchedulesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSchedules::route('/'),
            'create' => CreateSchedule::route('/create'),
            'edit' => EditSchedule::route('/{record}/edit'),
        ];
    }
}
