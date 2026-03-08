<?php

use App\Support\ClinicRuntimeSettings;
use Database\Seeders\LocalDemoDataSeeder;
use Database\Seeders\OpsScenarioSeeder;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\seed;

beforeEach(function (): void {
    File::deleteDirectory(storage_path(OpsScenarioSeeder::ROOT_DIRECTORY));
    File::deleteDirectory(storage_path('app/testing/ops-scenario-seeder'));
});

it('configures the automation service account as the local scheduler actor', function (): void {
    seed(LocalDemoDataSeeder::class);

    $automationActor = \App\Models\User::query()
        ->where('email', 'automation.bot@demo.nhakhoaanphuc.test')
        ->firstOrFail();

    expect(ClinicRuntimeSettings::schedulerAutomationActorUserId())->toBe($automationActor->id)
        ->and(ClinicRuntimeSettings::schedulerAutomationActorRequiredRole())->toBe('AutomationService');
});

it('creates ready and failing backup scenarios for local ops smoke', function (): void {
    seed(LocalDemoDataSeeder::class);

    $this->artisan('ops:check-backup-health', [
        '--path' => OpsScenarioSeeder::readyBackupPath(),
        '--strict' => true,
    ])
        ->expectsOutputToContain('BACKUP_HEALTH_STATUS: healthy')
        ->assertSuccessful();

    $this->artisan('ops:check-backup-health', [
        '--path' => OpsScenarioSeeder::failMissingManifestBackupPath(),
        '--strict' => true,
    ])
        ->expectsOutputToContain('BACKUP_HEALTH_ERROR: backup_manifest_missing')
        ->assertFailed();

    $this->artisan('ops:check-backup-health', [
        '--path' => OpsScenarioSeeder::failChecksumBackupPath(),
        '--strict' => true,
    ])
        ->expectsOutputToContain('BACKUP_HEALTH_ERROR: backup_artifact_checksum_mismatch')
        ->assertFailed();
});

it('creates readiness report scenarios that can be verified with seeded demo signers', function (): void {
    seed(LocalDemoDataSeeder::class);

    $signoffPath = storage_path('app/testing/ops-scenario-seeder/readiness-signoff.json');
    File::ensureDirectoryExists(dirname($signoffPath));

    $this->artisan('ops:verify-production-readiness-report', [
        'report' => OpsScenarioSeeder::passReadinessReportPath(),
        '--qa' => 'manager.q1@demo.nhakhoaanphuc.test',
        '--pm' => 'admin@demo.nhakhoaanphuc.test',
        '--release-ref' => 'REL-DEMO-OPS-001',
        '--strict' => true,
        '--output' => $signoffPath,
    ])
        ->expectsOutputToContain('READINESS_REPORT_STATUS: PASS')
        ->expectsOutputToContain('READINESS_SIGNOFF_PATH: '.$signoffPath)
        ->assertSuccessful();

    expect(File::exists($signoffPath))->toBeTrue();

    $this->artisan('ops:verify-production-readiness-report', [
        'report' => OpsScenarioSeeder::failStrictReadinessReportPath(),
        '--qa' => 'manager.q1@demo.nhakhoaanphuc.test',
        '--pm' => 'admin@demo.nhakhoaanphuc.test',
        '--release-ref' => 'REL-DEMO-OPS-001',
        '--strict' => true,
    ])
        ->expectsOutputToContain('strict_full_phai_true_khi_xac_nhan_deploy')
        ->assertFailed();

    $this->artisan('ops:verify-production-readiness-report', [
        'report' => OpsScenarioSeeder::invalidSchemaReadinessReportPath(),
        '--qa' => 'manager.q1@demo.nhakhoaanphuc.test',
        '--pm' => 'admin@demo.nhakhoaanphuc.test',
    ])
        ->expectsOutputToContain('Schema report khong hop le')
        ->assertFailed();

    expect(File::exists(OpsScenarioSeeder::passReadinessSignoffPath()))->toBeTrue()
        ->and((string) data_get(json_decode((string) File::get(OpsScenarioSeeder::passReadinessSignoffPath()), true), 'qa_signoff.email'))
        ->toBe('manager.q1@demo.nhakhoaanphuc.test')
        ->and((string) data_get(json_decode((string) File::get(OpsScenarioSeeder::passReadinessSignoffPath()), true), 'pm_signoff.email'))
        ->toBe('admin@demo.nhakhoaanphuc.test');
});
