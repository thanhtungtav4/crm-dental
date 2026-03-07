<?php

use App\Filament\Pages\IntegrationSettings;
use App\Models\ClinicSetting;
use App\Models\User;
use Livewire\Livewire;

it('rejects stale integration settings save when revision changed in database', function (): void {
    $admin = actingAsIntegrationSettingsConcurrencyAdmin();

    ClinicSetting::setValue('branding.clinic_name', 'Nha khoa revision A', [
        'group' => 'branding',
        'label' => 'Tên phòng khám',
        'value_type' => 'text',
        'is_secret' => false,
        'is_active' => true,
        'sort_order' => 600,
    ]);

    $component = Livewire::actingAs($admin)->test(IntegrationSettings::class);

    ClinicSetting::setValue('branding.clinic_name', 'Nha khoa revision B', [
        'group' => 'branding',
        'label' => 'Tên phòng khám',
        'value_type' => 'text',
        'is_secret' => false,
        'is_active' => true,
        'sort_order' => 600,
    ]);

    $component
        ->set('settings.branding_clinic_name', 'Nha khoa revision C')
        ->call('save')
        ->assertHasErrors(['settingsRevision']);

    expect(ClinicSetting::getValue('branding.clinic_name'))->toBe('Nha khoa revision B');
});

it('refreshes settings revision after a successful save', function (): void {
    $admin = actingAsIntegrationSettingsConcurrencyAdmin();

    $component = Livewire::actingAs($admin)->test(IntegrationSettings::class);
    $initialRevision = (string) $component->get('settingsRevision');

    $component
        ->set('settings.branding_clinic_name', 'Nha khoa revision success')
        ->call('save')
        ->assertHasNoErrors();

    expect((string) $component->get('settingsRevision'))
        ->not->toBe($initialRevision)
        ->and(ClinicSetting::getValue('branding.clinic_name'))->toBe('Nha khoa revision success');
});

function actingAsIntegrationSettingsConcurrencyAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    test()->actingAs($admin);

    return $admin;
}
