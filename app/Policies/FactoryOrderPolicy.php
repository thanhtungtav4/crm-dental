<?php

namespace App\Policies;

use App\Models\FactoryOrder;
use App\Models\User;

class FactoryOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Admin') || $user->hasRole('Manager');
    }

    public function view(User $user, FactoryOrder $factoryOrder): bool
    {
        return $this->viewAny($user) && $factoryOrder->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, FactoryOrder $factoryOrder): bool
    {
        return $this->view($user, $factoryOrder) && $factoryOrder->isEditable();
    }

    public function delete(User $user, FactoryOrder $factoryOrder): bool
    {
        return false;
    }

    public function transitionStatus(User $user, FactoryOrder $factoryOrder): bool
    {
        return $this->view($user, $factoryOrder)
            && ! in_array($factoryOrder->status, [
                FactoryOrder::STATUS_DELIVERED,
                FactoryOrder::STATUS_CANCELLED,
            ], true);
    }

    public function restore(User $user, FactoryOrder $factoryOrder): bool
    {
        return false;
    }

    public function forceDelete(User $user, FactoryOrder $factoryOrder): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
