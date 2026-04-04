<?php

namespace App\Services;

use App\Models\ReportSnapshot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class OperationalKpiSnapshotReadModelService
{
    public function baseQuery(?array $branchIds = null): Builder
    {
        $query = ReportSnapshot::query()
            ->where('snapshot_key', 'operational_kpi_pack');

        if ($branchIds === null) {
            return $query;
        }

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('branch_scope_id', $branchIds);
    }

    public function latestSnapshotDate(?array $branchIds = null): string
    {
        if ($branchIds === []) {
            return now()->subDay()->toDateString();
        }

        $snapshotDate = $this->baseQuery($branchIds)->max('snapshot_date');

        return $snapshotDate
            ? Carbon::parse((string) $snapshotDate)->toDateString()
            : now()->subDay()->toDateString();
    }

    /**
     * @param  array<int, int>|null  $branchIds
     * @param  array<int, string>  $columns
     * @return Collection<int, ReportSnapshot>
     */
    public function snapshotsForDate(string $snapshotDate, ?array $branchIds = null, array $columns = ['*']): Collection
    {
        return $this->baseQuery($branchIds)
            ->whereDate('snapshot_date', $snapshotDate)
            ->get($columns);
    }

    public function slaViolationCountForDate(string $snapshotDate, ?array $branchIds = null): int
    {
        return $this->baseQuery($branchIds)
            ->whereDate('snapshot_date', $snapshotDate)
            ->whereIn('sla_status', [
                ReportSnapshot::SLA_LATE,
                ReportSnapshot::SLA_STALE,
                ReportSnapshot::SLA_MISSING,
            ])
            ->count();
    }

    /**
     * @param  array<int, int>|null  $branchIds
     * @param  array{from?:mixed,until?:mixed,status?:mixed,sla_status?:mixed}  $filters
     */
    public function latestSnapshot(?array $branchIds = null, array $filters = []): ?ReportSnapshot
    {
        $query = $this->baseQuery($branchIds);

        if (filled($filters['from'] ?? null)) {
            $query->whereDate('snapshot_date', '>=', $filters['from']);
        }

        if (filled($filters['until'] ?? null)) {
            $query->whereDate('snapshot_date', '<=', $filters['until']);
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', (string) $filters['status']);
        }

        if (filled($filters['sla_status'] ?? null)) {
            $query->where('sla_status', (string) $filters['sla_status']);
        }

        return $query
            ->latest('snapshot_date')
            ->latest('generated_at')
            ->first();
    }

    /**
     * @param  Collection<int, ReportSnapshot>  $snapshots
     * @param  array<int, int>  $visibleBranchIds
     * @return array{on_time:int,late:int,stale:int,missing:int}
     */
    public function snapshotCounts(Collection $snapshots, array $visibleBranchIds): array
    {
        return [
            'on_time' => $snapshots->where('sla_status', ReportSnapshot::SLA_ON_TIME)->count(),
            'late' => $snapshots->where('sla_status', ReportSnapshot::SLA_LATE)->count(),
            'stale' => $snapshots->where('sla_status', ReportSnapshot::SLA_STALE)->count(),
            'missing' => max(0, count($visibleBranchIds) - $snapshots->pluck('branch_scope_id')->unique()->count()),
        ];
    }

    /**
     * @param  array{on_time:int,late:int,stale:int,missing:int}  $counts
     * @return array<int, array{
     *     key:string,
     *     label:string,
     *     value:int,
     *     value_label:string,
     *     badge_label:string,
     *     badge_classes:string
     * }>
     */
    public function renderedSnapshotCountCards(array $counts): array
    {
        return [
            $this->snapshotCountCard('on_time', 'On Time', $counts['on_time'], 'success'),
            $this->snapshotCountCard('late', 'Late', $counts['late'], $counts['late'] > 0 ? 'warning' : 'success'),
            $this->snapshotCountCard('stale', 'Stale', $counts['stale'], $counts['stale'] > 0 ? 'danger' : 'success'),
            $this->snapshotCountCard('missing', 'Missing', $counts['missing'], $counts['missing'] > 0 ? 'danger' : 'success'),
        ];
    }

    /**
     * @param  array<int, int>|null  $branchIds
     * @return array{no_show_delta:float,acceptance_delta:float,chair_delta:float}|null
     */
    public function branchBenchmarkSummary(ReportSnapshot $snapshot, ?array $branchIds = null): ?array
    {
        if (! $snapshot->branch_id) {
            return null;
        }

        $peerSnapshots = $this->baseQuery($branchIds)
            ->whereDate('snapshot_date', $snapshot->snapshot_date)
            ->whereNotNull('branch_id')
            ->get(['id', 'payload']);

        if ($peerSnapshots->count() < 2) {
            return null;
        }

        $averageNoShow = round((float) $peerSnapshots->avg(fn (ReportSnapshot $record) => (float) data_get($record->payload, 'no_show_rate', 0)), 2);
        $averageAcceptance = round((float) $peerSnapshots->avg(fn (ReportSnapshot $record) => (float) data_get($record->payload, 'treatment_acceptance_rate', 0)), 2);
        $averageChair = round((float) $peerSnapshots->avg(fn (ReportSnapshot $record) => (float) data_get($record->payload, 'chair_utilization_rate', 0)), 2);

        $currentNoShow = (float) data_get($snapshot->payload, 'no_show_rate', 0);
        $currentAcceptance = (float) data_get($snapshot->payload, 'treatment_acceptance_rate', 0);
        $currentChair = (float) data_get($snapshot->payload, 'chair_utilization_rate', 0);

        return [
            'no_show_delta' => round($currentNoShow - $averageNoShow, 2),
            'acceptance_delta' => round($currentAcceptance - $averageAcceptance, 2),
            'chair_delta' => round($currentChair - $averageChair, 2),
        ];
    }

    protected function snapshotCountCard(string $key, string $label, int $value, string $tone): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'value_label' => number_format($value),
            'badge_label' => number_format($value),
            'badge_classes' => $this->toneBadgeClasses($tone),
        ];
    }

    protected function toneBadgeClasses(string $tone): string
    {
        return match ($tone) {
            'success' => 'border-success-200 bg-success-50 text-success-700 dark:border-success-900/60 dark:bg-success-950/30 dark:text-success-200',
            'warning' => 'border-warning-200 bg-warning-50 text-warning-700 dark:border-warning-900/60 dark:bg-warning-950/30 dark:text-warning-200',
            'danger' => 'border-danger-200 bg-danger-50 text-danger-700 dark:border-danger-900/60 dark:bg-danger-950/30 dark:text-danger-100',
            default => 'border-info-200 bg-info-50 text-info-700 dark:border-info-900/60 dark:bg-info-950/30 dark:text-info-200',
        };
    }
}
