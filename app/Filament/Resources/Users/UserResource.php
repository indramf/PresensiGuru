<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
// use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;


class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'users';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'Administrasi';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Pengguna';

    protected static ?string $modelLabel = 'pengguna';

    protected static ?string $pluralModelLabel = 'pengguna';

    protected static ?string $recordTitleAttribute = 'name';


    // protected static ?string $model = User::class;

    // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    // protected static ?string $recordTitleAttribute = 'name';

    public static function roleLabels(): array
    {
        return [
            User::ROLE_ADMIN => 'Administrator',
            User::ROLE_GURU => 'Guru',
            User::ROLE_GURU_PIKET => 'Guru Piket',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
