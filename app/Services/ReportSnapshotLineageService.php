<?php

namespace App\Services;

use App\Models\ReportSnapshot;
use Illuminate\Support\Arr;

class ReportSnapshotLineageService
{
    /**
     * @var array<string, array{schema_version:string,formula_version:string,source_version:string}>
     */
    protected const PROFILES = [
        'operational_kpi_pack' => [
            'schema_version' => 'operational_kpi_pack.v1',
            'formula_version' => 'operational_kpi_formula.v2',
            'source_version' => 'operational_kpi_sources.v1',
        ],
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $lineage
     * @return array{
     *     payload:array<string, mixed>,
     *     lineage:array<string, mixed>,
     *     schema_version:string,
     *     payload_checksum:string,
     *     lineage_checksum:string
     * }
     */
    public function enrich(string $snapshotKey, array $payload, array $lineage): array
    {
        $profile = $this->resolveProfile($snapshotKey);
        $normalizedPayload = $this->normalize($payload);
        $normalizedLineage = $this->normalize($lineage);
        $formulaVersion = (string) data_get($normalizedLineage, 'kpi_dictionary.version', $profile['formula_version']);

        $doctorBenchmarkFirst = collect((array) data_get($normalizedPayload, 'doctor_benchmark', []))
            ->first();

        $formulaSignature = $this->checksum([
            'formula_version' => $formulaVersion,
            'metric_keys' => $this->sortedArrayKeys($normalizedPayload),
            'doctor_benchmark_keys' => $this->sortedArrayKeys((array) ($doctorBenchmarkFirst ?? [])),
        ]);

        $sourceTables = collect((array) data_get($normalizedLineage, 'sources', []))
            ->map(fn ($source) => (string) data_get($source, 'table'))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $sourceSignature = $this->checksum([
            'source_version' => $profile['source_version'],
            'tables' => $sourceTables,
        ]);

        $normalizedLineage = array_merge($normalizedLineage, [
            'schema_version' => $profile['schema_version'],
            'formula_version' => $formulaVersion,
            'source_version' => $profile['source_version'],
            'formula_signature' => $formulaSignature,
            'source_signature' => $sourceSignature,
        ]);
        $normalizedLineage = $this->normalize($normalizedLineage);

        return [
            'payload' => $normalizedPayload,
            'lineage' => $normalizedLineage,
            'schema_version' => $profile['schema_version'],
            'payload_checksum' => $this->checksum($normalizedPayload),
            'lineage_checksum' => $this->checksum($normalizedLineage),
        ];
    }

    public function schemaVersionFor(string $snapshotKey): string
    {
        return $this->resolveProfile($snapshotKey)['schema_version'];
    }

    /**
     * @param  array{schema_version:string,lineage:array<string, mixed>,payload_checksum:string,lineage_checksum:string}  $current
     * @return array{drift_status:string,drift_details:array<string, mixed>}
     */
    public function detectDrift(array $current, ?ReportSnapshot $baseline): array
    {
        if (! $baseline) {
            return [
                'drift_status' => ReportSnapshot::DRIFT_UNKNOWN,
                'drift_details' => [
                    'baseline_snapshot_id' => null,
                    'baseline_found' => false,
                ],
            ];
        }

        $baselineLineage = is_array($baseline->lineage) ? $baseline->lineage : [];
        $schemaChanged = (string) ($baseline->schema_version ?? '') !== (string) $current['schema_version'];
        $formulaChanged = (string) data_get($baselineLineage, 'formula_signature') !== (string) data_get($current['lineage'], 'formula_signature');
        $sourceChanged = (string) data_get($baselineLineage, 'source_signature') !== (string) data_get($current['lineage'], 'source_signature');

        $driftStatus = ReportSnapshot::DRIFT_NONE;
        if ($schemaChanged) {
            $driftStatus = ReportSnapshot::DRIFT_SCHEMA_CHANGED;
        } elseif ($formulaChanged) {
            $driftStatus = ReportSnapshot::DRIFT_FORMULA_CHANGED;
        } elseif ($sourceChanged) {
            $driftStatus = ReportSnapshot::DRIFT_SOURCE_CHANGED;
        }

        return [
            'drift_status' => $driftStatus,
            'drift_details' => [
                'baseline_snapshot_id' => $baseline->id,
                'baseline_found' => true,
                'schema_changed' => $schemaChanged,
                'formula_changed' => $formulaChanged,
                'source_changed' => $sourceChanged,
                'payload_checksum_changed' => (string) $baseline->payload_checksum !== (string) $current['payload_checksum'],
                'lineage_checksum_changed' => (string) $baseline->lineage_checksum !== (string) $current['lineage_checksum'],
                'baseline_schema_version' => $baseline->schema_version,
                'current_schema_version' => $current['schema_version'],
                'baseline_formula_signature' => data_get($baselineLineage, 'formula_signature'),
                'current_formula_signature' => data_get($current['lineage'], 'formula_signature'),
                'baseline_source_signature' => data_get($baselineLineage, 'source_signature'),
                'current_source_signature' => data_get($current['lineage'], 'source_signature'),
            ],
        ];
    }

    public function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = $this->normalize($item);
        }

        if (Arr::isAssoc($normalized)) {
            ksort($normalized);
        }

        return $normalized;
    }

    public function checksum(mixed $value): string
    {
        $normalized = $this->normalize($value);
        $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $encoded === false ? serialize($normalized) : $encoded);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    protected function sortedArrayKeys(array $payload): array
    {
        $keys = array_keys($payload);
        sort($keys);

        return $keys;
    }

    /**
     * @return array{schema_version:string,formula_version:string,source_version:string}
     */
    protected function resolveProfile(string $snapshotKey): array
    {
        return self::PROFILES[$snapshotKey] ?? [
            'schema_version' => $snapshotKey.'.v1',
            'formula_version' => $snapshotKey.'.formula.v1',
            'source_version' => $snapshotKey.'.source.v1',
        ];
    }
}
