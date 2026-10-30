<?php

namespace App\Policies;

use App\Models\ClassReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
// use Illuminate\Auth\Access\Response;

class ClassReportPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN, User::ROLE_GURU_PIKET);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Model $record): bool
    {
        return $user->hasRole(User::ROLE_ADMIN, User::ROLE_GURU_PIKET);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(User::ROLE_GURU_PIKET);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Model $record): bool
    {
        /** @var ClassReport $record */
        return $user->hasRole(User::ROLE_GURU_PIKET) && $record->reported_by === $user->getKey();
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
    public function restore(User $user, ClassReport $classReport): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ClassReport $classReport): bool
    {
        return false;
    }
}
