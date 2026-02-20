<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DiseaseGroup;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DiseaseGroupPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DiseaseGroup');
    }

    public function view(AuthUser $authUser, DiseaseGroup $diseaseGroup): bool
    {
        return $authUser->can('View:DiseaseGroup');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DiseaseGroup');
    }

    public function update(AuthUser $authUser, DiseaseGroup $diseaseGroup): bool
    {
        return $authUser->can('Update:DiseaseGroup');
    }

    public function delete(AuthUser $authUser, DiseaseGroup $diseaseGroup): bool
    {
        return $authUser->can('Delete:DiseaseGroup');
    }

    public function restore(AuthUser $authUser, DiseaseGroup $diseaseGroup): bool
    {
        return $authUser->can('Restore:DiseaseGroup');
    }

    public function forceDelete(AuthUser $authUser, DiseaseGroup $diseaseGroup): bool
    {
        return $authUser->can('ForceDelete:DiseaseGroup');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DiseaseGroup');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DiseaseGroup');
    }

    public function replicate(AuthUser $authUser, DiseaseGroup $diseaseGroup): bool
    {
        return $authUser->can('Replicate:DiseaseGroup');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DiseaseGroup');
    }
}

