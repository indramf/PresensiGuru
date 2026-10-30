<?php

namespace App\Filament\Resources\Attendances\Pages;

use App\Filament\Resources\Attendances\AttendanceResource;
use App\Support\AttendanceRules;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAttendance extends EditRecord
{
    protected static string $resource = AttendanceResource::class;

    /**
     * Perubahan status lewat AttendanceRules agar alasan wajib dan tercatat di audit log (FR-11).
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return AttendanceRules::changeStatus(
            auth()->user(),
            $record,
            $data['status'],
            $data['reason'] ?? null,
        );
    }
}