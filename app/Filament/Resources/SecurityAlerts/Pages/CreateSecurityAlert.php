<?php

namespace App\Filament\Resources\SecurityAlerts\Pages;

use App\Filament\Resources\SecurityAlerts\SecurityAlertResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSecurityAlert extends CreateRecord
{
    protected static string $resource = SecurityAlertResource::class;
}
