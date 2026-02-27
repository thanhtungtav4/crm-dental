<?php

namespace App\Services;

use App\Models\OperationalKpiAlert;
use App\Models\ReportSnapshot;
use App\Models\User;
use App\Support\ClinicRuntimeSettings;

class OperationalKpiAlertService
{
    /**
     * @return array{triggered:int,auto_resolved:int,active_alerts:int}
     */
    public function evaluateSnapshot(ReportSnapshot $snapshot, ?int $actorId = null): array
    {
        $payload = (array) $snapshot->payload;

        if ($snapshot->status !== ReportSnapshot::STATUS_SUCCESS || $payload === []) {
            return [
                'triggered' => 0,
                'auto_resolved' => 0,
                'active_alerts' => 0,
            ];
        }

        $definitions = $this->metricDefinitions();
        $triggeredMetricKeys = [];
        $triggeredCount = 0;

        foreach ($definitions as $definition) {
            $metricKey = $definition['metric_key'];
            $observedValue = round((float) data_get($payload, $metricKey, 0), 2);
            $thresholdValue = round((float) $definition['threshold_value'], 2);

            $isBreach = $definition['direction'] === 'max'
                ? $observedValue > $thresholdValue
                : $observedValue < $thresholdValue;

            if (! $isBreach) {
                continue;
            }

            $triggeredMetricKeys[] = $metricKey;
            $triggeredCount++;

            $alert = OperationalKpiAlert::query()->firstOrNew([
                'snapshot_id' => $snapshot->id,
                'metric_key' => $metricKey,
            ]);

            $shouldResetStatus = in_array($alert->status, [
                OperationalKpiAlert::STATUS_RESOLVED,
                null,
            ], true);

            $severity = $this->resolveSeverity(
                direction: $definition['direction'],
                thresholdValue: $thresholdValue,
                observedValue: $observedValue,
            );

            $alert->forceFill([
                'snapshot_key' => (string) $snapshot->snapshot_key,
                'snapshot_date' => $snapshot->snapshot_date,
                'branch_id' => $snapshot->branch_id,
                'owner_user_id' => $this->resolveOwnerUserId($snapshot->branch_id),
                'threshold_direction' => $definition['direction'],
                'threshold_value' => $thresholdValue,
                'observed_value' => $observedValue,
                'severity' => $severity,
                'status' => $shouldResetStatus
                    ? OperationalKpiAlert::STATUS_NEW
                    : $alert->status,
                'title' => $definition['title'],
                'message' => $this->buildMessage(
                    metricLabel: $definition['metric_label'],
                    direction: $definition['direction'],
                    thresholdValue: $thresholdValue,
                    observedValue: $observedValue,
                ),
                'metadata' => [
                    'metric_label' => $definition['metric_label'],
                    'window' => data_get($snapshot->lineage, 'window'),
                ],
                'acknowledged_by' => $shouldResetStatus ? null : $alert->acknowledged_by,
                'acknowledged_at' => $shouldResetStatus ? null : $alert->acknowledged_at,
                'resolved_by' => $shouldResetStatus ? null : $alert->resolved_by,
                'resolved_at' => $shouldResetStatus ? null : $alert->resolved_at,
                'resolution_note' => $shouldResetStatus ? null : $alert->resolution_note,
            ])->save();
        }

        $autoResolvedCount = 0;

        $alertsToResolve = OperationalKpiAlert::query()
            ->where('snapshot_id', $snapshot->id)
            ->when(
                $triggeredMetricKeys !== [],
                fn ($query) => $query->whereNotIn('metric_key', $triggeredMetricKeys),
            )
            ->whereIn('status', [
                OperationalKpiAlert::STATUS_NEW,
                OperationalKpiAlert::STATUS_ACK,
            ])
            ->get();

        foreach ($alertsToResolve as $alert) {
            $alert->forceFill([
                'status' => OperationalKpiAlert::STATUS_RESOLVED,
                'resolved_by' => $actorId,
                'resolved_at' => now(),
                'resolution_note' => 'Auto-resolved: metric đã trở lại trong ngưỡng.',
            ])->save();

            $autoResolvedCount++;
        }

        return [
            'triggered' => $triggeredCount,
            'auto_resolved' => $autoResolvedCount,
            'active_alerts' => OperationalKpiAlert::query()
                ->where('snapshot_id', $snapshot->id)
                ->whereIn('status', [OperationalKpiAlert::STATUS_NEW, OperationalKpiAlert::STATUS_ACK])
                ->count(),
        ];
    }

    /**
     * @return array<int, array{metric_key:string,metric_label:string,direction:string,threshold_value:float,title:string}>
     */
    protected function metricDefinitions(): array
    {
        return [
            [
                'metric_key' => 'no_show_rate',
                'metric_label' => 'No-show rate',
                'direction' => 'max',
                'threshold_value' => ClinicRuntimeSettings::kpiNoShowRateMaxThreshold(),
                'title' => 'No-show vượt ngưỡng',
            ],
            [
                'metric_key' => 'chair_utilization_rate',
                'metric_label' => 'Chair utilization',
                'direction' => 'min',
                'threshold_value' => ClinicRuntimeSettings::kpiChairUtilizationRateMinThreshold(),
                'title' => 'Chair utilization dưới ngưỡng',
            ],
            [
                'metric_key' => 'treatment_acceptance_rate',
                'metric_label' => 'Treatment acceptance',
                'direction' => 'min',
                'threshold_value' => ClinicRuntimeSettings::kpiTreatmentAcceptanceRateMinThreshold(),
                'title' => 'Treatment acceptance dưới ngưỡng',
            ],
        ];
    }

    protected function resolveOwnerUserId(?int $branchId): ?int
    {
        $branchManagerId = User::query()
            ->where('branch_id', $branchId)
            ->whereHas('roles', fn ($query) => $query->where('name', 'Manager'))
            ->value('id');

        if ($branchManagerId) {
            return (int) $branchManagerId;
        }

        $managerId = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'Manager'))
            ->value('id');

        if ($managerId) {
            return (int) $managerId;
        }

        $adminId = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'Admin'))
            ->value('id');

        return $adminId ? (int) $adminId : null;
    }

    protected function resolveSeverity(string $direction, float $thresholdValue, float $observedValue): string
    {
        $gap = $direction === 'max'
            ? max(0, $observedValue - $thresholdValue)
            : max(0, $thresholdValue - $observedValue);

        if ($gap >= 20) {
            return 'high';
        }

        if ($gap >= 8) {
            return 'medium';
        }

        return 'low';
    }

    protected function buildMessage(
        string $metricLabel,
        string $direction,
        float $thresholdValue,
        float $observedValue,
    ): string {
        $operatorText = $direction === 'max' ? 'vượt' : 'thấp hơn';

        return sprintf(
            '%s đang %s ngưỡng (observed %.2f%%, threshold %.2f%%).',
            $metricLabel,
            $operatorText,
            $observedValue,
            $thresholdValue,
        );
    }
}
