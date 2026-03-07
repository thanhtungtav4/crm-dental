<?php

namespace App\Services;

use App\Models\ReportCareQueueDailyAggregate;
use App\Models\ReportRevenueDailyAggregate;
use App\Support\ClinicRuntimeSettings;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;

class HotReportAggregateReadinessService
{
    /**
     * @param  array<int, int>  $scopeIds
     */
    public function shouldUseRevenueAggregate(array $scopeIds, ?string $from, ?string $until): bool
    {
        return $this->aggregateRangeIsReady(
            ReportRevenueDailyAggregate::query(),
            $scopeIds,
            $from,
            $until,
        );
    }

    /**
     * @param  array<int, int>  $scopeIds
     */
    public function shouldUseCareAggregate(array $scopeIds, ?string $from, ?string $until): bool
    {
        return $this->aggregateRangeIsReady(
            ReportCareQueueDailyAggregate::query(),
            $scopeIds,
            $from,
            $until,
        );
    }

    /**
     * @param  array<int, int>  $scopeIds
     */
    protected function aggregateRangeIsReady(
        Builder $query,
        array $scopeIds,
        ?string $from,
        ?string $until,
    ): bool {
        $normalizedScopeIds = collect($scopeIds)
            ->map(static fn (mixed $scopeId): int => (int) $scopeId)
            ->unique()
            ->values()
            ->all();

        if ($normalizedScopeIds === [] || blank($from) || blank($until)) {
            return false;
        }

        $fromDate = Carbon::parse($from)->startOfDay();
        $untilDate = Carbon::parse($until)->startOfDay();

        if ($fromDate->gt($untilDate)) {
            return false;
        }

        $coverage = $query
            ->whereIn('branch_scope_id', $normalizedScopeIds)
            ->whereDate('snapshot_date', '>=', $fromDate->toDateString())
            ->whereDate('snapshot_date', '<=', $untilDate->toDateString())
            ->get(['branch_scope_id', 'snapshot_date', 'generated_at'])
            ->groupBy(function (object $row): string {
                return sprintf(
                    '%d|%s',
                    (int) $row->branch_scope_id,
                    Carbon::parse((string) $row->snapshot_date)->toDateString(),
                );
            });

        $todayString = now()->toDateString();
        $staleCutoff = now()->subHours(ClinicRuntimeSettings::reportSnapshotStaleAfterHours());

        foreach ($normalizedScopeIds as $scopeId) {
            foreach (CarbonPeriod::create($fromDate, $untilDate) as $date) {
                $dateString = $date->toDateString();
                $rowsForKey = $coverage->get(sprintf('%d|%s', $scopeId, $dateString));

                if ($rowsForKey === null || $rowsForKey->isEmpty()) {
                    return false;
                }

                if ($dateString !== $todayString) {
                    continue;
                }

                $latestGeneratedAt = $rowsForKey
                    ->map(fn (object $row): ?Carbon => filled($row->generated_at)
                        ? Carbon::parse((string) $row->generated_at)
                        : null)
                    ->filter()
                    ->sortDesc()
                    ->first();

                if (! $latestGeneratedAt instanceof Carbon || $latestGeneratedAt->lt($staleCutoff)) {
                    return false;
                }
            }
        }

        return true;
    }
}
