<?php

namespace App\Filament\Resources\Schedules\Schemas;

use App\Filament\Resources\Schedules\ScheduleResource;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class ScheduleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('teacher_id')
                ->label('Guru')
                ->relationship('teacher', 'full_name')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('class_id')
                ->label('Kelas')
                ->relationship('schoolClass', 'class_name')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('subject_id')
                ->label('Mata pelajaran')
                ->relationship('subject', 'subject_name')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('day_of_week')
                ->label('Hari')
                ->options(ScheduleResource::dayLabels())
                ->required(),
            TimePicker::make('start_time')
                ->label('Jam mulai')
                ->seconds(false)
                ->required()
                ->rule(fn(Get $get, ?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                    $message = ScheduleResource::conflictMessage(
                        (int) $get('day_of_week'),
                        (string) $value,
                        (int) $get('class_id'),
                        (int) $get('teacher_id'),
                        $record?->getKey(),
                    );

                    if ($message !== null) {
                        $fail($message);
                    }
                }),
            TimePicker::make('end_time')
                ->label('Jam selesai')
                ->seconds(false)
                ->required()
                ->rule(fn(Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                    if (substr((string) $value, 0, 5) <= substr((string) $get('start_time'), 0, 5)) {
                        $fail('Jam selesai harus setelah jam mulai.');
                    }
                }),
        ]);
    }
}
