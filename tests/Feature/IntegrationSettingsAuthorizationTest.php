<?php

use App\Filament\Pages\IntegrationSettings;
use App\Models\ClinicSetting;
use App\Models\User;
use Livewire\Livewire;

it('forbids manager from saving integration settings', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    Livewire::actingAs($manager)
        ->test(IntegrationSettings::class)
        ->call('save')
        ->assertForbidden();
});

it('forbids manager from generating a new web lead token', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    Livewire::actingAs($manager)
        ->test(IntegrationSettings::class)
        ->call('generateWebLeadApiToken')
        ->assertForbidden();
});

it('allows admin to save integration settings', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    Livewire::actingAs($admin)
        ->test(IntegrationSettings::class)
        ->set('catalogEditors.catalog_customer_sources_json', [
            ['key' => '', 'label' => 'Nguồn auth admin', 'enabled' => true],
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect(ClinicSetting::getValue('catalog.customer_sources', []))->toBe([
        'nguon_auth_admin' => 'Nguồn auth admin',
    ]);
});
