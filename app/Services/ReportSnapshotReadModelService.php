<?php

namespace App\Services;

use App\Models\ReportSnapshot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportSnapshotReadModelService
{
    public function baseQuery(string $snapshotKey, ?int $branchId = null): Builder
    {
        return ReportSnapshot::query()
            ->where('snapshot_key', $snapshotKey)
            ->when(
                $branchId === null,
                fn (Builder $query): Builder => $query->whereNull('branch_id'),
                fn (Builder $query): Builder => $query->where('branch_id', $branchId),
            );
    }

    public function findById(int $snapshotId): ?ReportSnapshot
    {
        return ReportSnapshot::query()->find($snapshotId);
    }

    /**
     * @return Collection<int, ReportSnapshot>
     */
    public function snapshotsForDate(string $snapshotKey, string $snapshotDate, ?int $branchId = null): Collection
    {
        return $this->baseQuery($snapshotKey, $branchId)
            ->whereDate('snapshot_date', $snapshotDate)
            ->get();
    }

    /**
     * @return Collection<int, ReportSnapshot>
     */
    public function snapshotsForDateAcrossBranches(string $snapshotKey, string $snapshotDate, ?int $branchId = null): Collection
    {
        return ReportSnapshot::query()
            ->where('snapshot_key', $snapshotKey)
            ->whereDate('snapshot_date', $snapshotDate)
            ->when($branchId !== null, fn (Builder $query): Builder => $query->where('branch_id', $branchId))
            ->get();
    }

    public function latestSuccessfulSnapshotForDate(string $snapshotKey, string $snapshotDate, ?int $branchId = null): ?ReportSnapshot
    {
        return $this->baseQuery($snapshotKey, $branchId)
            ->whereDate('snapshot_date', $snapshotDate)
            ->where('status', ReportSnapshot::STATUS_SUCCESS)
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->first();
    }

    public function previousSuccessfulSnapshot(?ReportSnapshot $current): ?ReportSnapshot
    {
        if (! $current) {
            return null;
        }

        return $this->baseQuery($current->snapshot_key, $current->branch_id)
            ->where('status', ReportSnapshot::STATUS_SUCCESS)
            ->where(function (Builder $query) use ($current): void {
                $query->whereDate('snapshot_date', '<', $current->snapshot_date?->toDateString())
                    ->orWhere(function (Builder $innerQuery) use ($current): void {
                        $innerQuery->whereDate('snapshot_date', $current->snapshot_date?->toDateString())
                            ->where('id', '<', $current->id);
                    });
            })
            ->orderByDesc('snapshot_date')
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->first();
    }
}
