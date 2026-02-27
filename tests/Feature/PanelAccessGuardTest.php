<?php

use App\Models\User;
use Filament\Panel;

it('blocks admin panel access for inactive or unprivileged users', function () {
    $panel = \Mockery::mock(Panel::class);
    $panel->shouldReceive('getId')->andReturn('admin');

    $inactiveUser = User::factory()->make([
        'status' => false,
    ]);

    $unprivilegedUser = User::factory()->create();

    expect($inactiveUser->canAccessPanel($panel))->toBeFalse()
        ->and($unprivilegedUser->canAccessPanel($panel))->toBeFalse();
});

it('allows active staff role to access admin panel', function () {
    $panel = \Mockery::mock(Panel::class);
    $panel->shouldReceive('getId')->andReturn('admin');

    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    expect($manager->canAccessPanel($panel))->toBeTrue();
});
