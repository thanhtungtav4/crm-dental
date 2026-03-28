<?php

namespace App\Services;

use App\Models\ReportSnapshot;
use Illuminate\Support\Collection;

class ReportSnapshotComparisonService
{
    /**
     * @return array{
     *     baseline:array{id:int,snapshot_key:string,snapshot_date:string|null,schema_version:string|null,payload_checksum:string|null,lineage_checksum:string|null},
     *     current:array{id:int,snapshot_key:string,snapshot_date:string|null,schema_version:string|null,payload_checksum:string|null,lineage_checksum:string|null},
     *     drift:array{status:string|null,details:array<string, mixed>},
     *     metrics:array<int, array{
     *         metric:string,
     *         baseline:string,
     *         current:string,
     *         delta:string,
     *         delta_percent:string
     *     }>
     * }
     */
    public function compare(ReportSnapshot $baseline, ReportSnapshot $current): array
    {
        return [
            'baseline' => $this->snapshotSummary($baseline),
            'current' => $this->snapshotSummary($current),
            'drift' => [
                'status' => $current->drift_status,
                'details' => is_array($current->drift_details) ? $current->drift_details : [],
            ],
            'metrics' => $this->buildMetricDiff((array) $baseline->payload, (array) $current->payload),
        ];
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
    public function buildMetricDiff(array $baselinePayload, array $currentPayload): array
    {
        return $this->metricKeys($baselinePayload, $currentPayload)
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
    public function snapshotSummary(ReportSnapshot $snapshot): array
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

    /**
     * @param  array<string, mixed>  $baselinePayload
     * @param  array<string, mixed>  $currentPayload
     * @return Collection<int, string>
     */
    protected function metricKeys(array $baselinePayload, array $currentPayload): Collection
    {
        return collect(array_merge(array_keys($baselinePayload), array_keys($currentPayload)))
            ->unique()
            ->sort()
            ->values();
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
