<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('care:generate-birthday-tickets')
    ->dailyAt('00:05');

Schedule::command('care:generate-recall-tickets')
    ->dailyAt('00:10');

Schedule::command('reports:snapshot-operational-kpis')
    ->dailyAt('00:20');

Schedule::command('mpi:sync')
    ->dailyAt('01:30');

Schedule::command('appointments:run-no-show-recovery')
    ->hourlyAt(5);

Schedule::command('finance:run-invoice-aging-reminders')
    ->dailyAt('08:00');

Schedule::command('care:run-plan-follow-up')
    ->dailyAt('09:00');

Schedule::command('reports:check-snapshot-sla')
    ->dailyAt('10:00');

Schedule::command('invoices:sync-overdue-status')
    ->hourly();
