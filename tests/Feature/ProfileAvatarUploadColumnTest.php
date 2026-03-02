<?php

use App\Models\User;
use Illuminate\Support\Facades\File;

it('uses avatar_url as the avatar upload field in admin profile configuration', function (): void {
    $providerSource = (string) File::get(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($providerSource)
        ->toContain("FileUpload::make('avatar_url')")
        ->not->toContain("FileUpload::make('avatar')");
});

it('allows mass assignment for avatar_url and does not rely on legacy avatar field', function (): void {
    $user = User::factory()->create();

    $user->update([
        'avatar_url' => 'avatars/test-avatar.jpg',
    ]);

    $user->refresh();

    expect($user->avatar_url)->toBe('avatars/test-avatar.jpg')
        ->and(in_array('avatar_url', $user->getFillable(), true))->toBeTrue()
        ->and(in_array('avatar', $user->getFillable(), true))->toBeFalse();
});
