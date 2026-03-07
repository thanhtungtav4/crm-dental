<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

dataset('restrictedOpsCommands', [
    'release gates' => [
        'command' => 'ops:run-release-gates',
        'arguments' => [
            '--profile' => 'ci',
            '--dry-run' => true,
        ],
    ],
    'production readiness' => [
        'command' => 'ops:run-production-readiness',
        'arguments' => [
            '--dry-run' => true,
            '--report' => __DIR__.'/../../storage/app/testing/ops-auth/production-readiness-report.json',
        ],
    ],
    'create backup artifact' => [
        'command' => 'ops:create-backup-artifact',
        'arguments' => [
            '--path' => __DIR__.'/../../storage/app/testing/ops-auth/backups',
        ],
    ],
]);

it('denies manager from running restricted ops commands', function (string $command, array $arguments): void {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $this->actingAs($manager);

    expect(fn () => $this->artisan($command, $arguments))
        ->toThrow(ValidationException::class, 'Admin hoặc AutomationService');
})->with('restrictedOpsCommands');

it('records admin actor audit for release gate dry run', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin);

    $this->artisan('ops:run-release-gates', [
        '--profile' => 'ci',
        '--dry-run' => true,
    ])->assertSuccessful();

    $audit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_AUTOMATION)
        ->where('action', AuditLog::ACTION_RUN)
        ->where('metadata->command', 'ops:run-release-gates')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and((int) $audit->actor_id)->toBe($admin->id)
        ->and((bool) data_get($audit?->metadata, 'dry_run'))->toBeTrue();
});

it('records automation service actor audit for backup health command', function (): void {
    $actor = User::factory()->create();
    $actor->assignRole('AutomationService');

    $backupPath = storage_path('app/testing/ops-auth/backup-health');
    File::ensureDirectoryExists($backupPath);

    $backupFile = $backupPath.'/crm-backup-pass.sql';
    File::put($backupFile, '-- backup fixture');
    touch($backupFile, now()->subHour()->getTimestamp());

    $this->actingAs($actor);

    $this->artisan('ops:check-backup-health', [
        '--path' => $backupPath,
        '--max-age-hours' => 2,
        '--strict' => true,
    ])->assertSuccessful();

    $audit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_AUTOMATION)
        ->where('action', AuditLog::ACTION_RUN)
        ->where('metadata->command', 'ops:check-backup-health')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and((int) $audit->actor_id)->toBe($actor->id)
        ->and((string) data_get($audit?->metadata, 'status'))->toBe('healthy');
});

it('records admin actor audit for readiness signoff verification', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $reportPath = storage_path('app/testing/ops-auth/readiness-report.json');
    $signoffPath = storage_path('app/testing/ops-auth/readiness-signoff.json');
    File::ensureDirectoryExists(dirname($reportPath));

    File::put(
        $reportPath,
        json_encode(validOpsReadinessReport(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    );

    $this->actingAs($admin);

    $this->artisan('ops:verify-production-readiness-report', [
        'report' => $reportPath,
        '--qa' => 'qa.lead@clinic.test',
        '--pm' => 'pm.owner@clinic.test',
        '--release-ref' => 'REL-OPS-001',
        '--strict' => true,
        '--output' => $signoffPath,
    ])->assertSuccessful();

    $audit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_AUTOMATION)
        ->where('action', AuditLog::ACTION_APPROVE)
        ->where('metadata->command', 'ops:verify-production-readiness-report')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and((int) $audit->actor_id)->toBe($admin->id)
        ->and((string) data_get($audit?->metadata, 'status'))->toBe('pass');
});

/**
 * @return array<string, mixed>
 */
function validOpsReadinessReport(): array
{
    return [
        'profile' => 'production',
        'strict_full' => true,
        'dry_run' => false,
        'with_finance' => true,
        'run_tests' => true,
        'test_filter' => null,
        'started_at' => '2026-03-02 08:00:00',
        'finished_at' => '2026-03-02 08:10:00',
        'status' => 'pass',
        'duration_ms' => 600000,
        'steps_plan' => [
            [
                'name' => 'Release gates (production)',
                'command' => 'php artisan ops:run-release-gates --profile=production --with-finance',
                'timeout_seconds' => 1800,
            ],
            [
                'name' => 'Application test suite (full)',
                'command' => 'php artisan test',
                'timeout_seconds' => 7200,
            ],
        ],
        'steps_run' => [
            [
                'name' => 'Release gates (production)',
                'command' => 'php artisan ops:run-release-gates --profile=production --with-finance',
                'timeout_seconds' => 1800,
                'duration_ms' => 120000,
                'exit_code' => 0,
                'successful' => true,
                'output' => implode(PHP_EOL, [
                    'schema:assert-no-pending-migrations',
                    'schema:assert-critical-foreign-keys',
                    'security:assert-action-permission-baseline',
                    'emr:reconcile-clinical-media',
                    'reports:explain-ops-hotpaths',
                    'security:check-automation-actor',
                    'ops:check-backup-health',
                    'ops:run-restore-drill',
                    'ops:check-alert-runbook-map',
                    'ops:check-observability-health',
                    'emr:check-dicom-readiness',
                    'finance:reconcile-branch-attribution',
                ]),
                'error_output' => '',
            ],
            [
                'name' => 'Application test suite (full)',
                'command' => 'php artisan test',
                'timeout_seconds' => 7200,
                'duration_ms' => 480000,
                'exit_code' => 0,
                'successful' => true,
                'output' => 'tests ok',
                'error_output' => '',
            ],
        ],
        'failures' => [],
    ];
}
