<?php

namespace App\Services;

use App\Models\ReportSnapshot;
use App\Support\ClinicRuntimeSettings;
use Carbon\Carbon;

class ReportSnapshotSlaService
{
    public function __construct(protected ReportSnapshotReadModelService $reportSnapshots) {}

    /**
     * @return array{on_time:int,late:int,stale:int,missing:int}
     */
    public function checkScope(
        string $snapshotKey,
        Carbon $snapshotDate,
        ?int $branchId = null,
        bool $dryRun = false,
        ?int $actorId = null,
    ): array {
        $snapshots = $this->reportSnapshots->snapshotsForDateAcrossBranches(
            $snapshotKey,
            $snapshotDate->toDateString(),
            $branchId,
        );

        if ($snapshots->isEmpty()) {
            if (! $dryRun) {
                ReportSnapshot::query()->create(
                    $this->missingSnapshotAttributes(
                        snapshotKey: $snapshotKey,
                        snapshotDate: $snapshotDate,
                        branchId: $branchId,
                        actorId: $actorId,
                    ),
                );
            }

            return [
                'on_time' => 0,
                'late' => 0,
                'stale' => 0,
                'missing' => 1,
            ];
        }

        $staleCutoff = now()->subHours(ClinicRuntimeSettings::reportSnapshotStaleAfterHours());
        $summary = [
            'on_time' => 0,
            'late' => 0,
            'stale' => 0,
            'missing' => 0,
        ];

        foreach ($snapshots as $snapshot) {
            $slaStatus = $this->resolveSlaStatus($snapshot, $staleCutoff);
            $summary[$slaStatus]++;

            if (! $dryRun) {
                $snapshot->sla_status = $slaStatus;
                $snapshot->save();
            }
        }

        return $summary;
    }

    public function resolveSlaStatus(ReportSnapshot $snapshot, Carbon $staleCutoff): string
    {
        if (! $snapshot->generated_at) {
            return ReportSnapshot::SLA_MISSING;
        }

        if ($snapshot->sla_due_at && $snapshot->generated_at->gt($snapshot->sla_due_at)) {
            return ReportSnapshot::SLA_LATE;
        }

        if ($snapshot->generated_at->lt($staleCutoff) && $snapshot->snapshot_date?->isToday()) {
            return ReportSnapshot::SLA_STALE;
        }

        return ReportSnapshot::SLA_ON_TIME;
    }

    /**
     * @return array<string, mixed>
     */
    public function missingSnapshotAttributes(
        string $snapshotKey,
        Carbon $snapshotDate,
        ?int $branchId = null,
        ?int $actorId = null,
    ): array {
        return [
            'snapshot_key' => $snapshotKey,
            'snapshot_date' => $snapshotDate->toDateString(),
            'branch_id' => $branchId,
            'status' => ReportSnapshot::STATUS_FAILED,
            'sla_status' => ReportSnapshot::SLA_MISSING,
            'generated_at' => null,
            'sla_due_at' => $snapshotDate
                ->copy()
                ->endOfDay()
                ->addHours(ClinicRuntimeSettings::reportSnapshotSlaHours()),
            'payload' => [],
            'lineage' => [
                'generated_at' => null,
                'branch_id' => $branchId,
                'window' => [
                    'from' => $snapshotDate->copy()->startOfDay()->toDateTimeString(),
                    'to' => $snapshotDate->copy()->endOfDay()->toDateTimeString(),
                ],
                'sources' => [],
            ],
            'error_message' => 'Snapshot không tồn tại trong khoảng SLA.',
            'created_by' => $actorId,
        ];
    }
}
