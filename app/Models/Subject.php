<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $fillable = ['subject_name'];

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}
