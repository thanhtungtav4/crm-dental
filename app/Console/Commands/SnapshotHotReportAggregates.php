<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Services\HotReportAggregateService;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SnapshotHotReportAggregates extends Command
{
    protected $signature = 'reports:snapshot-hot-aggregates
        {--date= : Ngày snapshot (Y-m-d)}
        {--branch_id= : Snapshot theo 1 chi nhánh}
        {--dry-run : Chỉ preview, không ghi DB}';

    protected $description = 'Snapshot pre-aggregation cho hot reports (revenue + care queue).';

    public function __construct(
        protected HotReportAggregateService $aggregateService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy snapshot hot report aggregates.',
        );

        $dryRun = (bool) $this->option('dry-run');
        $snapshotDate = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->startOfDay()
            : now()->startOfDay();
        $branchOption = $this->option('branch_id');

        $branchIds = [];

        if ($branchOption !== null) {
            $branchIds[] = (int) $branchOption;
        } else {
            $branchIds = Branch::query()->pluck('id')->all();
            array_unshift($branchIds, null);
        }

        $totalRevenueRows = 0;
        $totalCareRows = 0;

        foreach ($branchIds as $branchId) {
            if ($dryRun) {
                continue;
            }

            $summary = $this->aggregateService->snapshotForDate($snapshotDate, $branchId);
            $totalRevenueRows += (int) $summary['revenue_rows'];
            $totalCareRows += (int) $summary['care_rows'];
        }

        if (! $dryRun) {
            AuditLog::record(
                entityType: AuditLog::ENTITY_REPORT_SNAPSHOT,
                entityId: 0,
                action: AuditLog::ACTION_SNAPSHOT,
                actorId: auth()->id(),
                metadata: [
                    'snapshot_key' => 'hot_reports_daily',
                    'snapshot_date' => $snapshotDate->toDateString(),
                    'branch_count' => count($branchIds),
                    'revenue_rows' => $totalRevenueRows,
                    'care_rows' => $totalCareRows,
                    'branch_id' => $branchOption !== null ? (int) $branchOption : null,
                ],
            );
        }

        $this->line('HOT_REPORT_AGGREGATE_SNAPSHOT_DATE: '.$snapshotDate->toDateString());
        $this->line('HOT_REPORT_AGGREGATE_BRANCH_COUNT: '.count($branchIds));
        $this->line('HOT_REPORT_AGGREGATE_REVENUE_ROWS: '.($dryRun ? '-' : (string) $totalRevenueRows));
        $this->line('HOT_REPORT_AGGREGATE_CARE_ROWS: '.($dryRun ? '-' : (string) $totalCareRows));

        if ($dryRun) {
            $this->info('Dry-run completed.');
        }

        return self::SUCCESS;
    }
}
