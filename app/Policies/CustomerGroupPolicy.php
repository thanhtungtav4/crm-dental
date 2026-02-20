<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CustomerGroup;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CustomerGroupPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CustomerGroup');
    }

    public function view(AuthUser $authUser, CustomerGroup $customerGroup): bool
    {
        return $authUser->can('View:CustomerGroup');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CustomerGroup');
    }

    public function update(AuthUser $authUser, CustomerGroup $customerGroup): bool
    {
        return $authUser->can('Update:CustomerGroup');
    }

    public function delete(AuthUser $authUser, CustomerGroup $customerGroup): bool
    {
        return $authUser->can('Delete:CustomerGroup');
    }

    public function restore(AuthUser $authUser, CustomerGroup $customerGroup): bool
    {
        return $authUser->can('Restore:CustomerGroup');
    }

    public function forceDelete(AuthUser $authUser, CustomerGroup $customerGroup): bool
    {
        return $authUser->can('ForceDelete:CustomerGroup');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CustomerGroup');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CustomerGroup');
    }

    public function replicate(AuthUser $authUser, CustomerGroup $customerGroup): bool
    {
        return $authUser->can('Replicate:CustomerGroup');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CustomerGroup');
    }
}

