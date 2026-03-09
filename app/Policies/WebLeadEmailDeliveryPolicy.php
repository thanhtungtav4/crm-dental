<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebLeadEmailDelivery;

class WebLeadEmailDeliveryPolicy
{
    public function viewAny(User $authUser): bool
    {
        return WebLeadEmailDelivery::canAccessModule($authUser)
            && $authUser->can('ViewAny:WebLeadEmailDelivery');
    }

    public function view(User $authUser, WebLeadEmailDelivery $delivery): bool
    {
        return $delivery->isVisibleTo($authUser);
    }

    public function create(User $authUser): bool
    {
        return false;
    }

    public function update(User $authUser, WebLeadEmailDelivery $delivery): bool
    {
        if (! WebLeadEmailDelivery::canAccessModule($authUser)) {
            return false;
        }

        if (! $authUser->can('Update:WebLeadEmailDelivery')) {
            return false;
        }

        return ($delivery->branch_id !== null && $authUser->canAccessBranch((int) $delivery->branch_id))
            || ($delivery->branch_id === null && $authUser->hasRole('Admin'));
    }

    public function delete(User $authUser, WebLeadEmailDelivery $delivery): bool
    {
        return false;
    }

    public function restore(User $authUser, WebLeadEmailDelivery $delivery): bool
    {
        return false;
    }

    public function forceDelete(User $authUser, WebLeadEmailDelivery $delivery): bool
    {
        return false;
    }

    public function deleteAny(User $authUser): bool
    {
        return false;
    }
}
