<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\InstallmentPlan;
use Illuminate\Auth\Access\HandlesAuthorization;

class InstallmentPlanPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:InstallmentPlan');
    }

    public function view(AuthUser $authUser, InstallmentPlan $installmentPlan): bool
    {
        return $authUser->can('View:InstallmentPlan');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:InstallmentPlan');
    }

    public function update(AuthUser $authUser, InstallmentPlan $installmentPlan): bool
    {
        return $authUser->can('Update:InstallmentPlan');
    }

    public function delete(AuthUser $authUser, InstallmentPlan $installmentPlan): bool
    {
        return $authUser->can('Delete:InstallmentPlan');
    }

    public function restore(AuthUser $authUser, InstallmentPlan $installmentPlan): bool
    {
        return $authUser->can('Restore:InstallmentPlan');
    }

    public function forceDelete(AuthUser $authUser, InstallmentPlan $installmentPlan): bool
    {
        return $authUser->can('ForceDelete:InstallmentPlan');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:InstallmentPlan');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:InstallmentPlan');
    }

    public function replicate(AuthUser $authUser, InstallmentPlan $installmentPlan): bool
    {
        return $authUser->can('Replicate:InstallmentPlan');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:InstallmentPlan');
    }

}