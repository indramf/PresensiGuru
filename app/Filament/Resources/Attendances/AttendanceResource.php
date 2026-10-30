<?php

namespace App\Filament\Resources\Attendances;

use App\Filament\Resources\Attendances\Pages\CreateAttendance;
// use App\Filament\Resources\Attendances\Pages\EditAttendance;
use App\Filament\Resources\Attendances\Pages\ListAttendances;
// use App\Filament\Resources\Attendances\Pages\ViewAttendance;
use App\Filament\Resources\Attendances\Schemas\AttendanceForm;
use App\Filament\Resources\Attendances\Schemas\AttendanceInfolist;
use App\Filament\Resources\Attendances\Tables\AttendancesTable;
use App\Models\Attendance;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AttendanceResource extends Resource
{
    protected static ?string $model = Attendance::class;

    protected static ?string $slug = 'attendances';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Presensi';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Presensi';

    protected static ?string $modelLabel = 'presensi';

    protected static ?string $pluralModelLabel = 'presensi';



    // protected static ?string $model = Attendance::class;

    // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'id';

    public static function statusLabels(): array
    {
        return [
            Attendance::STATUS_HADIR => 'Hadir',
            Attendance::STATUS_TERLAMBAT => 'Terlambat',
            Attendance::STATUS_IZIN => 'Izin',
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['teacher', 'schedule.schoolClass', 'schedule.subject']);

        $user = auth()->user();

        if ($user?->hasRole(User::ROLE_GURU)) {
            $query->where('recorded_by', $user->getKey());
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return AttendanceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AttendanceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendancesTable::configure($table);
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
            'index' => ListAttendances::route('/'),
            'create' => CreateAttendance::route('/create'),
            // 'view' => ViewAttendance::route('/{record}'),
            // 'edit' => EditAttendance::route('/{record}/edit'),
        ];
    }
}
