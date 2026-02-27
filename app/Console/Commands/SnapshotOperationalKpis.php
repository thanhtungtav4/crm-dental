<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\ReportSnapshot;
use App\Services\OperationalKpiAlertService;
use App\Services\OperationalKpiService;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\ClinicRuntimeSettings;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SnapshotOperationalKpis extends Command
{
    protected $signature = 'reports:snapshot-operational-kpis {--date= : Ngày snapshot (Y-m-d)} {--branch_id= : Snapshot theo 1 chi nhánh} {--key=operational_kpi_pack : Snapshot key} {--dry-run : Chỉ preview, không ghi DB}';

    protected $description = 'Chụp snapshot KPI vận hành kèm data lineage.';

    public function __construct(
        protected OperationalKpiService $kpiService,
        protected OperationalKpiAlertService $alertService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy automation snapshot KPI.',
        );

        $dryRun = (bool) $this->option('dry-run');
        $snapshotDate = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->startOfDay()
            : now()->startOfDay();

        $snapshotKey = (string) $this->option('key');
        $branchOption = $this->option('branch_id');

        $branchIds = [];

        if ($branchOption !== null) {
            $branchIds = [(int) $branchOption];
        } else {
            $branchIds = Branch::query()->pluck('id')->all();
            array_unshift($branchIds, null);
        }

        $from = $snapshotDate->copy()->startOfDay();
        $to = $snapshotDate->copy()->endOfDay();
        $slaDueAt = $snapshotDate
            ->copy()
            ->endOfDay()
            ->addHours(ClinicRuntimeSettings::reportSnapshotSlaHours());

        $created = 0;
        $failed = 0;

        foreach ($branchIds as $branchId) {
            try {
                $snapshot = $this->kpiService->buildSnapshot($from, $to, $branchId);

                if (! $dryRun) {
                    $snapshotRecord = $this->upsertSnapshot(
                        snapshotKey: $snapshotKey,
                        snapshotDate: $snapshotDate,
                        branchId: $branchId,
                        attributes: [
                            'status' => ReportSnapshot::STATUS_SUCCESS,
                            'sla_status' => ReportSnapshot::SLA_ON_TIME,
                            'generated_at' => now(),
                            'sla_due_at' => $slaDueAt,
                            'payload' => $snapshot['metrics'],
                            'lineage' => $snapshot['lineage'],
                            'error_message' => null,
                            'created_by' => auth()->id(),
                        ],
                    );

                    $this->alertService->evaluateSnapshot(
                        snapshot: $snapshotRecord,
                        actorId: auth()->id(),
                    );

                    AuditLog::record(
                        entityType: AuditLog::ENTITY_REPORT_SNAPSHOT,
                        entityId: (int) ($branchId ?? 0),
                        action: AuditLog::ACTION_SNAPSHOT,
                        actorId: auth()->id(),
                        metadata: [
                            'snapshot_key' => $snapshotKey,
                            'snapshot_date' => $snapshotDate->toDateString(),
                            'branch_id' => $branchId,
                        ],
                    );
                }

                $created++;
            } catch (\Throwable $throwable) {
                $failed++;

                if (! $dryRun) {
                    $this->upsertSnapshot(
                        snapshotKey: $snapshotKey,
                        snapshotDate: $snapshotDate,
                        branchId: $branchId,
                        attributes: [
                            'status' => ReportSnapshot::STATUS_FAILED,
                            'sla_status' => ReportSnapshot::SLA_LATE,
                            'generated_at' => null,
                            'sla_due_at' => $slaDueAt,
                            'payload' => [],
                            'lineage' => null,
                            'error_message' => $throwable->getMessage(),
                            'created_by' => auth()->id(),
                        ],
                    );
                }
            }
        }

        $mode = $dryRun ? 'DRY RUN' : 'APPLY';
        $this->info("[{$mode}] KPI snapshots processed. success={$created}, failed={$failed}, snapshot_date={$snapshotDate->toDateString()}");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function upsertSnapshot(
        string $snapshotKey,
        Carbon $snapshotDate,
        ?int $branchId,
        array $attributes,
    ): ReportSnapshot {
        $query = ReportSnapshot::query()
            ->where('snapshot_key', $snapshotKey)
            ->whereDate('snapshot_date', $snapshotDate->toDateString());

        if ($branchId === null) {
            $query->whereNull('branch_id');
        } else {
            $query->where('branch_id', $branchId);
        }

        $snapshot = $query
            ->latest('id')
            ->first();

        if (! $snapshot) {
            $snapshot = new ReportSnapshot([
                'snapshot_key' => $snapshotKey,
                'snapshot_date' => $snapshotDate->toDateString(),
                'branch_id' => $branchId,
            ]);
        }

        $snapshot->fill($attributes);
        $snapshot->save();

        return $snapshot;
    }
}
