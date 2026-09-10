<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrderPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any orders.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the specific order.
     */
    public function view(User $user, Order $order): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($order->user_identification === null) {
            return false;
        }

        return (int) $order->user_identification === (int) $user->identification;
    }

    /**
     * Determine whether the user can update the order.
     */
    public function update(User $user, Order $order): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($order->user_identification === null) {
            return false;
        }

        return (int) $order->user_identification === (int) $user->identification && $order->isEditable();
    }

    /**
     * Determine whether the user can update the status of the order.
     */
    public function updateStatus(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the order.
     */
    public function delete(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }
}
