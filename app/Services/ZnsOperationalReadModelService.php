<?php

namespace App\Services;

use App\Models\ZnsAutomationEvent;
use App\Models\ZnsAutomationLog;
use App\Models\ZnsCampaign;
use App\Models\ZnsCampaignDelivery;
use Illuminate\Database\Eloquent\Builder;

class ZnsOperationalReadModelService
{
    /**
     * @return array<int, array{label:string,value:int,tone:string}>
     */
    public function summaryCards(): array
    {
        return [
            [
                'label' => 'Automation pending',
                'value' => $this->automationPendingCount(),
                'tone' => 'info',
            ],
            [
                'label' => 'Automation retry due',
                'value' => $this->automationRetryDueCount(),
                'tone' => 'warning',
            ],
            [
                'label' => 'Automation dead-letter',
                'value' => $this->automationDeadCount(),
                'tone' => 'danger',
            ],
            [
                'label' => 'Delivery retry due',
                'value' => $this->deliveryRetryDueCount(),
                'tone' => 'warning',
            ],
            [
                'label' => 'Delivery terminal failed',
                'value' => $this->deliveryTerminalFailedCount(),
                'tone' => 'danger',
            ],
            [
                'label' => 'Campaign failed',
                'value' => $this->failedCampaignCount(),
                'tone' => 'warning',
            ],
        ];
    }

    public function automationPendingCount(): int
    {
        return ZnsAutomationEvent::query()
            ->where('status', ZnsAutomationEvent::STATUS_PENDING)
            ->count();
    }

    public function automationRetryDueCount(): int
    {
        return ZnsAutomationEvent::query()
            ->where('status', ZnsAutomationEvent::STATUS_FAILED)
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->count();
    }

    public function automationDeadCount(): int
    {
        return ZnsAutomationEvent::query()
            ->where('status', ZnsAutomationEvent::STATUS_DEAD)
            ->count();
    }

    public function automationFailedCount(): int
    {
        return ZnsAutomationEvent::query()
            ->where('status', ZnsAutomationEvent::STATUS_FAILED)
            ->count();
    }

    public function deliveryRetryDueCount(): int
    {
        return ZnsCampaignDelivery::query()
            ->where('status', ZnsCampaignDelivery::STATUS_FAILED)
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->count();
    }

    public function deliveryTerminalFailedCount(): int
    {
        return ZnsCampaignDelivery::query()
            ->where('status', ZnsCampaignDelivery::STATUS_FAILED)
            ->whereNull('next_retry_at')
            ->count();
    }

    public function failedCampaignCount(): int
    {
        return ZnsCampaign::query()
            ->where('status', ZnsCampaign::STATUS_FAILED)
            ->count();
    }

    public function automationRetentionCandidateCount(int $retentionDays): int
    {
        $cutoff = now()->subDays($retentionDays);

        return ZnsAutomationEvent::query()
            ->whereIn('status', [
                ZnsAutomationEvent::STATUS_SENT,
                ZnsAutomationEvent::STATUS_DEAD,
            ])
            ->where(function (Builder $builder) use ($cutoff): void {
                $builder
                    ->where('processed_at', '<', $cutoff)
                    ->orWhere(function (Builder $fallbackQuery) use ($cutoff): void {
                        $fallbackQuery
                            ->whereNull('processed_at')
                            ->where('updated_at', '<', $cutoff);
                    });
            })
            ->count();
    }

    public function automationLogRetentionCandidateCount(int $retentionDays): int
    {
        return ZnsAutomationLog::query()
            ->where('attempted_at', '<', now()->subDays($retentionDays))
            ->count();
    }

    public function deliveryRetentionCandidateCount(int $retentionDays): int
    {
        $cutoff = now()->subDays($retentionDays);

        return ZnsCampaignDelivery::query()
            ->whereNull('processing_token')
            ->where(function (Builder $builder): void {
                $builder
                    ->whereIn('status', [
                        ZnsCampaignDelivery::STATUS_SENT,
                        ZnsCampaignDelivery::STATUS_SKIPPED,
                    ])
                    ->orWhere(function (Builder $failedQuery): void {
                        $failedQuery
                            ->where('status', ZnsCampaignDelivery::STATUS_FAILED)
                            ->whereNull('next_retry_at');
                    });
            })
            ->where(function (Builder $builder) use ($cutoff): void {
                $builder
                    ->where('sent_at', '<', $cutoff)
                    ->orWhere(function (Builder $fallbackQuery) use ($cutoff): void {
                        $fallbackQuery
                            ->whereNull('sent_at')
                            ->where('updated_at', '<', $cutoff);
                    });
            })
            ->count();
    }
}
