<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AppointmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return $authUser->can('ViewAny:Appointment') && $authUser->hasAnyAccessibleBranch();
    }

    public function view(User $authUser, Appointment $appointment): bool
    {
        return $authUser->can('View:Appointment') && $this->canAccessAppointment($authUser, $appointment);
    }

    public function create(User $authUser): bool
    {
        return $authUser->can('Create:Appointment') && $authUser->hasAnyAccessibleBranch();
    }

    public function update(User $authUser, Appointment $appointment): bool
    {
        return $authUser->can('Update:Appointment') && $this->canAccessAppointment($authUser, $appointment);
    }

    public function delete(User $authUser, Appointment $appointment): bool
    {
        return $authUser->can('Delete:Appointment') && $this->canAccessAppointment($authUser, $appointment);
    }

    public function restore(User $authUser, Appointment $appointment): bool
    {
        return $authUser->can('Restore:Appointment') && $this->canAccessAppointment($authUser, $appointment);
    }

    public function forceDelete(User $authUser, Appointment $appointment): bool
    {
        return $authUser->can('ForceDelete:Appointment') && $this->canAccessAppointment($authUser, $appointment);
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Appointment') && $authUser->hasAnyAccessibleBranch();
    }

    public function restoreAny(User $authUser): bool
    {
        return $authUser->can('RestoreAny:Appointment') && $authUser->hasAnyAccessibleBranch();
    }

    public function replicate(User $authUser, Appointment $appointment): bool
    {
        return $authUser->can('Replicate:Appointment') && $this->canAccessAppointment($authUser, $appointment);
    }

    public function reorder(User $authUser): bool
    {
        return $authUser->can('Reorder:Appointment') && $authUser->hasAnyAccessibleBranch();
    }

    protected function canAccessAppointment(User $authUser, Appointment $appointment): bool
    {
        return $authUser->canAccessBranch($appointment->branch_id ? (int) $appointment->branch_id : null);
    }
}
