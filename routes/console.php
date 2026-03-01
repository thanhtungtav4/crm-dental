<?php

use App\Console\Commands\RunScheduledCommand;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$scheduleLockTtlMinutes = ClinicRuntimeSettings::schedulerLockExpiresAfterMinutes();

$scheduleAutomation = function (string $targetCommand) use ($scheduleLockTtlMinutes) {
    return Schedule::command(RunScheduledCommand::class, [$targetCommand])
        ->name("automation:{$targetCommand}")
        ->withoutOverlapping($scheduleLockTtlMinutes)
        ->onOneServer();
};

Schedule::command('security:check-automation-actor', ['--strict' => true])
    ->name('security:check-automation-actor')
    ->hourlyAt(0)
    ->withoutOverlapping($scheduleLockTtlMinutes)
    ->onOneServer();

$scheduleAutomation('care:generate-birthday-tickets')->dailyAt('00:05');
$scheduleAutomation('care:generate-recall-tickets')->dailyAt('00:10');
$scheduleAutomation('reports:snapshot-operational-kpis')->dailyAt('00:20');
$scheduleAutomation('reports:snapshot-hot-aggregates')->dailyAt('00:25');
$scheduleAutomation('growth:run-loyalty-program')->dailyAt('00:35');
$scheduleAutomation('patients:score-risk')->dailyAt('00:45');
$scheduleAutomation('mpi:sync')->dailyAt('01:30');
$scheduleAutomation('finance:run-invoice-aging-reminders')->dailyAt('08:00');
$scheduleAutomation('care:run-plan-follow-up')->dailyAt('09:00');
$scheduleAutomation('growth:run-reactivation-flow')->dailyAt('09:30');
$scheduleAutomation('reports:check-snapshot-sla')->dailyAt('10:00');
$scheduleAutomation('ops:run-restore-drill')->dailyAt('02:10');
$scheduleAutomation('ops:check-alert-runbook-map')->dailyAt('02:20');
$scheduleAutomation('emr:sync-events')->hourlyAt(15);
$scheduleAutomation('emr:reconcile-integrity')->hourlyAt(40);
$scheduleAutomation('zns:run-campaigns')->everyTenMinutes();
$scheduleAutomation('photos:prune')->dailyAt('03:10');
$scheduleAutomation('appointments:run-no-show-recovery')->hourlyAt(5);
$scheduleAutomation('invoices:sync-overdue-status')->hourly();
