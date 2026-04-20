<?php

use App\Console\Commands\RunReleaseGates;
use App\Support\OpsAutomationCatalog;
use App\Support\OpsReleaseGateCatalog;
use Illuminate\Console\Scheduling\Schedule;

describe('OpsAutomationCatalog::trackedCommands()', function (): void {

    it('returns a non-empty list of tracked command strings', function (): void {
        $commands = OpsAutomationCatalog::trackedCommands();

        expect($commands)->not->toBeEmpty()
            ->and($commands)->each(fn ($item) => $item->toBeString());
    });

    it('includes core ops commands', function (): void {
        $commands = OpsAutomationCatalog::trackedCommands();

        expect($commands)->toContain('ops:check-observability-health')
            ->and($commands)->toContain('ops:create-backup-artifact')
            ->and($commands)->toContain('ops:run-release-gates')
            ->and($commands)->toContain('integrations:revoke-rotated-secrets')
            ->and($commands)->toContain('integrations:prune-operational-data');
    });

    it('includes ZNS commands', function (): void {
        $commands = OpsAutomationCatalog::trackedCommands();

        expect($commands)->toContain('zns:run-campaigns')
            ->and($commands)->toContain('zns:sync-automation-events')
            ->and($commands)->toContain('zns:prune-operational-data');
    });

    it('includes EMR maintenance commands', function (): void {
        $commands = OpsAutomationCatalog::trackedCommands();

        expect($commands)->toContain('emr:sync-events')
            ->and($commands)->toContain('emr:reconcile-integrity')
            ->and($commands)->toContain('emr:reconcile-clinical-media')
            ->and($commands)->toContain('emr:check-dicom-readiness')
            ->and($commands)->toContain('emr:prune-clinical-media');
    });

    it('includes Google Calendar sync command', function (): void {
        $commands = OpsAutomationCatalog::trackedCommands();

        expect($commands)->toContain('google-calendar:sync-events');
    });

    it('includes popup and photo prune commands', function (): void {
        $commands = OpsAutomationCatalog::trackedCommands();

        expect($commands)->toContain('popups:dispatch-due')
            ->and($commands)->toContain('popups:prune')
            ->and($commands)->toContain('photos:prune');
    });

    it('has no duplicate tracked commands', function (): void {
        $commands = OpsAutomationCatalog::trackedCommands();

        expect(count($commands))->toBe(count(array_unique($commands)));
    });
});

describe('OpsAutomationCatalog::scheduledAutomationDefinitions()', function (): void {

    it('returns definitions each with target, arguments, cadence, value', function (): void {
        $definitions = OpsAutomationCatalog::scheduledAutomationDefinitions();

        expect($definitions)->not->toBeEmpty();

        foreach ($definitions as $def) {
            expect($def)->toHaveKey('target')
                ->and($def)->toHaveKey('arguments')
                ->and($def)->toHaveKey('cadence')
                ->and($def)->toHaveKey('value');
        }
    });

    it('scheduledAutomationTargets() returns all targets from definitions', function (): void {
        $definitions = OpsAutomationCatalog::scheduledAutomationDefinitions();
        $targets = OpsAutomationCatalog::scheduledAutomationTargets();

        $expectedTargets = collect($definitions)->pluck('target')->values()->all();

        expect($targets)->toBe($expectedTargets);
    });

    it('scheduler registers all catalog targets via hardened wrapper', function (): void {
        $expectedTargets = OpsAutomationCatalog::scheduledAutomationTargets();

        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'ops:run-scheduled-command'))
            ->values();

        expect($events)->toHaveCount(count($expectedTargets));

        foreach ($expectedTargets as $target) {
            $event = $events->first(
                fn ($scheduledEvent) => str_contains((string) $scheduledEvent->command, $target)
            );
            expect($event)->not->toBeNull(
                "Expected target '{$target}' to be registered in scheduler via ops:run-scheduled-command"
            );
        }
    });
});

describe('OpsAutomationCatalog::smokeCommands()', function (): void {

    it('returns a non-empty list of smoke command strings', function (): void {
        $smoke = OpsAutomationCatalog::smokeCommands('2026-01-01', '/tmp/readiness.json');

        expect($smoke)->not->toBeEmpty()
            ->and($smoke)->each(fn ($item) => $item->toBeString()->toStartWith('php artisan'));
    });

    it('includes strict zns and emr smoke commands', function (): void {
        $smoke = OpsAutomationCatalog::smokeCommands('2026-01-01', '/tmp/readiness.json');

        $hasZns = collect($smoke)->contains(fn ($cmd) => str_contains($cmd, 'zns:sync-automation-events'));
        $hasEmr = collect($smoke)->contains(fn ($cmd) => str_contains($cmd, 'emr:reconcile-integrity'));

        expect($hasZns)->toBeTrue()
            ->and($hasEmr)->toBeTrue();
    });
});

describe('OpsReleaseGateCatalog::steps()', function (): void {

    it('ci profile includes base steps only', function (): void {
        $steps = OpsReleaseGateCatalog::steps(RunReleaseGates::PROFILE_CI, false);

        $commands = collect($steps)->pluck('command')->all();

        expect($commands)->toContain('schema:assert-no-pending-migrations')
            ->and($commands)->toContain('schema:assert-critical-foreign-keys')
            ->and($commands)->toContain('emr:reconcile-clinical-media')
            ->and($commands)->not->toContain('security:check-automation-actor')
            ->and($commands)->not->toContain('ops:check-backup-health');
    });

    it('ops profile includes base steps plus hotpath explain', function (): void {
        $steps = OpsReleaseGateCatalog::steps(RunReleaseGates::PROFILE_OPS, false);

        $commands = collect($steps)->pluck('command')->all();

        expect($commands)->toContain('reports:explain-ops-hotpaths')
            ->and($commands)->not->toContain('security:check-automation-actor');
    });

    it('production profile includes all gates including backup and observability', function (): void {
        $steps = OpsReleaseGateCatalog::steps(RunReleaseGates::PROFILE_PRODUCTION, false);

        $commands = collect($steps)->pluck('command')->all();

        expect($commands)->toContain('security:check-automation-actor')
            ->and($commands)->toContain('ops:check-backup-health')
            ->and($commands)->toContain('ops:run-restore-drill')
            ->and($commands)->toContain('ops:check-alert-runbook-map')
            ->and($commands)->toContain('ops:check-observability-health')
            ->and($commands)->toContain('emr:check-dicom-readiness')
            ->and($commands)->toContain('reports:explain-ops-hotpaths');
    });

    it('with_finance=true appends finance reconciliation step', function (): void {
        $withFinance = OpsReleaseGateCatalog::steps(RunReleaseGates::PROFILE_CI, true, '2026-01-01', '2026-01-31');
        $withoutFinance = OpsReleaseGateCatalog::steps(RunReleaseGates::PROFILE_CI, false);

        $financeCommands = collect($withFinance)->pluck('command');

        expect($financeCommands)->toContain('finance:reconcile-branch-attribution')
            ->and(count($withFinance))->toBe(count($withoutFinance) + 1);
    });

    it('each step has required keys: name, command, arguments', function (): void {
        $steps = OpsReleaseGateCatalog::steps(RunReleaseGates::PROFILE_PRODUCTION, true, '2026-01-01', '2026-01-31');

        foreach ($steps as $step) {
            expect($step)->toHaveKey('name')
                ->and($step)->toHaveKey('command')
                ->and($step)->toHaveKey('arguments');
        }
    });
});

describe('OpsReleaseGateCatalog::requiredProductionCommands()', function (): void {

    it('returns non-empty list matching production profile steps', function (): void {
        $required = OpsReleaseGateCatalog::requiredProductionCommands(false);
        $productionSteps = OpsReleaseGateCatalog::steps(RunReleaseGates::PROFILE_PRODUCTION, false, '2026-01-01', '2026-01-31');

        $expectedCommands = collect($productionSteps)->pluck('command')->values()->all();

        expect($required)->not->toBeEmpty()
            ->and($required)->toBe($expectedCommands);
    });

    it('includes finance reconciliation when withFinance=true', function (): void {
        $required = OpsReleaseGateCatalog::requiredProductionCommands(true);

        expect($required)->toContain('finance:reconcile-branch-attribution');
    });

    it('does not include finance reconciliation when withFinance=false', function (): void {
        $required = OpsReleaseGateCatalog::requiredProductionCommands(false);

        expect($required)->not->toContain('finance:reconcile-branch-attribution');
    });
});
