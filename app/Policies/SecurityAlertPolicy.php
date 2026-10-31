<?php

namespace App\Policies;

use App\Models\SecurityAlert;
use App\Models\User;

class SecurityAlertPolicy
{
    /**
     * Peringatan keamanan hanya dilihat dan ditinjau administrator; tidak dapat dibuat manual atau dihapus.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SecurityAlert $securityAlert): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SecurityAlert $securityAlert): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SecurityAlert $securityAlert): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SecurityAlert $securityAlert): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SecurityAlert $securityAlert): bool
    {
        return false;
    }
}
