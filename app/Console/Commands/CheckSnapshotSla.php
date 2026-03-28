<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\ReportAutomationBranchScopeResolver;
use App\Services\ReportSnapshotSlaService;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckSnapshotSla extends Command
{
    protected $signature = 'reports:check-snapshot-sla {--date= : Ngày snapshot cần kiểm tra (Y-m-d), mặc định hôm qua} {--key=operational_kpi_pack : Snapshot key} {--branch_id= : Branch id} {--dry-run : Chỉ preview, không ghi DB}';

    protected $description = 'Đánh giá SLA cho report snapshots (on_time/late/stale/missing).';

    public function __construct(
        protected ReportAutomationBranchScopeResolver $scopeResolver,
        protected ReportSnapshotSlaService $snapshotSla,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy kiểm tra SLA snapshots.',
        );

        $dryRun = (bool) $this->option('dry-run');
        $snapshotDate = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->toDateString()
            : now()->subDay()->toDateString();

        $snapshotKey = (string) $this->option('key');
        $requestedBranchId = $this->option('branch_id') !== null
            ? (int) $this->option('branch_id')
            : null;
        $authUser = auth()->user();
        $branchIds = $this->scopeResolver->resolveQueryBranchIds(
            $authUser instanceof \App\Models\User ? $authUser : null,
            $requestedBranchId,
        );
        $snapshotDateCarbon = Carbon::parse($snapshotDate);

        $onTime = 0;
        $late = 0;
        $stale = 0;
        $missing = 0;

        foreach ($branchIds ?? [null] as $branchId) {
            $metrics = $this->processBranchScope(
                snapshotKey: $snapshotKey,
                snapshotDate: $snapshotDateCarbon,
                branchId: $branchId,
                dryRun: $dryRun,
            );

            $onTime += $metrics['on_time'];
            $late += $metrics['late'];
            $stale += $metrics['stale'];
            $missing += $metrics['missing'];
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
                    'branch_id' => $requestedBranchId,
                    'branch_ids' => $branchIds,
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

    /**
     * @return array{on_time:int,late:int,stale:int,missing:int}
     */
    protected function processBranchScope(
        string $snapshotKey,
        Carbon $snapshotDate,
        ?int $branchId,
        bool $dryRun,
    ): array {
        return $this->snapshotSla->checkScope(
            snapshotKey: $snapshotKey,
            snapshotDate: $snapshotDate,
            branchId: $branchId,
            dryRun: $dryRun,
            actorId: auth()->id(),
        );
    }
}
