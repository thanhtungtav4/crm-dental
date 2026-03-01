<?php

use Spatie\Permission\Models\Role;

it('passes strict runbook map check when required categories are mapped to valid owners', function (): void {
    Role::findOrCreate('AutomationService');
    Role::findOrCreate('Admin');
    Role::findOrCreate('Manager');

    $this->artisan('ops:check-alert-runbook-map', [
        '--strict' => true,
    ])
        ->expectsOutputToContain('RUNBOOK_MAP_ERROR_COUNT: 0')
        ->assertSuccessful();
});

it('fails strict runbook map check when categories or owner mapping are invalid', function (): void {
    config()->set('care.ops_alert_runbook', [
        'backup_health' => [
            'owner_role' => '',
            'threshold' => '',
            'runbook' => '',
        ],
    ]);

    $this->artisan('ops:check-alert-runbook-map', [
        '--strict' => true,
    ])
        ->expectsOutputToContain('RUNBOOK_MAP_ERROR')
        ->expectsOutputToContain('Strict mode: alert runbook map chưa hợp lệ.')
        ->assertFailed();
});
