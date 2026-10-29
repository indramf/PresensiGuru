<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Jadwal: administrator mengelola; guru dan guru piket hanya boleh melihat.
 */
class SchedulePolicy extends AdminOnlyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN, User::ROLE_GURU, User::ROLE_GURU_PIKET);
    }

    public function view(User $user, Model $record): bool
    {
        return $user->hasRole(User::ROLE_ADMIN, User::ROLE_GURU, User::ROLE_GURU_PIKET);
    }
}
