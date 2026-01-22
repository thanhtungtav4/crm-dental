<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Billing;
use Illuminate\Auth\Access\HandlesAuthorization;

class BillingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Billing');
    }

    public function view(AuthUser $authUser, Billing $billing): bool
    {
        return $authUser->can('View:Billing');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Billing');
    }

    public function update(AuthUser $authUser, Billing $billing): bool
    {
        return $authUser->can('Update:Billing');
    }

    public function delete(AuthUser $authUser, Billing $billing): bool
    {
        return $authUser->can('Delete:Billing');
    }

    public function restore(AuthUser $authUser, Billing $billing): bool
    {
        return $authUser->can('Restore:Billing');
    }

    public function forceDelete(AuthUser $authUser, Billing $billing): bool
    {
        return $authUser->can('ForceDelete:Billing');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Billing');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Billing');
    }

    public function replicate(AuthUser $authUser, Billing $billing): bool
    {
        return $authUser->can('Replicate:Billing');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Billing');
    }

}