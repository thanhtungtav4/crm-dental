<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\OperationalKpiAlert;
use App\Models\ReportSnapshot;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class KpiScenarioSeeder extends Seeder
{
    public static function snapshotDate(): string
    {
        return now()->toDateString();
    }

    public function run(): void
    {
        $branches = Branch::query()
            ->whereIn('code', ['HCM-Q1', 'HN-CG', 'DN-HC'])
            ->get()
            ->keyBy('code');

        $admin = User::query()->where('email', 'admin@demo.nhakhoaanphuc.test')->first();
        $managerQ1 = User::query()->where('email', 'manager.q1@demo.nhakhoaanphuc.test')->first();
        $managerCg = User::query()->where('email', 'manager.cg@demo.nhakhoaanphuc.test')->first();

        if (
            ! $admin instanceof User
            || ! $managerQ1 instanceof User
            || ! $managerCg instanceof User
            || ! $branches->has('HCM-Q1')
            || ! $branches->has('HN-CG')
        ) {
            return;
        }

        $this->configureRuntimeSettings();

        $onTimeSnapshot = $this->seedOnTimeSnapshot(
            branch: $branches->get('HCM-Q1'),
            actor: $managerQ1,
        );

        $staleSnapshot = $this->seedStaleSnapshot(
            branch: $branches->get('HN-CG'),
            actor: $managerCg,
        );

        if ($onTimeSnapshot instanceof ReportSnapshot) {
            $this->seedOnTimeSnapshotAlerts($onTimeSnapshot, $managerQ1, $admin);
        }

        if ($staleSnapshot instanceof ReportSnapshot) {
            $this->seedStaleSnapshotAlerts($staleSnapshot, $managerCg);
        }
    }

    protected function configureRuntimeSettings(): void
    {
        ClinicSetting::setValue('report.snapshot_stale_after_hours', 24, [
            'group' => 'report',
            'label' => 'Ngưỡng stale snapshot (giờ)',
            'value_type' => 'integer',
            'is_active' => true,
        ]);
    }

    protected function seedOnTimeSnapshot(Branch $branch, User $actor): ?ReportSnapshot
    {
        return ReportSnapshot::query()->updateOrCreate(
            [
                'snapshot_key' => 'operational_kpi_pack',
                'snapshot_date' => self::snapshotDate(),
                'branch_scope_id' => $branch->id,
            ],
            [
                'branch_id' => $branch->id,
                'schema_version' => 'demo-local-v1',
                'status' => ReportSnapshot::STATUS_SUCCESS,
                'sla_status' => ReportSnapshot::SLA_ON_TIME,
                'generated_at' => now()->subMinutes(30),
                'sla_due_at' => now()->addHours(2),
                'payload' => [
                    'booking_count' => 8,
                    'no_show_rate' => 5.0,
                    'chair_utilization_rate' => 82.0,
                    'scenario' => 'on_time_branch_snapshot',
                ],
                'payload_checksum' => hash('sha256', 'demo-kpi-on-time'),
                'lineage_checksum' => hash('sha256', 'demo-kpi-lineage-on-time'),
                'drift_status' => ReportSnapshot::DRIFT_NONE,
                'drift_details' => [],
                'lineage' => [
                    'generated_at' => now()->subMinutes(30)->toIso8601String(),
                    'branch_id' => $branch->id,
                    'scenario' => 'on_time',
                ],
                'error_message' => null,
                'created_by' => $actor->id,
            ],
        );
    }

    protected function seedStaleSnapshot(Branch $branch, User $actor): ?ReportSnapshot
    {
        $generatedAt = now()->subHours(26);

        return ReportSnapshot::query()->updateOrCreate(
            [
                'snapshot_key' => 'operational_kpi_pack',
                'snapshot_date' => self::snapshotDate(),
                'branch_scope_id' => $branch->id,
            ],
            [
                'branch_id' => $branch->id,
                'schema_version' => 'demo-local-v1',
                'status' => ReportSnapshot::STATUS_SUCCESS,
                'sla_status' => ReportSnapshot::SLA_STALE,
                'generated_at' => $generatedAt,
                'sla_due_at' => now()->addHours(2),
                'payload' => [
                    'booking_count' => 4,
                    'no_show_rate' => 18.5,
                    'chair_utilization_rate' => 48.0,
                    'scenario' => 'stale_branch_snapshot',
                ],
                'payload_checksum' => hash('sha256', 'demo-kpi-stale'),
                'lineage_checksum' => hash('sha256', 'demo-kpi-lineage-stale'),
                'drift_status' => ReportSnapshot::DRIFT_UNKNOWN,
                'drift_details' => [
                    'reason' => 'seeded_stale_snapshot_for_local_qa',
                ],
                'lineage' => [
                    'generated_at' => $generatedAt->toIso8601String(),
                    'branch_id' => $branch->id,
                    'scenario' => 'stale',
                ],
                'error_message' => null,
                'created_by' => $actor->id,
            ],
        );
    }

    protected function seedOnTimeSnapshotAlerts(ReportSnapshot $snapshot, User $manager, User $admin): void
    {
        OperationalKpiAlert::query()->updateOrCreate(
            [
                'snapshot_id' => $snapshot->id,
                'metric_key' => 'production_efficiency',
            ],
            [
                'snapshot_key' => $snapshot->snapshot_key,
                'snapshot_date' => $snapshot->snapshot_date,
                'branch_id' => $snapshot->branch_id,
                'owner_user_id' => $manager->id,
                'threshold_direction' => 'min',
                'threshold_value' => 70,
                'observed_value' => 82,
                'severity' => 'low',
                'status' => OperationalKpiAlert::STATUS_RESOLVED,
                'title' => 'Hiệu suất ghế đã phục hồi',
                'message' => 'Scenario resolved để QA nhìn thấy lifecycle alert đầy đủ.',
                'metadata' => ['scenario' => 'resolved_alert'],
                'resolved_by' => $admin->id,
                'resolved_at' => now()->subHour(),
                'resolution_note' => 'Đã xử lý trong local demo seed.',
            ],
        );
    }

    protected function seedStaleSnapshotAlerts(ReportSnapshot $snapshot, User $manager): void
    {
        OperationalKpiAlert::query()->updateOrCreate(
            [
                'snapshot_id' => $snapshot->id,
                'metric_key' => 'no_show_rate',
            ],
            [
                'snapshot_key' => $snapshot->snapshot_key,
                'snapshot_date' => $snapshot->snapshot_date,
                'branch_id' => $snapshot->branch_id,
                'owner_user_id' => $manager->id,
                'threshold_direction' => 'max',
                'threshold_value' => 10,
                'observed_value' => 18.5,
                'severity' => 'high',
                'status' => OperationalKpiAlert::STATUS_NEW,
                'title' => 'No-show vượt ngưỡng',
                'message' => 'Scenario new alert để manager triage trong local QA.',
                'metadata' => ['scenario' => 'new_alert'],
            ],
        );

        OperationalKpiAlert::query()->updateOrCreate(
            [
                'snapshot_id' => $snapshot->id,
                'metric_key' => 'chair_utilization_rate',
            ],
            [
                'snapshot_key' => $snapshot->snapshot_key,
                'snapshot_date' => $snapshot->snapshot_date,
                'branch_id' => $snapshot->branch_id,
                'owner_user_id' => $manager->id,
                'threshold_direction' => 'min',
                'threshold_value' => 60,
                'observed_value' => 48,
                'severity' => 'medium',
                'status' => OperationalKpiAlert::STATUS_ACK,
                'title' => 'Công suất ghế thấp',
                'message' => 'Scenario ack alert để QA thấy trạng thái đã nhận việc.',
                'metadata' => ['scenario' => 'ack_alert'],
                'acknowledged_by' => $manager->id,
                'acknowledged_at' => Carbon::now()->subHour(),
            ],
        );
    }
}
