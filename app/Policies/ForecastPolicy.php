<?php

namespace App\Policies;

use App\Models\Forecast;
use App\Models\User;

class ForecastPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator() || $user->isOperator();
    }

    public function view(User $user, Forecast $forecast): bool
    {
        return $user->isAdministrator() || $user->isOperator();
    }
}
