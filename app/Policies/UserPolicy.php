<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdministrator() && ! $model->isService();
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isAdministrator() && ! $model->isService() && $user->id !== $model->id;
    }
}
