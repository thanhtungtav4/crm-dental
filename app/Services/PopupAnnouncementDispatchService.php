<?php

namespace App\Services;

use App\Models\PopupAnnouncement;
use App\Models\PopupAnnouncementDelivery;
use App\Models\User;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PopupAnnouncementDispatchService
{
    /**
     * @return array{enabled: bool, announcements_processed: int, announcements_expired: int, deliveries_created: int, deliveries_expired: int}
     */
    public function dispatchDueAnnouncements(): array
    {
        if (! ClinicRuntimeSettings::popupAnnouncementsEnabled()) {
            return [
                'enabled' => false,
                'announcements_processed' => 0,
                'announcements_expired' => 0,
                'deliveries_created' => 0,
                'deliveries_expired' => 0,
            ];
        }

        $announcementsExpired = $this->expireEndedAnnouncements();
        $deliveriesCreated = 0;
        $processed = 0;

        $dueAnnouncements = PopupAnnouncement::query()
            ->whereIn('status', [
                PopupAnnouncement::STATUS_SCHEDULED,
                PopupAnnouncement::STATUS_PUBLISHED,
            ])
            ->where(function ($query): void {
                $query
                    ->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        foreach ($dueAnnouncements as $announcement) {
            $processed++;
            $deliveriesCreated += $this->dispatchAnnouncement($announcement);
        }

        return [
            'enabled' => true,
            'announcements_processed' => $processed,
            'announcements_expired' => $announcementsExpired['announcements'],
            'deliveries_created' => $deliveriesCreated,
            'deliveries_expired' => $announcementsExpired['deliveries'],
        ];
    }

    public function dispatchAnnouncement(PopupAnnouncement $announcement): int
    {
        if (! $announcement->isDueForDispatch()) {
            return 0;
        }

        $recipients = $this->resolveRecipients($announcement);

        if ($recipients->isEmpty()) {
            if ($announcement->status === PopupAnnouncement::STATUS_SCHEDULED) {
                $announcement->forceFill([
                    'status' => PopupAnnouncement::STATUS_PUBLISHED,
                    'published_at' => $announcement->published_at ?? now(),
                ])->save();
            }

            return 0;
        }

        $now = now();
        $rows = [];

        foreach ($recipients as $recipient) {
            $rows[] = [
                'popup_announcement_id' => $announcement->id,
                'user_id' => $recipient->id,
                'branch_id' => $this->resolveDeliveryBranchId($recipient, $announcement->target_branch_ids ?? []),
                'status' => PopupAnnouncementDelivery::STATUS_PENDING,
                'delivered_at' => $now,
                'display_count' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $created = PopupAnnouncementDelivery::query()->insertOrIgnore($rows);

        if ($announcement->status === PopupAnnouncement::STATUS_SCHEDULED) {
            $announcement->forceFill([
                'status' => PopupAnnouncement::STATUS_PUBLISHED,
                'published_at' => $announcement->published_at ?? $now,
            ])->save();
        }

        return $created;
    }

    /**
     * @return Collection<int, User>
     */
    protected function resolveRecipients(PopupAnnouncement $announcement): Collection
    {
        $roleNames = collect($announcement->target_role_names ?? [])
            ->filter(static fn (mixed $role): bool => is_string($role) && trim($role) !== '')
            ->map(static fn (string $role): string => trim($role))
            ->values()
            ->all();

        if ($roleNames === []) {
            return collect();
        }

        $targetBranchIds = collect($announcement->target_branch_ids ?? [])
            ->filter(static fn (mixed $branchId): bool => is_numeric($branchId) && (int) $branchId > 0)
            ->map(static fn (mixed $branchId): int => (int) $branchId)
            ->unique()
            ->values()
            ->all();

        $query = User::query()
            ->role($roleNames)
            ->select('users.*')
            ->distinct('users.id');

        if (Schema::hasColumn('users', 'status')) {
            $query->where('status', true);
        }

        return $query->get()
            ->filter(fn (User $user): bool => $this->canReceiveForBranches($user, $targetBranchIds))
            ->values();
    }

    /**
     * @param  array<int, int>  $targetBranchIds
     */
    protected function canReceiveForBranches(User $user, array $targetBranchIds): bool
    {
        if ($targetBranchIds === []) {
            return true;
        }

        if ($user->hasRole('Admin')) {
            return true;
        }

        $accessibleBranchIds = $user->accessibleBranchIds();

        return collect($targetBranchIds)
            ->contains(fn (int $branchId): bool => in_array($branchId, $accessibleBranchIds, true));
    }

    /**
     * @param  array<int, int|string>  $targetBranchIds
     */
    protected function resolveDeliveryBranchId(User $user, array $targetBranchIds): ?int
    {
        $normalizedTargetBranchIds = collect($targetBranchIds)
            ->filter(static fn (mixed $branchId): bool => is_numeric($branchId) && (int) $branchId > 0)
            ->map(static fn (mixed $branchId): int => (int) $branchId)
            ->values()
            ->all();

        if ($normalizedTargetBranchIds === []) {
            return $user->branch_id !== null ? (int) $user->branch_id : null;
        }

        if ($user->hasRole('Admin')) {
            return $normalizedTargetBranchIds[0] ?? null;
        }

        $accessibleBranchIds = $user->accessibleBranchIds();

        foreach ($normalizedTargetBranchIds as $branchId) {
            if (in_array($branchId, $accessibleBranchIds, true)) {
                return $branchId;
            }
        }

        return null;
    }

    /**
     * @return array{announcements: int, deliveries: int}
     */
    protected function expireEndedAnnouncements(): array
    {
        $expiredAnnouncementIds = PopupAnnouncement::query()
            ->whereIn('status', [
                PopupAnnouncement::STATUS_SCHEDULED,
                PopupAnnouncement::STATUS_PUBLISHED,
            ])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($expiredAnnouncementIds === []) {
            return ['announcements' => 0, 'deliveries' => 0];
        }

        $deliveries = PopupAnnouncementDelivery::query()
            ->whereIn('popup_announcement_id', $expiredAnnouncementIds)
            ->whereIn('status', [
                PopupAnnouncementDelivery::STATUS_PENDING,
                PopupAnnouncementDelivery::STATUS_SEEN,
            ])
            ->update([
                'status' => PopupAnnouncementDelivery::STATUS_EXPIRED,
                'expired_at' => now(),
                'updated_at' => now(),
            ]);

        $announcements = PopupAnnouncement::query()
            ->whereIn('id', $expiredAnnouncementIds)
            ->update([
                'status' => PopupAnnouncement::STATUS_EXPIRED,
                'updated_at' => now(),
            ]);

        return [
            'announcements' => $announcements,
            'deliveries' => $deliveries,
        ];
    }
}
