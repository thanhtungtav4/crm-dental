<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\MaterialBatch;
use Illuminate\Auth\Access\HandlesAuthorization;

class MaterialBatchPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MaterialBatch');
    }

    public function view(AuthUser $authUser, MaterialBatch $materialBatch): bool
    {
        return $authUser->can('View:MaterialBatch');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MaterialBatch');
    }

    public function update(AuthUser $authUser, MaterialBatch $materialBatch): bool
    {
        return $authUser->can('Update:MaterialBatch');
    }

    public function delete(AuthUser $authUser, MaterialBatch $materialBatch): bool
    {
        return $authUser->can('Delete:MaterialBatch');
    }

    public function restore(AuthUser $authUser, MaterialBatch $materialBatch): bool
    {
        return $authUser->can('Restore:MaterialBatch');
    }

    public function forceDelete(AuthUser $authUser, MaterialBatch $materialBatch): bool
    {
        return $authUser->can('ForceDelete:MaterialBatch');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MaterialBatch');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MaterialBatch');
    }

    public function replicate(AuthUser $authUser, MaterialBatch $materialBatch): bool
    {
        return $authUser->can('Replicate:MaterialBatch');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MaterialBatch');
    }

}