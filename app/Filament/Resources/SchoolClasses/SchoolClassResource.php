<?php

namespace App\Filament\Resources\SchoolClasses;

use App\Filament\Resources\SchoolClasses\Pages\CreateSchoolClass;
use App\Filament\Resources\SchoolClasses\Pages\EditSchoolClass;
use App\Filament\Resources\SchoolClasses\Pages\ListSchoolClasses;
use App\Filament\Resources\SchoolClasses\Schemas\SchoolClassForm;
use App\Filament\Resources\SchoolClasses\Tables\SchoolClassesTable;
use App\Models\SchoolClass;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
// use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SchoolClassResource extends Resource
{
    protected static ?string $model = SchoolClass::class;

    protected static ?string $slug = 'school-classes';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Kelas';

    protected static ?string $modelLabel = 'kelas';

    protected static ?string $pluralModelLabel = 'kelas';

    protected static ?string $recordTitleAttribute = 'class_name';


    // protected static ?string $model = SchoolClass::class;

    // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    // protected static ?string $recordTitleAttribute = 'class_name';

    public static function form(Schema $schema): Schema
    {
        return SchoolClassForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SchoolClassesTable::configure($table);
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
            'index' => ListSchoolClasses::route('/'),
            'create' => CreateSchoolClass::route('/create'),
            'edit' => EditSchoolClass::route('/{record}/edit'),
        ];
    }
}
