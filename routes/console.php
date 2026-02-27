<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('care:generate-birthday-tickets')
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('care:generate-recall-tickets')
    ->dailyAt('00:10')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('reports:snapshot-operational-kpis')
    ->dailyAt('00:20')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('mpi:sync')
    ->dailyAt('01:30')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('appointments:run-no-show-recovery')
    ->hourlyAt(5)
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('finance:run-invoice-aging-reminders')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('care:run-plan-follow-up')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('growth:run-loyalty-program')
    ->dailyAt('00:35')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('patients:score-risk')
    ->dailyAt('00:45')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('growth:run-reactivation-flow')
    ->dailyAt('09:30')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('reports:check-snapshot-sla')
    ->dailyAt('10:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('invoices:sync-overdue-status')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
