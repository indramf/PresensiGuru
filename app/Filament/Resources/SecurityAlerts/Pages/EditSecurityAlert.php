<?php

namespace App\Filament\Resources\SecurityAlerts\Pages;

use App\Filament\Resources\SecurityAlerts\SecurityAlertResource;
use Filament\Resources\Pages\EditRecord;

class EditSecurityAlert extends EditRecord
{
    protected static string $resource = SecurityAlertResource::class;

    /**
     * Setiap penyimpanan mencatat siapa dan kapan meninjau (FR-15).
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['reviewed_by'] = auth()->id();
        $data['reviewed_at'] = now();

        return $data;
    }
}