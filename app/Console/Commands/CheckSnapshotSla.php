<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ReportSnapshot;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\ClinicRuntimeSettings;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckSnapshotSla extends Command
{
    protected $signature = 'reports:check-snapshot-sla {--date= : Ngày snapshot cần kiểm tra (Y-m-d)} {--key=operational_kpi_pack : Snapshot key} {--branch_id= : Branch id} {--dry-run : Chỉ preview, không ghi DB}';

    protected $description = 'Đánh giá SLA cho report snapshots (on_time/late/stale/missing).';

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy kiểm tra SLA snapshots.',
        );

        $dryRun = (bool) $this->option('dry-run');
        $snapshotDate = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->toDateString()
            : now()->toDateString();

        $snapshotKey = (string) $this->option('key');
        $branchId = $this->option('branch_id') !== null
            ? (int) $this->option('branch_id')
            : null;

        $staleCutoff = now()->subHours(ClinicRuntimeSettings::reportSnapshotStaleAfterHours());

        $query = ReportSnapshot::query()
            ->where('snapshot_key', $snapshotKey)
            ->whereDate('snapshot_date', $snapshotDate)
            ->when($branchId !== null, fn ($innerQuery) => $innerQuery->where('branch_id', $branchId));

        $snapshots = $query->get();

        $onTime = 0;
        $late = 0;
        $stale = 0;
        $missing = 0;

        if ($snapshots->isEmpty()) {
            $missing++;

            if (! $dryRun) {
                ReportSnapshot::query()->create([
                    'snapshot_key' => $snapshotKey,
                    'snapshot_date' => $snapshotDate,
                    'branch_id' => $branchId,
                    'status' => ReportSnapshot::STATUS_FAILED,
                    'sla_status' => ReportSnapshot::SLA_MISSING,
                    'generated_at' => null,
                    'sla_due_at' => Carbon::parse($snapshotDate)
                        ->endOfDay()
                        ->addHours(ClinicRuntimeSettings::reportSnapshotSlaHours()),
                    'payload' => [],
                    'lineage' => [
                        'generated_at' => null,
                        'branch_id' => $branchId,
                        'window' => [
                            'from' => Carbon::parse($snapshotDate)->startOfDay()->toDateTimeString(),
                            'to' => Carbon::parse($snapshotDate)->endOfDay()->toDateTimeString(),
                        ],
                        'sources' => [],
                    ],
                    'error_message' => 'Snapshot không tồn tại trong khoảng SLA.',
                    'created_by' => auth()->id(),
                ]);
            }
        }

        foreach ($snapshots as $snapshot) {
            $nextSlaStatus = ReportSnapshot::SLA_ON_TIME;

            if (! $snapshot->generated_at) {
                $nextSlaStatus = ReportSnapshot::SLA_MISSING;
                $missing++;
            } elseif ($snapshot->sla_due_at && $snapshot->generated_at->gt($snapshot->sla_due_at)) {
                $nextSlaStatus = ReportSnapshot::SLA_LATE;
                $late++;
            } elseif ($snapshot->generated_at->lt($staleCutoff) && $snapshot->snapshot_date?->isToday()) {
                $nextSlaStatus = ReportSnapshot::SLA_STALE;
                $stale++;
            } else {
                $onTime++;
            }

            if (! $dryRun) {
                $snapshot->sla_status = $nextSlaStatus;
                $snapshot->save();
            }
        }

        if (! $dryRun) {
            AuditLog::record(
                entityType: AuditLog::ENTITY_REPORT_SNAPSHOT,
                entityId: 0,
                action: AuditLog::ACTION_SLA_CHECK,
                actorId: auth()->id(),
                metadata: [
                    'snapshot_key' => $snapshotKey,
                    'snapshot_date' => $snapshotDate,
                    'branch_id' => $branchId,
                    'on_time' => $onTime,
                    'late' => $late,
                    'stale' => $stale,
                    'missing' => $missing,
                ],
            );
        }

        $mode = $dryRun ? 'DRY RUN' : 'APPLY';
        $this->info("[{$mode}] Snapshot SLA checked. on_time={$onTime}, late={$late}, stale={$stale}, missing={$missing}");

        return self::SUCCESS;
    }
}
