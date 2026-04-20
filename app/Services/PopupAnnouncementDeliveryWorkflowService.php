<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PopupAnnouncement;
use App\Models\PopupAnnouncementDelivery;

class PopupAnnouncementDeliveryWorkflowService
{
    public function markSeen(PopupAnnouncementDelivery $delivery): PopupAnnouncementDelivery
    {
        if ($delivery->seen_at === null) {
            $delivery->markSeen();

            AuditLog::record(
                entityType: AuditLog::ENTITY_POPUP_ANNOUNCEMENT,
                entityId: (int) $delivery->popup_announcement_id,
                action: AuditLog::ACTION_UPDATE,
                actorId: $delivery->user_id,
                metadata: [
                    'delivery_id' => (int) $delivery->getKey(),
                    'user_id' => $delivery->user_id,
                    'trigger' => 'popup_seen',
                    'status_from' => PopupAnnouncementDelivery::STATUS_PENDING,
                    'status_to' => PopupAnnouncementDelivery::STATUS_SEEN,
                    'display_count' => (int) $delivery->display_count,
                ],
                branchId: $delivery->branch_id,
            );
        }

        return $delivery->fresh(['announcement']) ?? $delivery;
    }

    public function acknowledge(PopupAnnouncementDelivery $delivery): PopupAnnouncementDelivery
    {
        $statusFrom = $delivery->status;
        $delivery->markAcknowledged();

        AuditLog::record(
            entityType: AuditLog::ENTITY_POPUP_ANNOUNCEMENT,
            entityId: (int) $delivery->popup_announcement_id,
            action: AuditLog::ACTION_APPROVE,
            actorId: $delivery->user_id,
            metadata: [
                'delivery_id' => (int) $delivery->getKey(),
                'user_id' => $delivery->user_id,
                'trigger' => 'popup_acknowledged',
                'status_from' => $statusFrom,
                'status_to' => PopupAnnouncementDelivery::STATUS_ACKNOWLEDGED,
            ],
            branchId: $delivery->branch_id,
        );

        return $delivery->fresh(['announcement']) ?? $delivery;
    }

    public function dismiss(PopupAnnouncementDelivery $delivery): PopupAnnouncementDelivery
    {
        if ($delivery->announcement instanceof PopupAnnouncement && $delivery->announcement->require_ack) {
            return $delivery;
        }

        $statusFrom = $delivery->status;
        $delivery->markDismissed();

        AuditLog::record(
            entityType: AuditLog::ENTITY_POPUP_ANNOUNCEMENT,
            entityId: (int) $delivery->popup_announcement_id,
            action: AuditLog::ACTION_CANCEL,
            actorId: $delivery->user_id,
            metadata: [
                'delivery_id' => (int) $delivery->getKey(),
                'user_id' => $delivery->user_id,
                'trigger' => 'popup_dismissed',
                'status_from' => $statusFrom,
                'status_to' => PopupAnnouncementDelivery::STATUS_DISMISSED,
            ],
            branchId: $delivery->branch_id,
        );

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
