<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AuditLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('actor_user_id')
                    ->numeric()
                    ->default(null),
                TextInput::make('event_type')
                    ->required(),
                TextInput::make('target_type')
                    ->default(null),
                TextInput::make('target_id')
                    ->numeric()
                    ->default(null),
                TextInput::make('action_result')
                    ->required(),
                Textarea::make('before_data')
                    ->default(null)
                    ->columnSpanFull(),
                Textarea::make('after_data')
                    ->default(null)
                    ->columnSpanFull(),
                Textarea::make('reason')
                    ->default(null)
                    ->columnSpanFull(),
                DateTimePicker::make('occurred_at')
                    ->required(),
                TextInput::make('previous_hash')
                    ->default(null),
                TextInput::make('entry_hash')
                    ->default(null),
            ]);
    }
}
