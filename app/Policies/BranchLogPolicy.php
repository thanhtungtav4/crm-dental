<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\BranchLog;
use Illuminate\Auth\Access\HandlesAuthorization;

class BranchLogPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BranchLog');
    }

    public function view(AuthUser $authUser, BranchLog $branchLog): bool
    {
        return $authUser->can('View:BranchLog');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BranchLog');
    }

    public function update(AuthUser $authUser, BranchLog $branchLog): bool
    {
        return $authUser->can('Update:BranchLog');
    }

    public function delete(AuthUser $authUser, BranchLog $branchLog): bool
    {
        return $authUser->can('Delete:BranchLog');
    }

    public function restore(AuthUser $authUser, BranchLog $branchLog): bool
    {
        return $authUser->can('Restore:BranchLog');
    }

    public function forceDelete(AuthUser $authUser, BranchLog $branchLog): bool
    {
        return $authUser->can('ForceDelete:BranchLog');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BranchLog');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BranchLog');
    }

    public function replicate(AuthUser $authUser, BranchLog $branchLog): bool
    {
        return $authUser->can('Replicate:BranchLog');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BranchLog');
    }

}