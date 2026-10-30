<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\Users\UserResource;
use Filament\Forms\Components\Select;
// use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nama')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Select::make('role')
                ->label('Peran')
                ->options(UserResource::roleLabels())
                ->required(),
            TextInput::make('password')
                ->label('Password')
                ->password()
                ->revealable()
                ->minLength(8)
                ->required(fn(string $operation): bool => $operation === 'create')
                ->dehydrated(fn(?string $state): bool => filled($state))
                ->helperText('Saat mengubah pengguna, kosongkan jika password tidak diganti.'),
            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true),
        ]);
    }
}
