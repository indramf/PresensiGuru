<?php

namespace App\Filament\Resources\Attendances\Pages;

use App\Filament\Resources\Attendances\AttendanceResource;
use App\Models\Schedule;
use App\Support\AttendanceRules;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAttendance extends CreateRecord
{
    protected static string $resource = AttendanceResource::class;

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        $schedule = Schedule::with('teacher')->findOrFail($data['schedule_id']);

        return AttendanceRules::record(
            auth()->user(),
            $schedule,
            $data['status'],
            $data['notes'] ?? null,
        );
    }
}
