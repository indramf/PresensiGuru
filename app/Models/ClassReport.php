<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassReport extends Model
{
    protected $fillable = ['schedule_id', 'report_date', 'reported_by', 'report_status', 'description', 'follow_up_status'];

    protected function casts(): array
    {
        return ['report_date' => 'date'];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
