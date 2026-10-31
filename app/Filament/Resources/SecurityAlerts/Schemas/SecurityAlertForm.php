<?php

namespace App\Filament\Resources\SecurityAlerts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SecurityAlertForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('rule_code')->label('Aturan')->disabled(),
            TextInput::make('severity')->label('Tingkat')->disabled(),
            Textarea::make('description')->label('Deskripsi')->rows(3)->disabled(),
            Select::make('status')
                ->label('Status tinjauan')
                ->options(['baru' => 'Baru', 'ditinjau' => 'Ditinjau', 'ditutup' => 'Ditutup'])
                ->required(),
        ]);
    }
}
