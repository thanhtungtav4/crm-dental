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
     * @return array{
     *   automation_pending:int,
     *   automation_retry_due:int,
     *   automation_dead:int,
     *   deliveries_retry_due:int,
     *   deliveries_terminal_failed:int,
     *   campaigns_running:int,
     *   campaigns_failed:int
     * }
     */
    public function summaryMetrics(?array $branchIds = null): array
    {
        return [
            'automation_pending' => $this->automationPendingCount($branchIds),
            'automation_retry_due' => $this->automationRetryDueCount($branchIds),
            'automation_dead' => $this->automationDeadCount(branchIds: $branchIds),
            'deliveries_retry_due' => $this->deliveryRetryDueCount($branchIds),
            'deliveries_terminal_failed' => $this->deliveryTerminalFailedCount($branchIds),
            'campaigns_running' => $this->runningCampaignCount($branchIds),
            'campaigns_failed' => $this->failedCampaignCount($branchIds),
        ];
    }

    /**
     * @return array<int, array{label:string,value:int,tone:string}>
     */
    public function summaryCards(?array $branchIds = null): array
    {
        $summary = $this->summaryMetrics($branchIds);

        return [
            [
                'label' => 'Automation pending',
                'value' => $summary['automation_pending'],
                'tone' => 'info',
            ],
            [
                'label' => 'Automation retry due',
                'value' => $summary['automation_retry_due'],
                'tone' => 'warning',
            ],
            [
                'label' => 'Automation dead-letter',
                'value' => $summary['automation_dead'],
                'tone' => 'danger',
            ],
            [
                'label' => 'Delivery retry due',
                'value' => $summary['deliveries_retry_due'],
                'tone' => 'warning',
            ],
            [
                'label' => 'Delivery terminal failed',
                'value' => $summary['deliveries_terminal_failed'],
                'tone' => 'danger',
            ],
            [
                'label' => 'Campaign failed',
                'value' => $summary['campaigns_failed'],
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

    public function runningCampaignCount(?array $branchIds = null): int
    {
        return $this->campaignQuery($branchIds)
            ->where('status', ZnsCampaign::STATUS_RUNNING)
            ->count();
    }

    /**
     * @return array<string, string>
     */
    public function automationProviderStatusOptions(?array $branchIds = null): array
    {
        return $this->automationEventQuery($branchIds)
            ->whereNotNull('provider_status_code')
            ->distinct()
            ->orderBy('provider_status_code')
            ->pluck('provider_status_code', 'provider_status_code')
            ->mapWithKeys(static fn (mixed $value, mixed $key): array => [(string) $key => (string) $value])
            ->all();
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
