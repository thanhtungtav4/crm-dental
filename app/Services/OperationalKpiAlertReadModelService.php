<?php

namespace App\Services;

use App\Models\OperationalKpiAlert;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OperationalKpiAlertReadModelService
{
    public function baseQuery(?array $branchIds = null): Builder
    {
        $query = OperationalKpiAlert::query()
            ->where('snapshot_key', 'operational_kpi_pack');

        if ($branchIds === null) {
            return $query;
        }

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('branch_id', $branchIds);
    }

    public function activeAlertCountForSnapshot(int $snapshotId): int
    {
        return OperationalKpiAlert::query()
            ->where('snapshot_id', $snapshotId)
            ->whereIn('status', $this->openStatuses())
            ->count();
    }

    public function openAlertCount(?array $branchIds = null, ?string $snapshotDate = null): int
    {
        $query = $this->baseQuery($branchIds)
            ->whereIn('status', $this->openStatuses());

        if (filled($snapshotDate)) {
            $query->whereDate('snapshot_date', $snapshotDate);
        }

        return $query->count();
    }

    public function resolvedAlertCount(string $snapshotDate, ?array $branchIds = null): int
    {
        return $this->baseQuery($branchIds)
            ->whereDate('snapshot_date', $snapshotDate)
            ->where('status', OperationalKpiAlert::STATUS_RESOLVED)
            ->count();
    }

    /**
     * @param  array<int, int>|null  $branchIds
     * @return Collection<int, OperationalKpiAlert>
     */
    public function openAlertsForDate(string $snapshotDate, ?array $branchIds = null, int $limit = 6): Collection
    {
        return $this->baseQuery($branchIds)
            ->with(['owner:id,email', 'branch:id,name'])
            ->whereDate('snapshot_date', $snapshotDate)
            ->whereIn('status', $this->openStatuses())
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array<int, int>|null  $branchIds
     * @return array{
     *     open_count:int,
     *     resolved_count:int,
     *     open_alerts:array<int, array{
     *         title:string,
     *         status:string,
     *         severity:string,
     *         branch:string,
     *         owner:string
     *     }>
     * }
     */
    public function opsSummaryForDate(string $snapshotDate, ?array $branchIds = null, int $limit = 6): array
    {
        $openAlerts = $this->openAlertsForDate($snapshotDate, $branchIds, $limit);

        return [
            'open_count' => $this->openAlertCount($branchIds, $snapshotDate),
            'resolved_count' => $this->resolvedAlertCount($snapshotDate, $branchIds),
            'open_alerts' => $openAlerts
                ->map(fn (OperationalKpiAlert $alert): array => [
                    'title' => $alert->title,
                    'status' => $alert->status,
                    'severity' => (string) $alert->severity,
                    'branch' => $alert->branch?->name ?: '-',
                    'owner' => $alert->owner?->email ?: 'Unassigned',
                ])
                ->all(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function openStatuses(): array
    {
        return [
            OperationalKpiAlert::STATUS_NEW,
            OperationalKpiAlert::STATUS_ACK,
        ];
    }
}
