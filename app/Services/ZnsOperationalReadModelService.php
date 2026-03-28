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
    public function summaryCards(?array $branchIds = null): array
    {
        return [
            [
                'label' => 'Automation pending',
                'value' => $this->automationPendingCount($branchIds),
                'tone' => 'info',
            ],
            [
                'label' => 'Automation retry due',
                'value' => $this->automationRetryDueCount($branchIds),
                'tone' => 'warning',
            ],
            [
                'label' => 'Automation dead-letter',
                'value' => $this->automationDeadCount(branchIds: $branchIds),
                'tone' => 'danger',
            ],
            [
                'label' => 'Delivery retry due',
                'value' => $this->deliveryRetryDueCount($branchIds),
                'tone' => 'warning',
            ],
            [
                'label' => 'Delivery terminal failed',
                'value' => $this->deliveryTerminalFailedCount($branchIds),
                'tone' => 'danger',
            ],
            [
                'label' => 'Campaign failed',
                'value' => $this->failedCampaignCount($branchIds),
                'tone' => 'warning',
            ],
        ];
    }

    public function automationPendingCount(?array $branchIds = null): int
    {
        return $this->automationEventQuery($branchIds)
            ->where('status', ZnsAutomationEvent::STATUS_PENDING)
            ->count();
    }

    public function automationRetryDueCount(?array $branchIds = null): int
    {
        return $this->automationEventQuery($branchIds)
            ->where('status', ZnsAutomationEvent::STATUS_FAILED)
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->count();
    }

    public function automationDeadCount(?string $eventType = null, ?array $branchIds = null): int
    {
        return $this->automationEventQuery($branchIds)
            ->when(filled($eventType), fn (Builder $query) => $query->where('event_type', $eventType))
            ->where('status', ZnsAutomationEvent::STATUS_DEAD)
            ->count();
    }

    public function automationFailedCount(?array $branchIds = null): int
    {
        return $this->automationEventQuery($branchIds)
            ->where('status', ZnsAutomationEvent::STATUS_FAILED)
            ->count();
    }

    public function deliveryRetryDueCount(?array $branchIds = null): int
    {
        return $this->deliveryQuery($branchIds)
            ->where('status', ZnsCampaignDelivery::STATUS_FAILED)
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->count();
    }

    public function deliveryTerminalFailedCount(?array $branchIds = null): int
    {
        return $this->deliveryQuery($branchIds)
            ->where('status', ZnsCampaignDelivery::STATUS_FAILED)
            ->whereNull('next_retry_at')
            ->count();
    }

    public function failedCampaignCount(?array $branchIds = null): int
    {
        return $this->campaignQuery($branchIds)
            ->where('status', ZnsCampaign::STATUS_FAILED)
            ->count();
    }

    public function automationRetentionCandidateCount(int $retentionDays): int
    {
        return $this->automationRetentionQuery($retentionDays)
            ->count();
    }

    public function automationLogRetentionCandidateCount(int $retentionDays): int
    {
        return $this->automationLogRetentionQuery($retentionDays)
            ->count();
    }

    public function deliveryRetentionCandidateCount(int $retentionDays): int
    {
        return $this->deliveryRetentionQuery($retentionDays)
            ->count();
    }

    public function automationRetentionQuery(int $retentionDays): Builder
    {
        $cutoff = now()->subDays($retentionDays);

        return $this->automationEventQuery()
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
            });
    }

    public function automationLogRetentionQuery(int $retentionDays): Builder
    {
        return ZnsAutomationLog::query()
            ->where('attempted_at', '<', now()->subDays($retentionDays));
    }

    public function deliveryRetentionQuery(int $retentionDays): Builder
    {
        $cutoff = now()->subDays($retentionDays);

        return $this->deliveryQuery()
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
            });
    }

    protected function automationEventQuery(?array $branchIds = null): Builder
    {
        return $this->scopeBranches(ZnsAutomationEvent::query(), $branchIds);
    }

    protected function deliveryQuery(?array $branchIds = null): Builder
    {
        return $this->scopeBranches(ZnsCampaignDelivery::query(), $branchIds);
    }

    protected function campaignQuery(?array $branchIds = null): Builder
    {
        return $this->scopeBranches(ZnsCampaign::query(), $branchIds);
    }

    protected function scopeBranches(Builder $query, ?array $branchIds = null): Builder
    {
        if ($branchIds === null) {
            return $query;
        }

        $normalizedBranchIds = collect($branchIds)
            ->map(static fn (mixed $branchId): int => (int) $branchId)
            ->filter(static fn (int $branchId): bool => $branchId > 0)
            ->unique()
            ->values()
            ->all();

        if ($normalizedBranchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('branch_id', $normalizedBranchIds);
    }
}
