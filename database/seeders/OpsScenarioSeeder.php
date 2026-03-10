<?php

namespace Database\Seeders;

use App\Models\ClinicSetting;
use App\Models\User;
use App\Services\BackupArtifactManifestService;
use App\Services\BackupArtifactService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use PDO;

class OpsScenarioSeeder extends Seeder
{
    public const ROOT_DIRECTORY = 'app/demo/ops';

    public const READY_BACKUP_DIRECTORY = self::ROOT_DIRECTORY.'/backups/pass';

    public const FAIL_MISSING_MANIFEST_BACKUP_DIRECTORY = self::ROOT_DIRECTORY.'/backups/fail-missing-manifest';

    public const FAIL_CHECKSUM_BACKUP_DIRECTORY = self::ROOT_DIRECTORY.'/backups/fail-checksum';

    public const READINESS_DIRECTORY = self::ROOT_DIRECTORY.'/readiness';

    public const PASS_READINESS_REPORT = self::READINESS_DIRECTORY.'/production-readiness-pass.json';

    public const FAIL_STRICT_READINESS_REPORT = self::READINESS_DIRECTORY.'/production-readiness-strict-fail.json';

    public const INVALID_SCHEMA_READINESS_REPORT = self::READINESS_DIRECTORY.'/production-readiness-invalid-schema.json';

    public const SIGNOFF_DIRECTORY = self::ROOT_DIRECTORY.'/signoff';

    public const PASS_READINESS_SIGNOFF = self::SIGNOFF_DIRECTORY.'/production-readiness-pass-signoff.json';

    public function run(): void
    {
        $this->configureAutomationActor();
        $this->seedBackupScenarios();
        $this->seedReadinessReports();
    }

    public static function readyBackupPath(): string
    {
        return storage_path(self::READY_BACKUP_DIRECTORY);
    }

    public static function failMissingManifestBackupPath(): string
    {
        return storage_path(self::FAIL_MISSING_MANIFEST_BACKUP_DIRECTORY);
    }

    public static function failChecksumBackupPath(): string
    {
        return storage_path(self::FAIL_CHECKSUM_BACKUP_DIRECTORY);
    }

    public static function passReadinessReportPath(): string
    {
        return storage_path(self::PASS_READINESS_REPORT);
    }

    public static function failStrictReadinessReportPath(): string
    {
        return storage_path(self::FAIL_STRICT_READINESS_REPORT);
    }

    public static function invalidSchemaReadinessReportPath(): string
    {
        return storage_path(self::INVALID_SCHEMA_READINESS_REPORT);
    }

    public static function passReadinessSignoffPath(): string
    {
        return storage_path(self::PASS_READINESS_SIGNOFF);
    }

    protected function configureAutomationActor(): void
    {
        $actor = User::query()
            ->where('email', 'automation.bot@demo.ident.test')
            ->first();

        if (! $actor instanceof User) {
            return;
        }

        ClinicSetting::setValue('scheduler.automation_actor_user_id', $actor->id, [
            'group' => 'scheduler',
            'label' => 'Automation actor user ID',
            'value_type' => 'integer',
            'is_secret' => false,
            'is_active' => true,
            'sort_order' => 594,
            'description' => 'Tài khoản service account chạy scheduler automation.',
        ]);

        ClinicSetting::setValue('scheduler.automation_actor_required_role', 'AutomationService', [
            'group' => 'scheduler',
            'label' => 'Automation actor required role',
            'value_type' => 'text',
            'is_secret' => false,
            'is_active' => true,
            'sort_order' => 595,
            'description' => 'Role tối thiểu bắt buộc cho scheduler actor.',
        ]);
    }

    protected function seedBackupScenarios(): void
    {
        $this->provisionValidBackupArtifact(self::readyBackupPath(), 'ops_demo_pass');
        $this->provisionMissingManifestBackupArtifact(self::failMissingManifestBackupPath(), 'ops_demo_fail_missing_manifest');
        $this->provisionChecksumMismatchBackupArtifact(self::failChecksumBackupPath(), 'ops_demo_fail_checksum');
    }

    protected function seedReadinessReports(): void
    {
        $directory = dirname(self::passReadinessReportPath());
        File::deleteDirectory($directory);
        File::ensureDirectoryExists($directory);

        File::put(
            self::passReadinessReportPath(),
            json_encode($this->validReadinessReport(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        $strictFailReport = $this->validReadinessReport();
        $strictFailReport['strict_full'] = false;

        File::put(
            self::failStrictReadinessReportPath(),
            json_encode($strictFailReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        File::put(
            self::invalidSchemaReadinessReportPath(),
            json_encode(['status' => 'pass'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        File::ensureDirectoryExists(dirname(self::passReadinessSignoffPath()));
        File::put(
            self::passReadinessSignoffPath(),
            json_encode($this->passReadinessSignoff(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    protected function provisionValidBackupArtifact(string $backupPath, string $connectionName): void
    {
        File::deleteDirectory($backupPath);

        $connection = $this->buildSqliteFixtureConnection($backupPath.'/source', $connectionName);

        $this->backupArtifactService()->create(
            connection: $connection,
            connectionName: $connectionName,
            backupPath: $backupPath,
        );
    }

    protected function provisionMissingManifestBackupArtifact(string $backupPath, string $connectionName): void
    {
        $this->provisionValidBackupArtifact($backupPath, $connectionName);

        $latestManifest = $this->manifestService()->latest($backupPath);
        $manifestPath = is_array($latestManifest) ? ($latestManifest['path'] ?? null) : null;

        if (is_string($manifestPath)) {
            File::delete($manifestPath);
        }
    }

    protected function provisionChecksumMismatchBackupArtifact(string $backupPath, string $connectionName): void
    {
        $this->provisionValidBackupArtifact($backupPath, $connectionName);

        $latestManifest = $this->manifestService()->latest($backupPath);
        $manifestPath = is_array($latestManifest) ? ($latestManifest['path'] ?? null) : null;
        $manifest = is_array($latestManifest) ? ($latestManifest['data'] ?? null) : null;

        if (! is_string($manifestPath) || ! is_array($manifest)) {
            return;
        }

        $manifest['artifact_checksum_sha256'] = hash('sha256', 'ops-demo-invalid-checksum');

        File::put(
            $manifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSqliteFixtureConnection(string $sourceDirectory, string $connectionName): array
    {
        File::deleteDirectory($sourceDirectory);
        File::ensureDirectoryExists($sourceDirectory);

        $databasePath = $sourceDirectory.'/database.sqlite';

        $pdo = new PDO('sqlite:'.$databasePath);
        $pdo->exec('CREATE TABLE ops_seed_checkpoints (id INTEGER PRIMARY KEY AUTOINCREMENT, checkpoint TEXT NOT NULL)');
        $pdo->exec("INSERT INTO ops_seed_checkpoints (checkpoint) VALUES ('ready')");
        $pdo = null;

        config()->set('database.connections.'.$connectionName, [
            'driver' => 'sqlite',
            'database' => $databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        return (array) config('database.connections.'.$connectionName);
    }

    protected function backupArtifactService(): BackupArtifactService
    {
        return app(BackupArtifactService::class);
    }

    protected function manifestService(): BackupArtifactManifestService
    {
        return app(BackupArtifactManifestService::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validReadinessReport(): array
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

    /**
     * @return array<string, mixed>
     */
    protected function passReadinessSignoff(): array
    {
        $qaSigner = User::query()
            ->where('email', 'manager.q1@demo.ident.test')
            ->first();
        $pmSigner = User::query()
            ->where('email', 'admin@demo.ident.test')
            ->first();
        $reportPath = self::passReadinessReportPath();

        return [
            'verified_at' => '2026-03-02 08:12:00',
            'verified_by_user_id' => $pmSigner?->id,
            'strict_mode' => true,
            'report_path' => $reportPath,
            'report_sha256' => is_file($reportPath) ? hash_file('sha256', $reportPath) : null,
            'report_status' => 'pass',
            'qa_signoff' => [
                'user_id' => $qaSigner?->id,
                'name' => $qaSigner?->name ?? 'Manager Q1',
                'email' => $qaSigner?->email ?? 'manager.q1@demo.ident.test',
                'roles' => $qaSigner?->getRoleNames()->values()->all() ?? ['Manager'],
                'signed_at' => '2026-03-02 08:11:00',
            ],
            'pm_signoff' => [
                'user_id' => $pmSigner?->id,
                'name' => $pmSigner?->name ?? 'Admin',
                'email' => $pmSigner?->email ?? 'admin@demo.ident.test',
                'roles' => $pmSigner?->getRoleNames()->values()->all() ?? ['Admin'],
                'signed_at' => '2026-03-02 08:12:00',
            ],
            'release_ref' => 'REL-DEMO-OPS-001',
            'summary' => [
                'steps_plan_count' => 2,
                'steps_run_count' => 2,
                'duration_ms' => 600000,
                'with_finance' => true,
                'run_tests' => true,
                'strict_full' => true,
            ],
        ];
    }
}
