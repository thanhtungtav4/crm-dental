<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Services\BackupArtifactService;
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
    'restore drill' => [
        'command' => 'ops:run-restore-drill',
        'arguments' => [
            '--path' => __DIR__.'/../../storage/app/testing/ops-auth/restore-backups',
            '--target' => __DIR__.'/../../storage/app/testing/ops-auth/restore-artifacts',
        ],
    ],
    'observability health' => [
        'command' => 'ops:check-observability-health',
        'arguments' => [
            '--window-hours' => 1,
            '--strict' => true,
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
    provisionOpsEncryptedBackupArtifact($backupPath, 'ops-auth-backup-fixture');

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

function provisionOpsEncryptedBackupArtifact(string $backupPath, string $payload): void
{
    $sourceDirectory = $backupPath.'/source';
    $databasePath = $sourceDirectory.'/database.sqlite';
    $connectionName = 'sqlite_ops_auth_'.str()->random(10);

    File::deleteDirectory($backupPath);
    File::ensureDirectoryExists($sourceDirectory);
    File::put($databasePath, $payload);

    config()->set('database.connections.'.$connectionName, [
        'driver' => 'sqlite',
        'database' => $databasePath,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    app(BackupArtifactService::class)->create(
        connection: config('database.connections.'.$connectionName),
        connectionName: $connectionName,
        backupPath: $backupPath,
    );
}

it('records admin actor audit for readiness signoff verification', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $qaSigner = User::factory()->create([
        'email' => 'qa.ops@clinic.test',
        'name' => 'QA Ops',
    ]);
    $qaSigner->assignRole('Manager');
    $pmSigner = User::factory()->create([
        'email' => 'pm.ops@clinic.test',
        'name' => 'PM Ops',
    ]);
    $pmSigner->assignRole('Admin');

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
        '--qa' => $qaSigner->email,
        '--pm' => $pmSigner->email,
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
        ->and((string) data_get($audit?->metadata, 'status'))->toBe('pass')
        ->and((int) data_get($audit?->metadata, 'qa_signer.user_id'))->toBe($qaSigner->id)
        ->and((int) data_get($audit?->metadata, 'pm_signer.user_id'))->toBe($pmSigner->id);
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
