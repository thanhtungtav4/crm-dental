<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\ReportSnapshot;
use App\Services\OperationalKpiAlertService;
use App\Services\OperationalKpiService;
use App\Services\ReportSnapshotLineageService;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\ClinicRuntimeSettings;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class SnapshotOperationalKpis extends Command
{
    protected $signature = 'reports:snapshot-operational-kpis {--date= : Ngày snapshot (Y-m-d)} {--branch_id= : Snapshot theo 1 chi nhánh} {--key=operational_kpi_pack : Snapshot key} {--dry-run : Chỉ preview, không ghi DB}';

    protected $description = 'Chụp snapshot KPI vận hành kèm data lineage.';

    public function __construct(
        protected OperationalKpiService $kpiService,
        protected OperationalKpiAlertService $alertService,
        protected ReportSnapshotLineageService $lineageService,
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
                $existingSnapshot = $this->findSnapshotForDate(
                    snapshotKey: $snapshotKey,
                    snapshotDate: $snapshotDate,
                    branchId: $branchId,
                );

                $enrichedSnapshot = $this->lineageService->enrich(
                    snapshotKey: $snapshotKey,
                    payload: (array) $snapshot['metrics'],
                    lineage: (array) $snapshot['lineage'],
                );
                $baselineSnapshot = $this->findBaselineSnapshot(
                    snapshotKey: $snapshotKey,
                    snapshotDate: $snapshotDate,
                    branchId: $branchId,
                    excludeSnapshotId: $existingSnapshot?->id,
                );
                $driftReport = $this->lineageService->detectDrift(
                    current: [
                        'schema_version' => $enrichedSnapshot['schema_version'],
                        'lineage' => $enrichedSnapshot['lineage'],
                        'payload_checksum' => $enrichedSnapshot['payload_checksum'],
                        'lineage_checksum' => $enrichedSnapshot['lineage_checksum'],
                    ],
                    baseline: $baselineSnapshot,
                );

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
                            'schema_version' => $enrichedSnapshot['schema_version'],
                            'payload' => $enrichedSnapshot['payload'],
                            'payload_checksum' => $enrichedSnapshot['payload_checksum'],
                            'lineage' => $enrichedSnapshot['lineage'],
                            'lineage_checksum' => $enrichedSnapshot['lineage_checksum'],
                            'drift_status' => $driftReport['drift_status'],
                            'drift_details' => $driftReport['drift_details'],
                            'compared_snapshot_id' => $baselineSnapshot?->id,
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
                            'schema_version' => $enrichedSnapshot['schema_version'],
                            'payload_checksum' => $enrichedSnapshot['payload_checksum'],
                            'lineage_checksum' => $enrichedSnapshot['lineage_checksum'],
                            'drift_status' => $driftReport['drift_status'],
                            'compared_snapshot_id' => $baselineSnapshot?->id,
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
                            'schema_version' => $this->lineageService->schemaVersionFor($snapshotKey),
                            'generated_at' => null,
                            'sla_due_at' => $slaDueAt,
                            'payload' => [],
                            'payload_checksum' => null,
                            'lineage' => null,
                            'lineage_checksum' => null,
                            'drift_status' => ReportSnapshot::DRIFT_UNKNOWN,
                            'drift_details' => [
                                'baseline_snapshot_id' => null,
                                'baseline_found' => false,
                            ],
                            'compared_snapshot_id' => null,
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
        $scopeId = $branchId ?? 0;
        $snapshotDateString = $snapshotDate->toDateString();

        return DB::transaction(function () use (
            $snapshotKey,
            $snapshotDateString,
            $scopeId,
            $branchId,
            $attributes
        ): ReportSnapshot {
            $query = ReportSnapshot::query()
                ->where('snapshot_key', $snapshotKey)
                ->where('branch_scope_id', $scopeId)
                ->whereDate('snapshot_date', $snapshotDateString);

            $snapshot = $query
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $snapshot) {
                $snapshot = new ReportSnapshot([
                    'snapshot_key' => $snapshotKey,
                    'snapshot_date' => $snapshotDateString,
                    'branch_scope_id' => $scopeId,
                    'branch_id' => $branchId,
                ]);
            }

            $snapshot->fill(array_merge($attributes, [
                'branch_id' => $branchId,
                'branch_scope_id' => $scopeId,
            ]));

            try {
                $snapshot->save();
            } catch (QueryException $exception) {
                $isUniqueViolation = str_contains((string) $exception->getCode(), '23000')
                    || str_contains(strtolower($exception->getMessage()), 'unique');

                if (! $isUniqueViolation) {
                    throw $exception;
                }

                $snapshot = ReportSnapshot::query()
                    ->where('snapshot_key', $snapshotKey)
                    ->where('branch_scope_id', $scopeId)
                    ->whereDate('snapshot_date', $snapshotDateString)
                    ->latest('id')
                    ->firstOrFail();

                $snapshot->fill(array_merge($attributes, [
                    'branch_id' => $branchId,
                    'branch_scope_id' => $scopeId,
                ]));
                $snapshot->save();
            }

            return $snapshot;
        }, 3);
    }

    protected function findSnapshotForDate(string $snapshotKey, Carbon $snapshotDate, ?int $branchId): ?ReportSnapshot
    {
        return $this->snapshotQuery($snapshotKey, $branchId)
            ->whereDate('snapshot_date', $snapshotDate->toDateString())
            ->latest('id')
            ->first();
    }

    protected function findBaselineSnapshot(
        string $snapshotKey,
        Carbon $snapshotDate,
        ?int $branchId,
        ?int $excludeSnapshotId = null,
    ): ?ReportSnapshot {
        return $this->snapshotQuery($snapshotKey, $branchId)
            ->where('status', ReportSnapshot::STATUS_SUCCESS)
            ->whereNotNull('schema_version')
            ->where(function (Builder $query) use ($snapshotDate, $excludeSnapshotId): void {
                $query->whereDate('snapshot_date', '<', $snapshotDate->toDateString())
                    ->orWhere(function (Builder $innerQuery) use ($snapshotDate, $excludeSnapshotId): void {
                        $innerQuery->whereDate('snapshot_date', $snapshotDate->toDateString());

                        if ($excludeSnapshotId !== null) {
                            $innerQuery->where('id', '!=', $excludeSnapshotId);
                        }
                    });
            })
            ->orderByDesc('snapshot_date')
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->first();
    }

    protected function snapshotQuery(string $snapshotKey, ?int $branchId): Builder
    {
        return ReportSnapshot::query()
            ->where('snapshot_key', $snapshotKey)
            ->where('branch_scope_id', $branchId ?? 0);
    }
}
