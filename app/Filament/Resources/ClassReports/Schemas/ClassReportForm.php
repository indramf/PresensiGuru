<?php

namespace App\Filament\Resources\ClassReports\Schemas;

use App\Filament\Resources\ClassReports\ClassReportResource;
use App\Support\AttendanceRules;
// use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
// use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class ClassReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('schedule_id')
                ->label('Sesi belum terkonfirmasi')
                ->options(fn (?Model $record): array => AttendanceRules::unconfirmedScheduleOptions(null, $record?->schedule_id))
                ->required()
                ->disabled(fn (string $operation): bool => $operation === 'edit')
                ->helperText('Hanya sesi hari ini yang sudah dimulai dan belum ada presensinya.'),
            Select::make('report_status')
                ->label('Kondisi kelas')
                ->options(ClassReportResource::statusLabels())
                ->required(),
            Textarea::make('description')
                ->label('Keterangan')
                ->maxLength(1000),
            Select::make('follow_up_status')
                ->label('Tindak lanjut')
                ->options(ClassReportResource::followUpLabels())
                ->default('belum')
                ->required(),
        ]);
    }
}
