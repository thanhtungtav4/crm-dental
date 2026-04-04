<?php

namespace App\Services;

use App\Models\PopupAnnouncementDelivery;
use App\Support\ClinicRuntimeSettings;

/**
 * @phpstan-import-type PopupAnnouncementCenterPayload from PopupAnnouncementCenterReadModelService
 *
 * @phpstan-type PopupAnnouncementCenterActiveState array{
 *     delivery_id:int,
 *     announcement:PopupAnnouncementCenterPayload
 * }
 */
class PopupAnnouncementCenterWorkflowService
{
    public function __construct(
        protected PopupAnnouncementCenterReadModelService $popupAnnouncementCenterReadModelService,
        protected PopupAnnouncementDeliveryWorkflowService $popupAnnouncementDeliveryWorkflowService,
    ) {}

    /** @return PopupAnnouncementCenterActiveState|null */
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

    /** @return PopupAnnouncementCenterActiveState|null */
    public function acknowledgeForUser(int $userId, int $deliveryId): ?array
    {
        $delivery = $this->resolveActiveDeliveryForUser($userId, $deliveryId);
        if (! $delivery instanceof PopupAnnouncementDelivery) {
            return null;
        }

        $this->popupAnnouncementDeliveryWorkflowService->acknowledge($delivery);

        return $this->refreshForUser($userId);
    }

    /** @return PopupAnnouncementCenterActiveState|null */
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

    /** @return PopupAnnouncementCenterActiveState|null */
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
