<?php

namespace App\Support;

use Database\Seeders\OpsScenarioSeeder;

class OpsAutomationCatalog
{
    /**
     * @return array<int, string>
     */
    public static function trackedCommands(): array
    {
        return [
            'security:check-automation-actor',
            'ops:create-backup-artifact',
            'ops:check-backup-health',
            'ops:run-restore-drill',
            'ops:run-release-gates',
            'ops:run-production-readiness',
            'ops:verify-production-readiness-report',
            'ops:check-alert-runbook-map',
            'ops:check-observability-health',
            'reports:explain-ops-hotpaths',
            'integrations:revoke-rotated-secrets',
            'integrations:prune-operational-data',
            'reports:snapshot-operational-kpis',
            'reports:check-snapshot-sla',
            'reports:compare-snapshots',
            'reports:snapshot-hot-aggregates',
            'emr:sync-events',
            'emr:reconcile-integrity',
            'emr:reconcile-clinical-media',
            'emr:check-dicom-readiness',
            'emr:prune-clinical-media',
            'google-calendar:sync-events',
            'zns:sync-automation-events',
            'zns:prune-operational-data',
            'zns:run-campaigns',
            'popups:dispatch-due',
            'popups:prune',
            'photos:prune',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function trackedChannels(): array
    {
        return [
            'automation_actor_health',
            'backup_artifact',
            'release_gates',
            'production_readiness',
            'observability_health',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function smokeCommands(string $snapshotDate, string $readinessReportPath): array
    {
        return [
            'php artisan ops:check-backup-health --path="'.OpsScenarioSeeder::readyBackupPath().'" --strict',
            'php artisan ops:check-backup-health --path="'.OpsScenarioSeeder::failMissingManifestBackupPath().'" --strict',
            'php artisan ops:run-restore-drill --path="'.OpsScenarioSeeder::readyBackupPath().'" --strict',
            'php artisan ops:verify-production-readiness-report "'.$readinessReportPath.'" --qa=manager.q1@demo.ident.test --pm=admin@demo.ident.test --release-ref=REL-DEMO-OPS-001 --strict',
            'php artisan ops:check-observability-health --strict',
            'php artisan integrations:revoke-rotated-secrets --dry-run --strict',
            'php artisan integrations:prune-operational-data --dry-run --strict',
            'php artisan emr:reconcile-integrity --strict',
            'php artisan emr:reconcile-clinical-media --strict',
            'php artisan emr:check-dicom-readiness --strict',
            'php artisan emr:prune-clinical-media --dry-run --strict',
            'php artisan google-calendar:sync-events --dry-run --strict-exit',
            'php artisan reports:check-snapshot-sla --date='.$snapshotDate.' --dry-run',
            'php artisan reports:snapshot-hot-aggregates --date='.$snapshotDate.' --dry-run',
            'php artisan zns:sync-automation-events --dry-run --strict-exit',
            'php artisan zns:prune-operational-data --dry-run --strict',
        ];
    }

    /**
     * @return array<int, array{
     *     target:string,
     *     arguments:array<int, string>,
     *     cadence:string,
     *     value:int|string|null
     * }>
     */
    public static function scheduledAutomationDefinitions(): array
    {
        return [
            ['target' => 'care:generate-birthday-tickets', 'arguments' => [], 'cadence' => 'dailyAt', 'value' => '00:05'],
            ['target' => 'care:generate-recall-tickets', 'arguments' => [], 'cadence' => 'dailyAt', 'value' => '00:10'],
            ['target' => 'reports:snapshot-operational-kpis', 'arguments' => [], 'cadence' => 'dailyAt', 'value' => '00:20'],
            ['target' => 'reports:snapshot-hot-aggregates', 'arguments' => [], 'cadence' => 'dailyAt', 'value' => '00:25'],
            ['target' => 'growth:run-loyalty-program', 'arguments' => [], 'cadence' => 'dailyAt', 'value' => '00:35'],
            ['target' => 'patients:score-risk', 'arguments' => [], 'cadence' => 'dailyAt', 'value' => '00:45'],
            ['target' => 'mpi:sync', 'arguments' => [], 'cadence' => 'dailyAt', 'value' => '01:30'],
            ['target' => 'finance:run-invoice-aging-reminders', 'arguments' => [], 'cadence' => 'dailyAt', 'value' => '08:00'],
            ['target' => 'care:run-plan-follow-up', 'arguments' => [], 'cadence' => 'dailyAt', 'value' => '09:00'],
            ['target' => 'growth:run-reactivation-flow', 'arguments' => [], 'cadence' => 'dailyAt', 'value' => '09:30'],
            ['target' => 'reports:check-snapshot-sla', 'arguments' => [], 'cadence' => 'dailyAt', 'value' => '10:00'],
            ['target' => 'ops:create-backup-artifact', 'arguments' => [], 'cadence' => 'dailyAt', 'value' => '01:50'],
            ['target' => 'ops:run-restore-drill', 'arguments' => [], 'cadence' => 'dailyAt', 'value' => '02:10'],
            ['target' => 'ops:check-alert-runbook-map', 'arguments' => [], 'cadence' => 'dailyAt', 'value' => '02:20'],
            ['target' => 'ops:check-observability-health', 'arguments' => ['--strict'], 'cadence' => 'dailyAt', 'value' => '02:25'],
            ['target' => 'emr:sync-events', 'arguments' => ['--strict-exit'], 'cadence' => 'hourlyAt', 'value' => 15],
            ['target' => 'emr:reconcile-integrity', 'arguments' => [], 'cadence' => 'hourlyAt', 'value' => 40],
            ['target' => 'emr:reconcile-clinical-media', 'arguments' => [], 'cadence' => 'hourlyAt', 'value' => 45],
            ['target' => 'google-calendar:sync-events', 'arguments' => ['--strict-exit'], 'cadence' => 'everyTenMinutes', 'value' => null],
            ['target' => 'integrations:revoke-rotated-secrets', 'arguments' => ['--strict'], 'cadence' => 'everyTenMinutes', 'value' => null],
            ['target' => 'zns:run-campaigns', 'arguments' => [], 'cadence' => 'everyTenMinutes', 'value' => null],
            ['target' => 'zns:sync-automation-events', 'arguments' => ['--strict-exit'], 'cadence' => 'everyFiveMinutes', 'value' => null],
            ['target' => 'zns:prune-operational-data', 'arguments' => [], 'cadence' => 'dailyAt', 'value' => '03:40'],
            ['target' => 'integrations:prune-operational-data', 'arguments' => [], 'cadence' => 'dailyAt', 'value' => '03:50'],
            ['target' => 'popups:dispatch-due', 'arguments' => [], 'cadence' => 'everyMinute', 'value' => null],
            ['target' => 'popups:prune', 'arguments' => [], 'cadence' => 'dailyAt', 'value' => '03:30'],
            ['target' => 'photos:prune', 'arguments' => [], 'cadence' => 'dailyAt', 'value' => '03:10'],
            ['target' => 'emr:prune-clinical-media', 'arguments' => [], 'cadence' => 'dailyAt', 'value' => '03:20'],
            ['target' => 'appointments:run-no-show-recovery', 'arguments' => [], 'cadence' => 'hourlyAt', 'value' => 5],
            ['target' => 'invoices:sync-overdue-status', 'arguments' => [], 'cadence' => 'hourly', 'value' => null],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function scheduledAutomationTargets(): array
    {
        return collect(self::scheduledAutomationDefinitions())
            ->pluck('target')
            ->values()
            ->all();
    }
}
