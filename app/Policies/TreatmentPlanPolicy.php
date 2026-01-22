<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\TreatmentPlan;
use Illuminate\Auth\Access\HandlesAuthorization;

class TreatmentPlanPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TreatmentPlan');
    }

    public function view(AuthUser $authUser, TreatmentPlan $treatmentPlan): bool
    {
        return $authUser->can('View:TreatmentPlan');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TreatmentPlan');
    }

    public function update(AuthUser $authUser, TreatmentPlan $treatmentPlan): bool
    {
        return $authUser->can('Update:TreatmentPlan');
    }

    public function delete(AuthUser $authUser, TreatmentPlan $treatmentPlan): bool
    {
        return $authUser->can('Delete:TreatmentPlan');
    }

    public function restore(AuthUser $authUser, TreatmentPlan $treatmentPlan): bool
    {
        return $authUser->can('Restore:TreatmentPlan');
    }

    public function forceDelete(AuthUser $authUser, TreatmentPlan $treatmentPlan): bool
    {
        return $authUser->can('ForceDelete:TreatmentPlan');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TreatmentPlan');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TreatmentPlan');
    }

    public function replicate(AuthUser $authUser, TreatmentPlan $treatmentPlan): bool
    {
        return $authUser->can('Replicate:TreatmentPlan');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TreatmentPlan');
    }

}