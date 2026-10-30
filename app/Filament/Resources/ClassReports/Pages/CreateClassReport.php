<?php

namespace App\Filament\Resources\ClassReports\Pages;

use App\Filament\Resources\ClassReports\ClassReportResource;
use App\Support\AttendanceRules;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateClassReport extends CreateRecord
{
    protected static string $resource = ClassReportResource::class;

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        return AttendanceRules::recordClassReport(
            auth()->user(),
            (int) $data['schedule_id'],
            $data['report_status'],
            $data['description'] ?? null,
            $data['follow_up_status'] ?? 'belum',
        );
    }
}
