<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Disease;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DiseasePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Disease');
    }

    public function view(AuthUser $authUser, Disease $disease): bool
    {
        return $authUser->can('View:Disease');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Disease');
    }

    public function update(AuthUser $authUser, Disease $disease): bool
    {
        return $authUser->can('Update:Disease');
    }

    public function delete(AuthUser $authUser, Disease $disease): bool
    {
        return $authUser->can('Delete:Disease');
    }

    public function restore(AuthUser $authUser, Disease $disease): bool
    {
        return $authUser->can('Restore:Disease');
    }

    public function forceDelete(AuthUser $authUser, Disease $disease): bool
    {
        return $authUser->can('ForceDelete:Disease');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Disease');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Disease');
    }

    public function replicate(AuthUser $authUser, Disease $disease): bool
    {
        return $authUser->can('Replicate:Disease');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Disease');
    }
}

