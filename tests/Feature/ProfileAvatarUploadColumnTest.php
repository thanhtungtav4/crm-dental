<?php

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Schema;

it('uses avatar_url as the persisted profile avatar column', function (): void {
    expect(Schema::hasColumn('users', 'avatar_url'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'avatar'))->toBeFalse()
        ->and((new User)->getFillable())->toContain('avatar_url')
        ->not->toContain('avatar');
});

it('configures my profile avatar upload component to use avatar_url', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $avatarUploadStatePath = filament('filament-breezy')
        ->getAvatarUploadComponent()
        ->getStatePath(false);

    expect($avatarUploadStatePath)->toBe('avatar_url');
});

it('renders my-profile page successfully for sensitive role with mfa', function (): void {
    $admin = User::factory()->create([
        'two_factor_confirmed_at' => now(),
    ]);
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get(route('filament.admin.pages.my-profile'))
        ->assertOk();
});
