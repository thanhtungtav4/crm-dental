<?php

namespace App\Livewire;

use App\Models\PopupAnnouncement;
use App\Models\PopupAnnouncementDelivery;
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
        if (! ClinicRuntimeSettings::popupAnnouncementsEnabled() || ! auth()->check()) {
            $this->resetActiveState();

            return;
        }

        $delivery = PopupAnnouncementDelivery::query()
            ->with('announcement')
            ->forUser((int) auth()->id())
            ->undone()
            ->whereHas('announcement', function ($query): void {
                $query
                    ->where('status', PopupAnnouncement::STATUS_PUBLISHED)
                    ->where(function ($nested): void {
                        $nested
                            ->whereNull('starts_at')
                            ->orWhere('starts_at', '<=', now());
                    })
                    ->where(function ($nested): void {
                        $nested
                            ->whereNull('ends_at')
                            ->orWhere('ends_at', '>', now());
                    });
            })
            ->orderBy('delivered_at')
            ->orderBy('id')
            ->first();

        if (! $delivery instanceof PopupAnnouncementDelivery || ! $delivery->announcement instanceof PopupAnnouncement) {
            $this->resetActiveState();

            return;
        }

        if ($delivery->seen_at === null) {
            $delivery->markSeen();
        }

        $announcement = $delivery->announcement;
        $this->activeDeliveryId = $delivery->id;
        $this->activeAnnouncement = [
            'code' => $announcement->code,
            'title' => $announcement->title,
            'message' => $announcement->message,
            'priority' => $announcement->priority,
            'require_ack' => (bool) $announcement->require_ack,
            'starts_at' => optional($announcement->starts_at)?->format('d/m/Y H:i'),
            'ends_at' => optional($announcement->ends_at)?->format('d/m/Y H:i'),
        ];
    }

    public function acknowledge(): void
    {
        $delivery = $this->resolveActiveDelivery();
        if (! $delivery instanceof PopupAnnouncementDelivery) {
            $this->resetActiveState();

            return;
        }

        $delivery->markAcknowledged();
        $this->refreshPending();
    }

    public function dismiss(): void
    {
        $delivery = $this->resolveActiveDelivery();
        if (! $delivery instanceof PopupAnnouncementDelivery) {
            $this->resetActiveState();

            return;
        }

        if ($delivery->announcement?->require_ack) {
            return;
        }

        $delivery->markDismissed();
        $this->refreshPending();
    }

    public function render(): View
    {
        return view('livewire.popup-announcement-center', [
            'pollingSeconds' => max(5, min(60, $this->pollingSeconds)),
        ]);
    }

    protected function resolveActiveDelivery(): ?PopupAnnouncementDelivery
    {
        if (! auth()->check() || $this->activeDeliveryId === null) {
            return null;
        }

        $delivery = PopupAnnouncementDelivery::query()
            ->with('announcement')
            ->forUser((int) auth()->id())
            ->whereKey($this->activeDeliveryId)
            ->first();

        return $delivery instanceof PopupAnnouncementDelivery ? $delivery : null;
    }

    protected function resetActiveState(): void
    {
        $this->activeDeliveryId = null;
        $this->activeAnnouncement = null;
    }
}
