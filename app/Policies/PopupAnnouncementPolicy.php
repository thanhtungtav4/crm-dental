<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PopupAnnouncement;
use App\Models\User;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Auth\Access\HandlesAuthorization;

class PopupAnnouncementPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return $this->canManagePopup($authUser);
    }

    public function view(User $authUser, PopupAnnouncement $popupAnnouncement): bool
    {
        return $this->canManagePopupRecord($authUser, $popupAnnouncement);
    }

    public function create(User $authUser): bool
    {
        return $this->canManagePopup($authUser);
    }

    public function update(User $authUser, PopupAnnouncement $popupAnnouncement): bool
    {
        return $this->canManagePopupRecord($authUser, $popupAnnouncement);
    }

    public function delete(User $authUser, PopupAnnouncement $popupAnnouncement): bool
    {
        return false;
    }

    public function deleteAny(User $authUser): bool
    {
        return false;
    }

    public function restore(User $authUser, PopupAnnouncement $popupAnnouncement): bool
    {
        return false;
    }

    public function forceDelete(User $authUser, PopupAnnouncement $popupAnnouncement): bool
    {
        return false;
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return false;
    }

    public function restoreAny(User $authUser): bool
    {
        return false;
    }

    public function replicate(User $authUser, PopupAnnouncement $popupAnnouncement): bool
    {
        return $this->canManagePopupRecord($authUser, $popupAnnouncement);
    }

    public function reorder(User $authUser): bool
    {
        return $this->canManagePopup($authUser);
    }

    protected function canManagePopup(User $authUser): bool
    {
        return $authUser->hasAnyRole(ClinicRuntimeSettings::popupAnnouncementSenderRoles());
    }

    protected function canManagePopupRecord(User $authUser, PopupAnnouncement $popupAnnouncement): bool
    {
        if (! $this->canManagePopup($authUser)) {
            return false;
        }

        $targetBranchIds = collect($popupAnnouncement->target_branch_ids ?? [])
            ->filter(static fn (mixed $branchId): bool => is_numeric($branchId) && (int) $branchId > 0)
            ->map(static fn (mixed $branchId): int => (int) $branchId)
            ->values()
            ->all();

        if ($targetBranchIds === []) {
            return true;
        }

        if ($authUser->hasRole('Admin')) {
            return true;
        }

        $accessibleBranchIds = $authUser->accessibleBranchIds();

        return collect($targetBranchIds)
            ->contains(fn (int $branchId): bool => in_array($branchId, $accessibleBranchIds, true));
    }
}
