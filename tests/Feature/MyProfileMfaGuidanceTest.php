<?php

use App\Models\User;

it('shows explicit mfa setup guidance when mfa_required query is present', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->get(route('filament.admin.pages.my-profile', ['mfa_required' => 1]))
        ->assertOk()
        ->assertSee('Bắt buộc bật MFA trước khi tiếp tục')
        ->assertSee('Xác thực hai yếu tố')
        ->assertSee('Passkeys');
});

it('does not show forced mfa notice on normal profile access', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->get(route('filament.admin.pages.my-profile'))
        ->assertOk()
        ->assertDontSee('Bắt buộc bật MFA trước khi tiếp tục');
});
