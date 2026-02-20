<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ToothCondition;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ToothConditionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ToothCondition');
    }

    public function view(AuthUser $authUser, ToothCondition $toothCondition): bool
    {
        return $authUser->can('View:ToothCondition');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ToothCondition');
    }

    public function update(AuthUser $authUser, ToothCondition $toothCondition): bool
    {
        return $authUser->can('Update:ToothCondition');
    }

    public function delete(AuthUser $authUser, ToothCondition $toothCondition): bool
    {
        return $authUser->can('Delete:ToothCondition');
    }

    public function restore(AuthUser $authUser, ToothCondition $toothCondition): bool
    {
        return $authUser->can('Restore:ToothCondition');
    }

    public function forceDelete(AuthUser $authUser, ToothCondition $toothCondition): bool
    {
        return $authUser->can('ForceDelete:ToothCondition');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ToothCondition');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ToothCondition');
    }

    public function replicate(AuthUser $authUser, ToothCondition $toothCondition): bool
    {
        return $authUser->can('Replicate:ToothCondition');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ToothCondition');
    }
}

