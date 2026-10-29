<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model untuk tabel `classes` ("Class" adalah kata cadangan di PHP).
 */
class SchoolClass extends Model
{
    protected $table = 'classes';

    protected $fillable = ['class_name', 'academic_year'];

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'class_id');
    }
}
