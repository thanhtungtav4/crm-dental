<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\TreatmentMaterial;
use Illuminate\Auth\Access\HandlesAuthorization;

class TreatmentMaterialPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TreatmentMaterial');
    }

    public function view(AuthUser $authUser, TreatmentMaterial $treatmentMaterial): bool
    {
        return $authUser->can('View:TreatmentMaterial');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TreatmentMaterial');
    }

    public function update(AuthUser $authUser, TreatmentMaterial $treatmentMaterial): bool
    {
        return $authUser->can('Update:TreatmentMaterial');
    }

    public function delete(AuthUser $authUser, TreatmentMaterial $treatmentMaterial): bool
    {
        return $authUser->can('Delete:TreatmentMaterial');
    }

    public function restore(AuthUser $authUser, TreatmentMaterial $treatmentMaterial): bool
    {
        return $authUser->can('Restore:TreatmentMaterial');
    }

    public function forceDelete(AuthUser $authUser, TreatmentMaterial $treatmentMaterial): bool
    {
        return $authUser->can('ForceDelete:TreatmentMaterial');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TreatmentMaterial');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TreatmentMaterial');
    }

    public function replicate(AuthUser $authUser, TreatmentMaterial $treatmentMaterial): bool
    {
        return $authUser->can('Replicate:TreatmentMaterial');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TreatmentMaterial');
    }

}