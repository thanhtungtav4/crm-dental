<?php

namespace App\Console\Commands;

use App\Models\ReportSnapshot;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class CompareReportSnapshots extends Command
{
    protected $signature = 'reports:compare-snapshots {left_snapshot_id? : Snapshot baseline id} {right_snapshot_id? : Snapshot hiện tại id} {--key=operational_kpi_pack : Snapshot key khi không truyền snapshot id} {--date= : Ngày snapshot hiện tại (Y-m-d)} {--branch_id= : Branch id} {--json : In kết quả dạng JSON}';

    protected $description = 'So sánh snapshot trước/sau để audit drift và thay đổi KPI.';

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

        $comparison = [
            'baseline' => $this->snapshotSummary($leftSnapshot),
            'current' => $this->snapshotSummary($rightSnapshot),
            'drift' => [
                'status' => $rightSnapshot->drift_status,
                'details' => is_array($rightSnapshot->drift_details) ? $rightSnapshot->drift_details : [],
            ],
            'metrics' => $this->buildMetricDiff((array) $leftSnapshot->payload, (array) $rightSnapshot->payload),
        ];

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
                ReportSnapshot::query()->find((int) $leftId),
                ReportSnapshot::query()->find((int) $rightId),
            ];
        }

        if ($leftId !== null) {
            $current = ReportSnapshot::query()->find((int) $leftId);

            return [
                $this->findPreviousSnapshot($current),
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

        $current = $this->snapshotQuery($snapshotKey, $branchId)
            ->whereDate('snapshot_date', $snapshotDate)
            ->where('status', ReportSnapshot::STATUS_SUCCESS)
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->first();

        return [
            $this->findPreviousSnapshot($current),
            $current,
        ];
    }

    protected function findPreviousSnapshot(?ReportSnapshot $current): ?ReportSnapshot
    {
        if (! $current) {
            return null;
        }

        return $this->snapshotQuery($current->snapshot_key, $current->branch_id)
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

    protected function snapshotQuery(string $snapshotKey, ?int $branchId): Builder
    {
        return ReportSnapshot::query()
            ->where('snapshot_key', $snapshotKey)
            ->when(
                $branchId === null,
                fn (Builder $query): Builder => $query->whereNull('branch_id'),
                fn (Builder $query): Builder => $query->where('branch_id', $branchId),
            );
    }

    /**
     * @param  array<string, mixed>  $baselinePayload
     * @param  array<string, mixed>  $currentPayload
     * @return array<int, array{
     *     metric:string,
     *     baseline:string,
     *     current:string,
     *     delta:string,
     *     delta_percent:string
     * }>
     */
    protected function buildMetricDiff(array $baselinePayload, array $currentPayload): array
    {
        $keys = collect(array_merge(array_keys($baselinePayload), array_keys($currentPayload)))
            ->unique()
            ->sort()
            ->values();

        return $keys
            ->map(function (string $metric) use ($baselinePayload, $currentPayload): array {
                $baselineValue = data_get($baselinePayload, $metric);
                $currentValue = data_get($currentPayload, $metric);

                if ($this->isNumericValue($baselineValue) && $this->isNumericValue($currentValue)) {
                    $baselineNumber = (float) $baselineValue;
                    $currentNumber = (float) $currentValue;
                    $delta = round($currentNumber - $baselineNumber, 2);
                    $deltaPercent = $baselineNumber !== 0.0
                        ? round(($delta / abs($baselineNumber)) * 100, 2)
                        : null;

                    return [
                        'metric' => $metric,
                        'baseline' => number_format($baselineNumber, 2, '.', ''),
                        'current' => number_format($currentNumber, 2, '.', ''),
                        'delta' => number_format($delta, 2, '.', ''),
                        'delta_percent' => $deltaPercent === null ? '-' : number_format($deltaPercent, 2, '.', '').'%',
                    ];
                }

                $changed = $baselineValue !== $currentValue;

                return [
                    'metric' => $metric,
                    'baseline' => $this->stringifyValue($baselineValue),
                    'current' => $this->stringifyValue($currentValue),
                    'delta' => $changed ? 'changed' : 'unchanged',
                    'delta_percent' => '-',
                ];
            })
            ->all();
    }

    /**
     * @return array{id:int,snapshot_key:string,snapshot_date:string|null,schema_version:string|null,payload_checksum:string|null,lineage_checksum:string|null}
     */
    protected function snapshotSummary(ReportSnapshot $snapshot): array
    {
        return [
            'id' => (int) $snapshot->id,
            'snapshot_key' => (string) $snapshot->snapshot_key,
            'snapshot_date' => $snapshot->snapshot_date?->toDateString(),
            'schema_version' => $snapshot->schema_version,
            'payload_checksum' => $snapshot->payload_checksum,
            'lineage_checksum' => $snapshot->lineage_checksum,
        ];
    }

    protected function isNumericValue(mixed $value): bool
    {
        return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value));
    }

    protected function stringifyValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? 'complex' : $encoded;
    }
}
