<?php

namespace App\Services;

use App\Models\PopupAnnouncement;
use App\Models\PopupAnnouncementDelivery;

class PopupAnnouncementDeliveryWorkflowService
{
    public function markSeen(PopupAnnouncementDelivery $delivery): PopupAnnouncementDelivery
    {
        if ($delivery->seen_at === null) {
            $delivery->markSeen();
        }

        return $delivery->fresh(['announcement']) ?? $delivery;
    }

    public function acknowledge(PopupAnnouncementDelivery $delivery): PopupAnnouncementDelivery
    {
        $delivery->markAcknowledged();

        return $delivery->fresh(['announcement']) ?? $delivery;
    }

    public function dismiss(PopupAnnouncementDelivery $delivery): PopupAnnouncementDelivery
    {
        if ($delivery->announcement instanceof PopupAnnouncement && $delivery->announcement->require_ack) {
            return $delivery;
        }

        $delivery->markDismissed();

        return $delivery->fresh(['announcement']) ?? $delivery;
    }

    /**
     * @param  array<int, int>  $announcementIds
     */
    public function expireActiveDeliveriesForAnnouncements(array $announcementIds): int
    {
        if ($announcementIds === []) {
            return 0;
        }

        return PopupAnnouncementDelivery::query()
            ->whereIn('popup_announcement_id', $announcementIds)
            ->whereIn('status', [
                PopupAnnouncementDelivery::STATUS_PENDING,
                PopupAnnouncementDelivery::STATUS_SEEN,
            ])
            ->update([
                'status' => PopupAnnouncementDelivery::STATUS_EXPIRED,
                'expired_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
