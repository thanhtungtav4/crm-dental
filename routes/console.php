<?php

use App\Console\Commands\RunScheduledCommand;
use App\Support\ClinicRuntimeSettings;
use App\Support\OpsAutomationCatalog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$scheduleLockTtlMinutes = ClinicRuntimeSettings::schedulerLockExpiresAfterMinutes();

$scheduleAutomation = function (string $targetCommand, array $targetArgs = []) use ($scheduleLockTtlMinutes) {
    $arguments = array_merge(
        [$targetCommand],
        collect($targetArgs)
            ->map(static fn (string $arg): string => '--target-args='.$arg)
            ->all(),
    );

    return Schedule::command(RunScheduledCommand::class, $arguments)
        ->name("automation:{$targetCommand}")
        ->withoutOverlapping($scheduleLockTtlMinutes)
        ->onOneServer();
};

Schedule::command('security:check-automation-actor', ['--strict' => true])
    ->name('security:check-automation-actor')
    ->hourlyAt(0)
    ->withoutOverlapping($scheduleLockTtlMinutes)
    ->onOneServer();

collect(OpsAutomationCatalog::scheduledAutomationDefinitions())
    ->each(function (array $definition) use ($scheduleAutomation): void {
        $event = $scheduleAutomation(
            $definition['target'],
            $definition['arguments'],
        );

        match ($definition['cadence']) {
            'dailyAt' => $event->dailyAt((string) $definition['value']),
            'hourlyAt' => $event->hourlyAt((int) $definition['value']),
            'everyFiveMinutes' => $event->everyFiveMinutes(),
            'everyTenMinutes' => $event->everyTenMinutes(),
            'everyMinute' => $event->everyMinute(),
            'hourly' => $event->hourly(),
            default => throw new \InvalidArgumentException('Unsupported automation cadence ['.$definition['cadence'].'].'),
        };
    });
