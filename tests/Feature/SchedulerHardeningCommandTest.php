<?php

use App\Models\AuditLog;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Process\ProcessResult as ProcessResultContract;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

it('retries scheduler command and succeeds on a later attempt', function () {
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(
                output: '',
                errorOutput: 'first attempt failed',
                exitCode: 1,
            ))
            ->push(Process::result(
                output: 'ok',
                errorOutput: '',
                exitCode: 0,
            )),
    ]);

    $this->artisan('ops:run-scheduled-command', [
        'target' => 'care:generate-birthday-tickets',
        '--timeout' => 45,
        '--max-attempts' => 2,
        '--retry-delay' => 0,
        '--alert-after' => 60,
    ])->assertSuccessful();

    Process::assertRanTimes(function (PendingProcess $process, ProcessResultContract $result): bool {
        return is_array($process->command)
            && in_array('care:generate-birthday-tickets', $process->command, true);
    }, 2);

    $runLog = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_AUTOMATION)
        ->where('action', AuditLog::ACTION_RUN)
        ->latest('id')
        ->first();

    $failLog = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_AUTOMATION)
        ->where('action', AuditLog::ACTION_FAIL)
        ->latest('id')
        ->first();

    expect($runLog)->not->toBeNull()
        ->and(data_get($runLog?->metadata, 'target_command'))->toBe('care:generate-birthday-tickets')
        ->and((int) data_get($runLog?->metadata, 'attempt'))->toBe(2)
        ->and($failLog)->not->toBeNull()
        ->and(data_get($failLog?->metadata, 'alert_reason'))->toBe('command_failed')
        ->and((bool) data_get($failLog?->metadata, 'will_retry'))->toBeTrue();
});

it('records failure alert after exhausting retry policy', function () {
    Process::fake([
        '*' => Process::result(
            output: '',
            errorOutput: 'still failing',
            exitCode: 1,
        ),
    ]);

    $this->artisan('ops:run-scheduled-command', [
        'target' => 'reports:snapshot-operational-kpis',
        '--timeout' => 30,
        '--max-attempts' => 3,
        '--retry-delay' => 0,
        '--alert-after' => 120,
    ])->assertExitCode(1);

    Process::assertRanTimes(function (PendingProcess $process, ProcessResultContract $result): bool {
        return is_array($process->command)
            && in_array('reports:snapshot-operational-kpis', $process->command, true);
    }, 3);

    $finalFailureLog = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_AUTOMATION)
        ->where('action', AuditLog::ACTION_FAIL)
        ->latest('id')
        ->first();

    expect($finalFailureLog)->not->toBeNull()
        ->and((int) data_get($finalFailureLog?->metadata, 'attempt'))->toBe(3)
        ->and((int) data_get($finalFailureLog?->metadata, 'max_attempts'))->toBe(3)
        ->and((bool) data_get($finalFailureLog?->metadata, 'will_retry'))->toBeFalse()
        ->and(data_get($finalFailureLog?->metadata, 'alert_reason'))->toBe('command_failed');
});

it('records SLA alert when runtime exceeds alert threshold', function () {
    Process::fake([
        '*' => Process::result(
            output: 'ok',
            errorOutput: '',
            exitCode: 0,
        ),
    ]);

    $this->artisan('ops:run-scheduled-command', [
        'target' => 'invoices:sync-overdue-status',
        '--timeout' => 30,
        '--max-attempts' => 1,
        '--retry-delay' => 0,
        '--alert-after' => 0,
    ])->assertSuccessful();

    $slaAlert = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_AUTOMATION)
        ->where('action', AuditLog::ACTION_FAIL)
        ->latest('id')
        ->first();

    expect($slaAlert)->not->toBeNull()
        ->and(data_get($slaAlert?->metadata, 'alert_reason'))->toBe('sla_breach')
        ->and((bool) data_get($slaAlert?->metadata, 'will_retry'))->toBeFalse();
});

it('schedules critical automations via hardened wrapper with single-node lock', function () {
    $expectedTargets = [
        'care:generate-birthday-tickets',
        'care:generate-recall-tickets',
        'reports:snapshot-operational-kpis',
        'reports:snapshot-hot-aggregates',
        'growth:run-loyalty-program',
        'patients:score-risk',
        'mpi:sync',
        'finance:run-invoice-aging-reminders',
        'care:run-plan-follow-up',
        'growth:run-reactivation-flow',
        'reports:check-snapshot-sla',
        'ops:run-restore-drill',
        'ops:check-alert-runbook-map',
        'emr:sync-events',
        'emr:reconcile-integrity',
        'appointments:run-no-show-recovery',
        'invoices:sync-overdue-status',
    ];

    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'ops:run-scheduled-command'))
        ->values();

    expect($events)->toHaveCount(count($expectedTargets));

    foreach ($expectedTargets as $target) {
        $event = $events->first(
            fn ($scheduledEvent) => str_contains((string) $scheduledEvent->command, $target)
        );

        expect($event)->not->toBeNull()
            ->and($event->withoutOverlapping)->toBeTrue()
            ->and($event->onOneServer)->toBeTrue()
            ->and($event->expiresAt)->toBe(ClinicRuntimeSettings::schedulerLockExpiresAfterMinutes());
    }
});
