<?php

use App\Filament\Pages\Auth\Login;
use App\Models\AuditLog;
use App\Models\ClinicSetting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('locks login attempts after configured threshold and records security audit', function (): void {
    ClinicSetting::setValue('security.login_max_attempts', 2, ['group' => 'security']);
    ClinicSetting::setValue('security.login_lockout_minutes', 15, ['group' => 'security']);

    $user = User::factory()->create([
        'email' => 'lockout@test.crm',
        'password' => Hash::make('correct-password'),
    ]);

    $component = Livewire::test(Login::class)
        ->set('data.email', $user->email)
        ->set('data.password', 'wrong-password');

    $component->call('authenticate')->assertHasErrors(['data.email']);
    $component->call('authenticate')->assertHasErrors(['data.email']);
    $component->call('authenticate');

    $audit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_SECURITY)
        ->where('action', AuditLog::ACTION_BLOCK)
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and(data_get($audit?->metadata, 'reason'))->toBe('login_lockout')
        ->and((int) data_get($audit?->metadata, 'max_attempts'))->toBe(2)
        ->and((int) data_get($audit?->metadata, 'lockout_minutes'))->toBe(15);
});
