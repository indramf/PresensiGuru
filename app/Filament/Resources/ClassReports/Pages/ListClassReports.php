<?php

namespace App\Filament\Resources\ClassReports\Pages;

use App\Filament\Resources\ClassReports\ClassReportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClassReports extends ListRecords
{
    protected static string $resource = ClassReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Catat konfirmasi kelas'),
        ];
    }
}
