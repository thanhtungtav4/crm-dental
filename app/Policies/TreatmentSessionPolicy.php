<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\TreatmentSession;
use Illuminate\Auth\Access\HandlesAuthorization;

class TreatmentSessionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TreatmentSession');
    }

    public function view(AuthUser $authUser, TreatmentSession $treatmentSession): bool
    {
        return $authUser->can('View:TreatmentSession');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TreatmentSession');
    }

    public function update(AuthUser $authUser, TreatmentSession $treatmentSession): bool
    {
        return $authUser->can('Update:TreatmentSession');
    }

    public function delete(AuthUser $authUser, TreatmentSession $treatmentSession): bool
    {
        return $authUser->can('Delete:TreatmentSession');
    }

    public function restore(AuthUser $authUser, TreatmentSession $treatmentSession): bool
    {
        return $authUser->can('Restore:TreatmentSession');
    }

    public function forceDelete(AuthUser $authUser, TreatmentSession $treatmentSession): bool
    {
        return $authUser->can('ForceDelete:TreatmentSession');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TreatmentSession');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TreatmentSession');
    }

    public function replicate(AuthUser $authUser, TreatmentSession $treatmentSession): bool
    {
        return $authUser->can('Replicate:TreatmentSession');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TreatmentSession');
    }

}