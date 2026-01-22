<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PlanItem;
use Illuminate\Auth\Access\HandlesAuthorization;

class PlanItemPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PlanItem');
    }

    public function view(AuthUser $authUser, PlanItem $planItem): bool
    {
        return $authUser->can('View:PlanItem');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PlanItem');
    }

    public function update(AuthUser $authUser, PlanItem $planItem): bool
    {
        return $authUser->can('Update:PlanItem');
    }

    public function delete(AuthUser $authUser, PlanItem $planItem): bool
    {
        return $authUser->can('Delete:PlanItem');
    }

    public function restore(AuthUser $authUser, PlanItem $planItem): bool
    {
        return $authUser->can('Restore:PlanItem');
    }

    public function forceDelete(AuthUser $authUser, PlanItem $planItem): bool
    {
        return $authUser->can('ForceDelete:PlanItem');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PlanItem');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PlanItem');
    }

    public function replicate(AuthUser $authUser, PlanItem $planItem): bool
    {
        return $authUser->can('Replicate:PlanItem');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PlanItem');
    }

}