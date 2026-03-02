<?php

use Illuminate\Support\Facades\File;

it('validates report schema and creates QA PM signoff artifact', function (): void {
    $reportPath = storage_path('app/testing/readiness-verify/pass-report.json');
    $signoffPath = storage_path('app/testing/readiness-verify/pass-signoff.json');
    File::ensureDirectoryExists(dirname($reportPath));
    File::ensureDirectoryExists(dirname($signoffPath));

    if (file_exists($signoffPath)) {
        unlink($signoffPath);
    }

    file_put_contents($reportPath, json_encode(validReadinessReport(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $this->artisan('ops:verify-production-readiness-report', [
        'report' => $reportPath,
        '--qa' => 'qa.lead@clinic.test',
        '--pm' => 'pm.owner@clinic.test',
        '--release-ref' => 'REL-2026-03-02',
        '--strict' => true,
        '--output' => $signoffPath,
    ])
        ->expectsOutputToContain('READINESS_REPORT_STATUS: PASS')
        ->expectsOutputToContain('READINESS_SIGNOFF_PATH: '.$signoffPath)
        ->assertSuccessful();

    expect(file_exists($signoffPath))->toBeTrue();

    $signoff = json_decode((string) file_get_contents($signoffPath), true, flags: JSON_THROW_ON_ERROR);
    expect($signoff)->toBeArray()
        ->and(data_get($signoff, 'report_path'))->toBe($reportPath)
        ->and(data_get($signoff, 'qa_signoff.signer'))->toBe('qa.lead@clinic.test')
        ->and(data_get($signoff, 'pm_signoff.signer'))->toBe('pm.owner@clinic.test')
        ->and(data_get($signoff, 'release_ref'))->toBe('REL-2026-03-02');
});

it('fails when schema is invalid', function (): void {
    $reportPath = storage_path('app/testing/readiness-verify/invalid-schema-report.json');
    File::ensureDirectoryExists(dirname($reportPath));
    file_put_contents($reportPath, json_encode(['status' => 'pass'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $this->artisan('ops:verify-production-readiness-report', [
        'report' => $reportPath,
        '--qa' => 'qa.lead@clinic.test',
        '--pm' => 'pm.owner@clinic.test',
    ])
        ->expectsOutputToContain('READINESS_REPORT_STATUS: FAIL')
        ->expectsOutputToContain('Schema report khong hop le')
        ->assertFailed();
});

it('fails strict mode when full strict checklist conditions are not met', function (): void {
    $reportPath = storage_path('app/testing/readiness-verify/non-strict-full-report.json');
    File::ensureDirectoryExists(dirname($reportPath));

    $report = validReadinessReport();
    $report['strict_full'] = false;
    file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $this->artisan('ops:verify-production-readiness-report', [
        'report' => $reportPath,
        '--qa' => 'qa.lead@clinic.test',
        '--pm' => 'pm.owner@clinic.test',
        '--release-ref' => 'REL-2026-03-02',
        '--strict' => true,
    ])
        ->expectsOutputToContain('READINESS_REPORT_STATUS: FAIL')
        ->expectsOutputToContain('strict_full_phai_true_khi_xac_nhan_deploy')
        ->assertFailed();
});

/**
 * @return array<string, mixed>
 */
function validReadinessReport(): array
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
                'output' => 'release gate ok',
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
