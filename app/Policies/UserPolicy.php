<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Hanya administrator (lihat AdminOnlyPolicy).
 */
class UserPolicy extends AdminOnlyPolicy
{
    public function delete(User $user, Model $record): bool
    {
        return parent::delete($user, $record) && ! $user->is($record);
    }
}
