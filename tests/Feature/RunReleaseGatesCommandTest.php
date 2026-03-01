<?php

it('fails when profile is invalid', function (): void {
    $this->artisan('ops:run-release-gates', [
        '--profile' => 'unknown',
    ])->assertFailed();
});

it('shows planned steps in dry-run production profile', function (): void {
    $this->artisan('ops:run-release-gates', [
        '--profile' => 'production',
        '--with-finance' => true,
        '--from' => '2026-01-01',
        '--to' => '2026-01-31',
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('RELEASE_GATE_PROFILE: production')
        ->expectsOutputToContain('security:check-automation-actor')
        ->expectsOutputToContain('ops:check-backup-health')
        ->expectsOutputToContain('ops:run-restore-drill')
        ->expectsOutputToContain('ops:check-alert-runbook-map')
        ->expectsOutputToContain('finance:reconcile-branch-attribution')
        ->expectsOutputToContain('Dry-run completed')
        ->assertSuccessful();
});

it('runs ci release gates successfully', function (): void {
    $this->artisan('ops:run-release-gates', [
        '--profile' => 'ci',
    ])
        ->expectsOutputToContain('schema:assert-no-pending-migrations')
        ->expectsOutputToContain('security:assert-action-permission-baseline')
        ->expectsOutputToContain('Release gate: PASS')
        ->assertSuccessful();
});
