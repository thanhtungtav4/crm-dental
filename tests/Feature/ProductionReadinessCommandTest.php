<?php

use Illuminate\Contracts\Process\ProcessResult as ProcessResultContract;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

it('shows dry run checklist without executing commands', function (): void {
    Process::fake();

    $this->artisan('ops:run-production-readiness', [
        '--with-finance' => true,
        '--from' => '2026-02-20',
        '--to' => '2026-03-01',
        '--run-tests' => true,
        '--test-filter' => 'RunReleaseGatesCommandTest',
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('PRODUCTION_READINESS_PROFILE: production')
        ->expectsOutputToContain('ops:run-release-gates')
        ->expectsOutputToContain('artisan test')
        ->expectsOutputToContain('PRODUCTION_READINESS_STATUS: DRY_RUN')
        ->assertSuccessful();
});

it('fails when release gate step fails', function (): void {
    Process::fake([
        '*' => Process::result(
            output: '',
            errorOutput: 'release gate failed',
            exitCode: 1,
        ),
    ]);

    $this->artisan('ops:run-production-readiness', [
        '--fail-fast' => true,
    ])
        ->expectsOutputToContain('PRODUCTION_READINESS_STATUS: FAIL')
        ->assertFailed();
});

it('runs release gate and optional tests successfully', function (): void {
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

    $this->artisan('ops:run-production-readiness', [
        '--with-finance' => true,
        '--from' => '2026-02-20',
        '--to' => '2026-03-01',
        '--run-tests' => true,
        '--test-filter' => 'ProductionReadinessCommandTest',
    ])
        ->expectsOutputToContain('PRODUCTION_READINESS_STATUS: PASS')
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
            && in_array('--filter=ProductionReadinessCommandTest', $process->command, true);
    });
});
