<?php

namespace App\Console\Commands;

use App\Models\ReportSnapshot;
use App\Services\ReportSnapshotComparisonService;
use App\Services\ReportSnapshotReadModelService;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CompareReportSnapshots extends Command
{
    protected $signature = 'reports:compare-snapshots {left_snapshot_id? : Snapshot baseline id} {right_snapshot_id? : Snapshot hiện tại id} {--key=operational_kpi_pack : Snapshot key khi không truyền snapshot id} {--date= : Ngày snapshot hiện tại (Y-m-d)} {--branch_id= : Branch id} {--json : In kết quả dạng JSON}';

    protected $description = 'So sánh snapshot trước/sau để audit drift và thay đổi KPI.';

    public function __construct(
        protected ReportSnapshotReadModelService $reportSnapshots,
        protected ReportSnapshotComparisonService $snapshotComparison,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy so sánh report snapshots.',
        );

        [$leftSnapshot, $rightSnapshot] = $this->resolveSnapshots();

        if (! $leftSnapshot || ! $rightSnapshot) {
            $this->error('Không tìm thấy đủ 2 snapshot để so sánh.');

            return self::INVALID;
        }

        $comparison = $this->snapshotComparison->compare($leftSnapshot, $rightSnapshot);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($comparison, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info("Comparing snapshots #{$leftSnapshot->id} -> #{$rightSnapshot->id}");
        $this->line('Baseline: '.$leftSnapshot->snapshot_date?->toDateString().' | Current: '.$rightSnapshot->snapshot_date?->toDateString());
        $this->line('Schema: '.($leftSnapshot->schema_version ?? 'n/a').' -> '.($rightSnapshot->schema_version ?? 'n/a'));
        $this->line('Drift status: '.($rightSnapshot->drift_status ?? ReportSnapshot::DRIFT_UNKNOWN));

        $this->table(
            ['Metric', 'Baseline', 'Current', 'Delta', 'Delta %'],
            collect($comparison['metrics'])
                ->map(fn (array $row) => [
                    $row['metric'],
                    $row['baseline'],
                    $row['current'],
                    $row['delta'],
                    $row['delta_percent'],
                ])
                ->all(),
        );

        return self::SUCCESS;
    }

    /**
     * @return array{0:?ReportSnapshot,1:?ReportSnapshot}
     */
    protected function resolveSnapshots(): array
    {
        $leftId = $this->argument('left_snapshot_id');
        $rightId = $this->argument('right_snapshot_id');

        if ($leftId !== null && $rightId !== null) {
            return [
                $this->reportSnapshots->findById((int) $leftId),
                $this->reportSnapshots->findById((int) $rightId),
            ];
        }

        if ($leftId !== null) {
            $current = $this->reportSnapshots->findById((int) $leftId);

            return [
                $this->reportSnapshots->previousSuccessfulSnapshot($current),
                $current,
            ];
        }

        $snapshotKey = (string) $this->option('key');
        $snapshotDate = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->toDateString()
            : now()->toDateString();
        $branchId = $this->option('branch_id') !== null
            ? (int) $this->option('branch_id')
            : null;

        $current = $this->reportSnapshots
            ->latestSuccessfulSnapshotForDate($snapshotKey, $snapshotDate, $branchId);

        return [
            $this->reportSnapshots->previousSuccessfulSnapshot($current),
            $current,
        ];
    }
}
