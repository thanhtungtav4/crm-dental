<?php

use App\Models\AuditLog;
use App\Models\User;

beforeEach(function (): void {
    config()->set('care.security_enforce_in_tests', true);
});

it('redirects sensitive roles without mfa to profile setup and writes audit log', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $response = $this->actingAs($manager)
        ->get(route('filament.admin.pages.dashboard'));

    $response->assertRedirect(route('filament.admin.pages.my-profile', ['mfa_required' => 1]));

    $audit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_SECURITY)
        ->where('entity_id', $manager->id)
        ->where('action', AuditLog::ACTION_BLOCK)
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and(data_get($audit?->metadata, 'reason'))->toBe('mfa_required');
});

it('allows sensitive roles to access admin panel when mfa is configured', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');
    $manager->forceFill([
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->actingAs($manager)
        ->get(route('filament.admin.pages.dashboard'))
        ->assertSuccessful();
});

it('expires idle admin session and records security audit', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $admin->forceFill([
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->actingAs($admin)
        ->withSession([
            'security.admin_last_activity_at' => now()->subHours(2)->getTimestamp(),
        ])
        ->get(route('filament.admin.pages.dashboard'))
        ->assertRedirect(route('filament.admin.auth.login'));

    $this->assertGuest();

    $audit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_SECURITY)
        ->where('entity_id', $admin->id)
        ->where('action', AuditLog::ACTION_FAIL)
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and(data_get($audit?->metadata, 'reason'))->toBe('session_idle_timeout');
});
