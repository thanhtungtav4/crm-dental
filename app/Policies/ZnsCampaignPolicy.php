<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ZnsCampaign;

class ZnsCampaignPolicy
{
    public function viewAny(User $authUser): bool
    {
        return ZnsCampaign::canAccessModule($authUser);
    }

    public function view(User $authUser, ZnsCampaign $znsCampaign): bool
    {
        return $znsCampaign->isVisibleTo($authUser);
    }

    public function create(User $authUser): bool
    {
        return ZnsCampaign::canAccessModule($authUser);
    }

    public function update(User $authUser, ZnsCampaign $znsCampaign): bool
    {
        return $znsCampaign->isVisibleTo($authUser);
    }

    public function delete(User $authUser, ZnsCampaign $znsCampaign): bool
    {
        return false;
    }

    public function restore(User $authUser, ZnsCampaign $znsCampaign): bool
    {
        return false;
    }

    public function forceDelete(User $authUser, ZnsCampaign $znsCampaign): bool
    {
        return false;
    }

    public function deleteAny(User $authUser): bool
    {
        return false;
    }

    public function restoreAny(User $authUser): bool
    {
        return false;
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return false;
    }
}
