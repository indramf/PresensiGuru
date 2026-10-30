<?php

namespace App\Filament\Resources\Teachers\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class TeacherForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('teacher_code')
                ->label('Kode guru')
                ->required()
                ->maxLength(30)
                ->unique(ignoreRecord: true),
            TextInput::make('full_name')
                ->label('Nama lengkap')
                ->required()
                ->maxLength(255),
            Select::make('user_id')
                ->label('Akun pengguna')
                ->relationship(
                    name: 'user',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn(Builder $query) => $query->whereIn('role', [User::ROLE_GURU, User::ROLE_GURU_PIKET]),
                )
                ->searchable()
                ->preload()
                ->unique(ignoreRecord: true)
                ->helperText('Opsional. Hubungkan guru dengan akun agar dapat masuk dan mencatat presensi.'),
            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true),
        ]);
    }
}
