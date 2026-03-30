<?php

namespace App\Livewire;

use App\Services\PopupAnnouncementCenterWorkflowService;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PopupAnnouncementCenter extends Component
{
    public ?int $activeDeliveryId = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $activeAnnouncement = null;

    public int $pollingSeconds = 10;

    public function mount(): void
    {
        $this->pollingSeconds = ClinicRuntimeSettings::popupAnnouncementsPollingSeconds();
        $this->refreshPending();
    }

    public function refreshPending(): void
    {
        $userId = $this->activeUserId();

        if ($userId === null) {
            $this->resetActiveState();

            return;
        }

        $this->applyActiveState(
            app(PopupAnnouncementCenterWorkflowService::class)->refreshForUser($userId),
        );
    }

    public function acknowledge(): void
    {
        $userId = $this->activeUserId();

        if ($userId === null || $this->activeDeliveryId === null) {
            $this->resetActiveState();

            return;
        }

        $this->applyActiveState(
            app(PopupAnnouncementCenterWorkflowService::class)
                ->acknowledgeForUser($userId, $this->activeDeliveryId),
        );
    }

    public function dismiss(): void
    {
        $userId = $this->activeUserId();

        if ($userId === null || $this->activeDeliveryId === null) {
            $this->resetActiveState();

            return;
        }

        $this->applyActiveState(
            app(PopupAnnouncementCenterWorkflowService::class)
                ->dismissForUser($userId, $this->activeDeliveryId),
        );
    }

    public function render(): View
    {
        return view('livewire.popup-announcement-center', [
            'pollingSeconds' => max(5, min(60, $this->pollingSeconds)),
        ]);
    }

    /**
     * @param  array{
     *     delivery_id:int,
     *     announcement:array<string, mixed>
     * }|null  $activeState
     */
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
        return auth()->check() ? (int) auth()->id() : null;
    }
}
