<?php

namespace App\Policies;

use App\Models\Inspection;
use App\Models\User;

class InspectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator() || $user->isOperator();
    }

    public function view(User $user, Inspection $inspection): bool
    {
        return $user->isAdministrator() || $user->isOperator();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Inspection $inspection): bool
    {
        return false;
    }

    public function delete(User $user, Inspection $inspection): bool
    {
        return false;
    }
}
