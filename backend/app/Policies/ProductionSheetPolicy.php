<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\OrderItem;
use App\Models\User;

class ProductionSheetPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin->value, UserRole::Producer->value], true);
    }

    public function view(User $user, OrderItem $item): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, OrderItem $item): bool
    {
        return $this->viewAny($user);
    }
}
