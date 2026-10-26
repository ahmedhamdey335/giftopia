<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Customer->value;
    }

    public function view(User $user, Order $order): bool
    {
        return in_array($user->role, [UserRole::Admin->value, UserRole::Producer->value], true)
            || ($user->role === UserRole::Customer->value && $order->user_id === $user->id);
    }

    public function update(User $user, Order $order): bool
    {
        return in_array($user->role, [UserRole::Admin->value, UserRole::Producer->value], true);
    }
}
