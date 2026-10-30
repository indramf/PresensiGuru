<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;
// use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

class AttendancePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN, User::ROLE_GURU, User::ROLE_GURU_PIKET);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Model $record): bool
    {
        if ($user->hasRole(User::ROLE_ADMIN, User::ROLE_GURU_PIKET)) {
            return true;
        }

        /** @var Attendance $record */
        return $record->recorded_by === $user->getKey();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(User::ROLE_GURU);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Model $record): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Model $record): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Model $record): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Model $record): bool
    {
        return false;
    }
}
