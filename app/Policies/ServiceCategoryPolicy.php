<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ServiceCategory;
use Illuminate\Auth\Access\HandlesAuthorization;

class ServiceCategoryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ServiceCategory');
    }

    public function view(AuthUser $authUser, ServiceCategory $serviceCategory): bool
    {
        return $authUser->can('View:ServiceCategory');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ServiceCategory');
    }

    public function update(AuthUser $authUser, ServiceCategory $serviceCategory): bool
    {
        return $authUser->can('Update:ServiceCategory');
    }

    public function delete(AuthUser $authUser, ServiceCategory $serviceCategory): bool
    {
        return $authUser->can('Delete:ServiceCategory');
    }

    public function restore(AuthUser $authUser, ServiceCategory $serviceCategory): bool
    {
        return $authUser->can('Restore:ServiceCategory');
    }

    public function forceDelete(AuthUser $authUser, ServiceCategory $serviceCategory): bool
    {
        return $authUser->can('ForceDelete:ServiceCategory');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ServiceCategory');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ServiceCategory');
    }

    public function replicate(AuthUser $authUser, ServiceCategory $serviceCategory): bool
    {
        return $authUser->can('Replicate:ServiceCategory');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ServiceCategory');
    }

}