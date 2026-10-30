<?php

namespace App\Filament\Resources\ClassReports;

use App\Filament\Resources\ClassReports\Pages\CreateClassReport;
use App\Filament\Resources\ClassReports\Pages\EditClassReport;
use App\Filament\Resources\ClassReports\Pages\ListClassReports;
use App\Filament\Resources\ClassReports\Schemas\ClassReportForm;
use App\Filament\Resources\ClassReports\Tables\ClassReportsTable;
use App\Models\ClassReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
// use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ClassReportResource extends Resource
{
    protected static ?string $model = ClassReport::class;

    protected static ?string $slug = 'class-reports';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Monitoring Kelas';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Konfirmasi Kelas';

    protected static ?string $modelLabel = 'konfirmasi kelas';

    protected static ?string $pluralModelLabel = 'konfirmasi kelas';



    // protected static ?string $model = ClassReport::class;

    // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'id';

    public static function statusLabels(): array
    {
        return [
            'kelas_terisi' => 'Kelas terisi',
            'kelas_kosong' => 'Kelas kosong',
            'guru_terlambat' => 'Guru terlambat',
        ];
    }

    public static function followUpLabels(): array
    {
        return [
            'belum' => 'Belum ditindaklanjuti',
            'ditindaklanjuti' => 'Sedang ditindaklanjuti',
            'selesai' => 'Selesai',
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['reporter', 'schedule.teacher', 'schedule.schoolClass', 'schedule.subject']);
    }

    public static function form(Schema $schema): Schema
    {
        return ClassReportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClassReportsTable::configure($table);
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
            'index' => ListClassReports::route('/'),
            'create' => CreateClassReport::route('/create'),
            'edit' => EditClassReport::route('/{record}/edit'),
        ];
    }
}
