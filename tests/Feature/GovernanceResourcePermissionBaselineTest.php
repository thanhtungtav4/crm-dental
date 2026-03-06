<?php

use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;

it('keeps manager away from governance resources while preserving operational baseline access', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    auth()->login($manager);

    expect($manager->can('ViewAny:Branch'))->toBeFalse()
        ->and($manager->can('Create:Branch'))->toBeFalse()
        ->and($manager->can('Update:Branch'))->toBeFalse()
        ->and($manager->can('Delete:Branch'))->toBeFalse()
        ->and($manager->can('ViewAny:User'))->toBeFalse()
        ->and($manager->can('Create:User'))->toBeFalse()
        ->and($manager->can('Update:User'))->toBeFalse()
        ->and($manager->can('Delete:User'))->toBeFalse()
        ->and($manager->can('ViewAny:Role'))->toBeFalse()
        ->and($manager->can('Create:Role'))->toBeFalse()
        ->and($manager->can('Update:Role'))->toBeFalse()
        ->and($manager->can('Delete:Role'))->toBeFalse()
        ->and($manager->can('ViewAny:Patient'))->toBeTrue()
        ->and($manager->can('Create:Appointment'))->toBeTrue()
        ->and($manager->can('Update:Invoice'))->toBeTrue()
        ->and(BranchResource::canViewAny())->toBeFalse()
        ->and(UserResource::canViewAny())->toBeFalse();
});

it('keeps admin access to governance resources', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    auth()->login($admin);

    expect($admin->can('ViewAny:Branch'))->toBeTrue()
        ->and($admin->can('Create:Branch'))->toBeTrue()
        ->and($admin->can('Update:Branch'))->toBeTrue()
        ->and($admin->can('Delete:Branch'))->toBeTrue()
        ->and($admin->can('ViewAny:User'))->toBeTrue()
        ->and($admin->can('Create:User'))->toBeTrue()
        ->and($admin->can('Update:User'))->toBeTrue()
        ->and($admin->can('Delete:User'))->toBeTrue()
        ->and($admin->can('ViewAny:Role'))->toBeTrue()
        ->and($admin->can('Create:Role'))->toBeTrue()
        ->and($admin->can('Update:Role'))->toBeTrue()
        ->and($admin->can('Delete:Role'))->toBeTrue()
        ->and(BranchResource::canViewAny())->toBeTrue()
        ->and(UserResource::canViewAny())->toBeTrue();
});
