<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

it('backfills the integration settings page permission and grants it to admin', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    Permission::query()
        ->where('name', 'View:IntegrationSettings')
        ->delete();

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Permission::query()->where('name', 'View:IntegrationSettings')->exists())->toBeFalse()
        ->and($admin->fresh()->can('View:IntegrationSettings'))->toBeFalse();

    $migration = require database_path('migrations/2026_03_24_233537_backfill_integration_settings_page_permission.php');
    $migration->up();

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $adminRole = Role::query()->where('name', 'Admin')->firstOrFail();

    expect(Permission::query()->where('name', 'View:IntegrationSettings')->count())->toBe(1)
        ->and($adminRole->hasPermissionTo('View:IntegrationSettings'))->toBeTrue()
        ->and($admin->fresh()->can('View:IntegrationSettings'))->toBeTrue();
});

it('keeps the integration settings page permission backfill migration idempotent', function (): void {
    $migration = require database_path('migrations/2026_03_24_233537_backfill_integration_settings_page_permission.php');

    $migration->up();
    $migration->up();

    expect(Permission::query()->where('name', 'View:IntegrationSettings')->count())->toBe(1);
});
