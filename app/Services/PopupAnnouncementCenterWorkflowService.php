<?php

namespace App\Services;

use App\Models\PopupAnnouncementDelivery;
use App\Support\ClinicRuntimeSettings;

class PopupAnnouncementCenterWorkflowService
{
    public function __construct(
        protected PopupAnnouncementCenterReadModelService $popupAnnouncementCenterReadModelService,
        protected PopupAnnouncementDeliveryWorkflowService $popupAnnouncementDeliveryWorkflowService,
    ) {}

    /**
     * @return array{
     *     delivery_id:int,
     *     announcement:array{
     *         code:?string,
     *         title:string,
     *         message:string,
     *         priority:?string,
     *         require_ack:bool,
     *         starts_at:?string,
     *         ends_at:?string
     *     }
     * }|null
     */
    public function refreshForUser(int $userId): ?array
    {
        if (! ClinicRuntimeSettings::popupAnnouncementsEnabled()) {
            return null;
        }

        $delivery = $this->popupAnnouncementCenterReadModelService->pendingDeliveryForUser($userId);
        if (! $delivery instanceof PopupAnnouncementDelivery) {
            return null;
        }

        $delivery = $this->popupAnnouncementDeliveryWorkflowService->markSeen($delivery);

        return $this->activeStateFromDelivery($delivery);
    }

    /**
     * @return array{
     *     delivery_id:int,
     *     announcement:array{
     *         code:?string,
     *         title:string,
     *         message:string,
     *         priority:?string,
     *         require_ack:bool,
     *         starts_at:?string,
     *         ends_at:?string
     *     }
     * }|null
     */
    public function acknowledgeForUser(int $userId, int $deliveryId): ?array
    {
        $delivery = $this->resolveActiveDeliveryForUser($userId, $deliveryId);
        if (! $delivery instanceof PopupAnnouncementDelivery) {
            return null;
        }

        $this->popupAnnouncementDeliveryWorkflowService->acknowledge($delivery);

        return $this->refreshForUser($userId);
    }

    /**
     * @return array{
     *     delivery_id:int,
     *     announcement:array{
     *         code:?string,
     *         title:string,
     *         message:string,
     *         priority:?string,
     *         require_ack:bool,
     *         starts_at:?string,
     *         ends_at:?string
     *     }
     * }|null
     */
    public function dismissForUser(int $userId, int $deliveryId): ?array
    {
        $delivery = $this->resolveActiveDeliveryForUser($userId, $deliveryId);
        if (! $delivery instanceof PopupAnnouncementDelivery) {
            return null;
        }

        $this->popupAnnouncementDeliveryWorkflowService->dismiss($delivery);

        return $this->refreshForUser($userId);
    }

    protected function resolveActiveDeliveryForUser(int $userId, int $deliveryId): ?PopupAnnouncementDelivery
    {
        return $this->popupAnnouncementCenterReadModelService->activeDeliveryForUser($userId, $deliveryId);
    }

    /**
     * @return array{
     *     delivery_id:int,
     *     announcement:array{
     *         code:?string,
     *         title:string,
     *         message:string,
     *         priority:?string,
     *         require_ack:bool,
     *         starts_at:?string,
     *         ends_at:?string
     *     }
     * }|null
     */
    protected function activeStateFromDelivery(PopupAnnouncementDelivery $delivery): ?array
    {
        $announcement = $this->popupAnnouncementCenterReadModelService->announcementPayload($delivery);
        if ($announcement === null) {
            return null;
        }

        return [
            'delivery_id' => $delivery->id,
            'announcement' => $announcement,
        ];
    }
}
