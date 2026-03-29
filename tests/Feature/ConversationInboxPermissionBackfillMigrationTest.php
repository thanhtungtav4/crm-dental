<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

it('backfills the conversation inbox page permission and grants it to admin manager and cskh roles', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $cskh = User::factory()->create();
    $cskh->assignRole('CSKH');

    Permission::query()
        ->where('name', 'View:ConversationInbox')
        ->delete();

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Permission::query()->where('name', 'View:ConversationInbox')->exists())->toBeFalse()
        ->and($admin->fresh()->can('View:ConversationInbox'))->toBeFalse()
        ->and($manager->fresh()->can('View:ConversationInbox'))->toBeFalse()
        ->and($cskh->fresh()->can('View:ConversationInbox'))->toBeFalse();

    $migration = require database_path('migrations/2026_03_29_190641_backfill_conversation_inbox_page_permission.php');
    $migration->up();

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $adminRole = Role::query()->where('name', 'Admin')->firstOrFail();
    $managerRole = Role::query()->where('name', 'Manager')->firstOrFail();
    $cskhRole = Role::query()->where('name', 'CSKH')->firstOrFail();

    expect(Permission::query()->where('name', 'View:ConversationInbox')->count())->toBe(1)
        ->and($adminRole->hasPermissionTo('View:ConversationInbox'))->toBeTrue()
        ->and($managerRole->hasPermissionTo('View:ConversationInbox'))->toBeTrue()
        ->and($cskhRole->hasPermissionTo('View:ConversationInbox'))->toBeTrue()
        ->and($admin->fresh()->can('View:ConversationInbox'))->toBeTrue()
        ->and($manager->fresh()->can('View:ConversationInbox'))->toBeTrue()
        ->and($cskh->fresh()->can('View:ConversationInbox'))->toBeTrue();
});

it('keeps the conversation inbox page permission backfill migration idempotent', function (): void {
    $migration = require database_path('migrations/2026_03_29_190641_backfill_conversation_inbox_page_permission.php');

    $migration->up();
    $migration->up();

    expect(Permission::query()->where('name', 'View:ConversationInbox')->count())->toBe(1);
});
