<?php

use Illuminate\Contracts\Process\ProcessResult as ProcessResultContract;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

it('shows dry run checklist without executing commands', function (): void {
    Process::fake();
    $reportPath = storage_path('app/testing/production-readiness/dry-run-report.json');
    if (file_exists($reportPath)) {
        unlink($reportPath);
    }

    $this->artisan('ops:run-production-readiness', [
        '--with-finance' => true,
        '--from' => '2026-02-20',
        '--to' => '2026-03-01',
        '--run-tests' => true,
        '--test-filter' => 'RunReleaseGatesCommandTest',
        '--dry-run' => true,
        '--report' => $reportPath,
    ])
        ->expectsOutputToContain('PRODUCTION_READINESS_PROFILE: production')
        ->expectsOutputToContain('ops:run-release-gates')
        ->expectsOutputToContain('artisan test')
        ->expectsOutputToContain('PRODUCTION_READINESS_STATUS: DRY_RUN')
        ->expectsOutputToContain('PRODUCTION_READINESS_REPORT: '.$reportPath)
        ->assertSuccessful();

    Process::assertNothingRan();

    $report = json_decode((string) file_get_contents($reportPath), true, flags: JSON_THROW_ON_ERROR);
    expect($report)->toBeArray()
        ->and(data_get($report, 'status'))->toBe('dry_run')
        ->and(data_get($report, 'run_tests'))->toBeTrue()
        ->and(count((array) data_get($report, 'steps_plan')))->toBe(2);
});

it('fails when release gate step fails', function (): void {
    Process::fake([
        '*' => Process::result(
            output: '',
            errorOutput: 'release gate failed',
            exitCode: 1,
        ),
    ]);
    $reportPath = storage_path('app/testing/production-readiness/fail-report.json');
    File::ensureDirectoryExists(dirname($reportPath));
    if (file_exists($reportPath)) {
        unlink($reportPath);
    }

    $this->artisan('ops:run-production-readiness', [
        '--fail-fast' => true,
        '--report' => $reportPath,
    ])
        ->expectsOutputToContain('PRODUCTION_READINESS_STATUS: FAIL')
        ->expectsOutputToContain('PRODUCTION_READINESS_REPORT: '.$reportPath)
        ->assertFailed();

    $report = json_decode((string) file_get_contents($reportPath), true, flags: JSON_THROW_ON_ERROR);
    expect(data_get($report, 'status'))->toBe('fail')
        ->and(count((array) data_get($report, 'failures')))->toBeGreaterThanOrEqual(1);
});

it('runs release gate and full suite in strict-full mode', function (): void {
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(
                output: 'release gate ok',
                errorOutput: '',
                exitCode: 0,
            ))
            ->push(Process::result(
                output: 'tests ok',
                errorOutput: '',
                exitCode: 0,
            )),
    ]);
    $reportPath = storage_path('app/testing/production-readiness/pass-report.json');
    if (file_exists($reportPath)) {
        unlink($reportPath);
    }

    $this->artisan('ops:run-production-readiness', [
        '--with-finance' => true,
        '--from' => '2026-02-20',
        '--to' => '2026-03-01',
        '--strict-full' => true,
        '--test-filter' => 'ShouldBeIgnored',
        '--report' => $reportPath,
    ])
        ->expectsOutputToContain('STRICT_FULL_MODE: --test-filter se bi bo qua')
        ->expectsOutputToContain('PRODUCTION_READINESS_STATUS: PASS')
        ->expectsOutputToContain('PRODUCTION_READINESS_REPORT: '.$reportPath)
        ->assertSuccessful();

    Process::assertRan(function (PendingProcess $process, ProcessResultContract $result): bool {
        return is_array($process->command)
            && in_array('ops:run-release-gates', $process->command, true)
            && in_array('--profile=production', $process->command, true)
            && in_array('--with-finance', $process->command, true)
            && in_array('--from=2026-02-20', $process->command, true)
            && in_array('--to=2026-03-01', $process->command, true);
    });

    Process::assertRan(function (PendingProcess $process, ProcessResultContract $result): bool {
        return is_array($process->command)
            && in_array('test', $process->command, true)
            && collect($process->command)
                ->filter(fn (mixed $value): bool => is_string($value))
                ->contains(fn (string $value): bool => str_starts_with($value, '--filter=')) === false;
    });

    $report = json_decode((string) file_get_contents($reportPath), true, flags: JSON_THROW_ON_ERROR);
    expect(data_get($report, 'status'))->toBe('pass')
        ->and(data_get($report, 'strict_full'))->toBeTrue()
        ->and(data_get($report, 'test_filter'))->toBeNull();
});
