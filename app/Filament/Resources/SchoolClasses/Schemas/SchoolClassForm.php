<?php

namespace App\Filament\Resources\SchoolClasses\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class SchoolClassForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('class_name')
                ->label('Nama kelas')
                ->required()
                ->maxLength(30)
                // Nama kelas harus unik pada tahun ajaran yang sama.
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn(Unique $rule, Get $get) => $rule->where('academic_year', $get('academic_year')),
                ),
            TextInput::make('academic_year')
                ->label('Tahun ajaran')
                ->required()
                ->maxLength(9)
                ->regex('/^\d{4}\/\d{4}$/')
                ->placeholder('2026/2027')
                ->helperText('Format: 2026/2027'),
        ]);
    }
}
