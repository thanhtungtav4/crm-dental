<?php

namespace App\Support;

use App\Console\Commands\RunReleaseGates;

class OpsReleaseGateCatalog
{
    /**
     * @return array<int, array{name:string, command:string, arguments:array<string, mixed>}>
     */
    public static function steps(string $profile, bool $withFinance, ?string $from = null, ?string $to = null): array
    {
        $steps = [
            ...self::baseSteps(),
        ];

        if (in_array($profile, [RunReleaseGates::PROFILE_OPS, RunReleaseGates::PROFILE_PRODUCTION], true)) {
            $steps[] = self::opsHotpathExplainStep();
        }

        if ($profile === RunReleaseGates::PROFILE_PRODUCTION) {
            $steps = [
                ...$steps,
                ...self::productionSteps(),
            ];
        }

        if ($withFinance) {
            $steps[] = self::financeReconciliationStep(
                from: $from ?: now()->startOfMonth()->toDateString(),
                to: $to ?: now()->toDateString(),
                strict: $profile === RunReleaseGates::PROFILE_PRODUCTION,
            );
        }

        return $steps;
    }

    /**
     * @return array<int, string>
     */
    public static function requiredProductionCommands(bool $withFinance): array
    {
        $commands = collect(self::steps(
            profile: RunReleaseGates::PROFILE_PRODUCTION,
            withFinance: $withFinance,
            from: '2026-01-01',
            to: '2026-01-31',
        ))
            ->pluck('command')
            ->values()
            ->all();

        return $commands;
    }

    /**
     * @return array<int, array{name:string, command:string, arguments:array<string, mixed>}>
     */
    protected static function baseSteps(): array
    {
        return [
            [
                'name' => 'Schema drift gate',
                'command' => 'schema:assert-no-pending-migrations',
                'arguments' => [],
            ],
            [
                'name' => 'Critical foreign key gate',
                'command' => 'schema:assert-critical-foreign-keys',
                'arguments' => [],
            ],
            [
                'name' => 'Action permission baseline gate',
                'command' => 'security:assert-action-permission-baseline',
                'arguments' => [],
            ],
            [
                'name' => 'Clinical media reconcile gate',
                'command' => 'emr:reconcile-clinical-media',
                'arguments' => ['--strict' => true],
            ],
        ];
    }

    /**
     * @return array{name:string, command:string, arguments:array<string, mixed>}
     */
    protected static function opsHotpathExplainStep(): array
    {
        return [
            'name' => 'Ops hot-path EXPLAIN gate',
            'command' => 'reports:explain-ops-hotpaths',
            'arguments' => ['--strict' => true],
        ];
    }

    /**
     * @return array<int, array{name:string, command:string, arguments:array<string, mixed>}>
     */
    protected static function productionSteps(): array
    {
        return [
            [
                'name' => 'Automation actor health gate',
                'command' => 'security:check-automation-actor',
                'arguments' => ['--strict' => true],
            ],
            [
                'name' => 'Backup health gate',
                'command' => 'ops:check-backup-health',
                'arguments' => ['--strict' => true],
            ],
            [
                'name' => 'Restore drill gate',
                'command' => 'ops:run-restore-drill',
                'arguments' => ['--strict' => true],
            ],
            [
                'name' => 'Alert runbook map gate',
                'command' => 'ops:check-alert-runbook-map',
                'arguments' => ['--strict' => true],
            ],
            [
                'name' => 'Cross-module observability gate',
                'command' => 'ops:check-observability-health',
                'arguments' => ['--strict' => true],
            ],
            [
                'name' => 'DICOM readiness gate (optional)',
                'command' => 'emr:check-dicom-readiness',
                'arguments' => ['--strict' => true],
            ],
        ];
    }

    /**
     * @return array{name:string, command:string, arguments:array<string, mixed>}
     */
    protected static function financeReconciliationStep(string $from, string $to, bool $strict): array
    {
        return [
            'name' => 'Finance branch attribution reconciliation gate',
            'command' => 'finance:reconcile-branch-attribution',
            'arguments' => [
                '--from' => $from,
                '--to' => $to,
                '--export' => storage_path('app/reconciliation/release-gate-finance-'.$from.'_'.$to.'.json'),
                '--strict' => $strict,
            ],
        ];
    }
}
