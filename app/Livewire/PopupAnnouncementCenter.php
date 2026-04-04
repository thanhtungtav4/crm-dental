<?php

namespace App\Livewire;

use App\Services\PopupAnnouncementCenterReadModelService;
use App\Services\PopupAnnouncementCenterWorkflowService;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * @phpstan-type PopupAnnouncementCenterActiveState array{
 *     delivery_id:int,
 *     announcement:array<string, mixed>
 * }
 */
class PopupAnnouncementCenter extends Component
{
    protected PopupAnnouncementCenterWorkflowService $popupAnnouncementCenterWorkflowService;

    protected PopupAnnouncementCenterReadModelService $popupAnnouncementCenterReadModelService;

    public ?int $activeDeliveryId = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $activeAnnouncement = null;

    public int $pollingSeconds = 10;

    public function boot(
        PopupAnnouncementCenterWorkflowService $popupAnnouncementCenterWorkflowService,
        PopupAnnouncementCenterReadModelService $popupAnnouncementCenterReadModelService,
    ): void {
        $this->popupAnnouncementCenterWorkflowService = $popupAnnouncementCenterWorkflowService;
        $this->popupAnnouncementCenterReadModelService = $popupAnnouncementCenterReadModelService;
    }

    public function mount(): void
    {
        $this->pollingSeconds = ClinicRuntimeSettings::popupAnnouncementsPollingSeconds();
        $this->refreshPending();
    }

    public function refreshPending(): void
    {
        $this->transitionForCurrentUser(
            fn (int $userId, ?int $deliveryId): ?array => $this->popupAnnouncementCenterWorkflowService->refreshForUser($userId),
        );
    }

    public function acknowledge(): void
    {
        $this->transitionForActiveDelivery(
            fn (int $userId, int $deliveryId): ?array => $this->popupAnnouncementCenterWorkflowService
                ->acknowledgeForUser($userId, $deliveryId),
        );
    }

    public function dismiss(): void
    {
        $this->transitionForActiveDelivery(
            fn (int $userId, int $deliveryId): ?array => $this->popupAnnouncementCenterWorkflowService
                ->dismissForUser($userId, $deliveryId),
        );
    }

    public function render(): View
    {
        return view('livewire.popup-announcement-center');
    }

    #[Computed]
    public function viewState(): array
    {
        return $this->popupAnnouncementCenterReadModelService->centerViewState(
            $this->activeAnnouncement,
            $this->pollingSeconds,
        );
    }

    /** @param  PopupAnnouncementCenterActiveState|null  $activeState */
    protected function applyActiveState(?array $activeState): void
    {
        if ($activeState === null) {
            $this->resetActiveState();

            return;
        }

        $this->activeDeliveryId = $activeState['delivery_id'];
        $this->activeAnnouncement = $activeState['announcement'];
    }

    protected function resetActiveState(): void
    {
        $this->activeDeliveryId = null;
        $this->activeAnnouncement = null;
    }

    protected function activeUserId(): ?int
    {
        return auth()->id() !== null ? (int) auth()->id() : null;
    }

    /** @param  callable(int, int): (PopupAnnouncementCenterActiveState|null)  $resolver */
    protected function transitionForActiveDelivery(callable $resolver): void
    {
        $this->transitionForCurrentUser(
            fn (int $userId, ?int $deliveryId): ?array => $deliveryId === null
                ? null
                : $resolver($userId, $deliveryId),
            requiresActiveDelivery: true,
        );
    }

    /** @param  callable(int, ?int): (PopupAnnouncementCenterActiveState|null)  $resolver */
    protected function transitionForCurrentUser(callable $resolver, bool $requiresActiveDelivery = false): void
    {
        $userId = $this->activeUserId();

        if ($userId === null || ($requiresActiveDelivery && $this->activeDeliveryId === null)) {
            $this->resetActiveState();

            return;
        }

        $this->applyActiveState($resolver($userId, $this->activeDeliveryId));
    }
}
