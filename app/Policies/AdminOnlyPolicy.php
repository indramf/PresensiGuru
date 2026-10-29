<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Dasar policy: hanya administrator yang boleh melihat dan mengubah.
 * Filament membaca policy ini untuk menampilkan menu dan membatasi halaman resource (FR-03).
 * Policy turunan dapat menimpa metode tertentu.
 */
abstract class AdminOnlyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }

    public function view(User $user, Model $record): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }

    public function update(User $user, Model $record): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }
}
