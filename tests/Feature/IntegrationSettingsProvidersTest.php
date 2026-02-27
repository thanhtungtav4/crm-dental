<?php

use App\Filament\Pages\IntegrationSettings;
use App\Models\ClinicSetting;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

it('does not expose vnpay provider or fields in integration settings', function () {
    $page = app(IntegrationSettings::class);
    $providers = collect($page->getProviders());
    $fieldKeys = $providers
        ->flatMap(fn (array $provider) => collect($provider['fields'] ?? [])->pluck('key'))
        ->values();

    expect($providers->pluck('group'))
        ->not->toContain('vnpay')
        ->and($fieldKeys->contains(fn ($key) => str_starts_with((string) $key, 'vnpay.')))
        ->toBeFalse()
        ->and($page->getSubheading())
        ->not->toContain('VNPay');
});

it('loads integration settings state without colliding with livewire hydrate hooks', function () {
    $page = app(IntegrationSettings::class);

    $page->mount();

    expect(method_exists($page, 'hydrateSettings'))->toBeFalse()
        ->and($page->settings)->toBeArray()
        ->and($page->settings)->not->toBeEmpty();
});

it('shows web lead api integration guide in settings page', function () {
    $blade = File::get(resource_path('views/filament/pages/integration-settings.blade.php'));

    expect($blade)
        ->toContain('Hướng dẫn tích hợp Web Lead API')
        ->toContain('wire:click="generateWebLeadApiToken"')
        ->toContain('Tạo API Token')
        ->toContain("route('api.v1.web-leads.store')")
        ->toContain('X-Idempotency-Key')
        ->toContain('Payload tối thiểu')
        ->toContain('curl -X POST')
        ->toContain('<x-filament::input.wrapper>')
        ->toContain('showWebLeadToken')
        ->toContain('x-ref="webLeadApiTokenInput"')
        ->toContain("x-bind:type=\"showWebLeadToken ? 'text' : 'password'\"")
        ->toContain('x-on:click="navigator.clipboard?.writeText($refs.webLeadApiTokenInput?.value ?? \'\')"')
        ->toContain('<x-filament::input.select wire:model.blur="{{ $statePath }}">');
});

it('renders concrete input html for integration text fields', function () {
    $html = Livewire::test(IntegrationSettings::class)->html();

    expect($html)
        ->not->toContain('<x-filament::input')
        ->toContain('class="fi-input"')
        ->toContain('wire:model.blur="settings.google_calendar_client_id"')
        ->toContain('wire:model.blur="settings.emr_provider"')
        ->toContain('wire:model.blur="settings.branding_clinic_name"')
        ->toContain('wire:model.blur="settings.branding_logo_url"');
});

it('can autogenerate web lead api token in form state', function () {
    $component = Livewire::test(IntegrationSettings::class)
        ->set('settings.web_lead_api_token', '')
        ->call('generateWebLeadApiToken');

    $token = (string) $component->get('settings.web_lead_api_token');

    expect($token)
        ->toStartWith('wla_')
        ->and(strlen($token))
        ->toBe(52);
});

it('normalizes exam indication image alias keys to canonical keys in catalog editor', function (): void {
    $component = Livewire::test(IntegrationSettings::class)
        ->set('catalogEditors.catalog_exam_indications_json', [
            ['key' => 'image_ext', 'label' => 'Ảnh ngoài'],
            ['key' => 'image_int', 'label' => 'Ảnh trong'],
        ])
        ->call('normalizeCatalogRowKey', 'catalog_exam_indications_json', 0)
        ->call('normalizeCatalogRowKey', 'catalog_exam_indications_json', 1);

    expect($component->get('catalogEditors.catalog_exam_indications_json.0.key'))->toBe('ext')
        ->and($component->get('catalogEditors.catalog_exam_indications_json.1.key'))->toBe('int');
});

it('auto generates catalog key from label for new row', function (): void {
    $component = Livewire::test(IntegrationSettings::class)
        ->set('catalogEditors.catalog_customer_sources_json', [
            ['key' => '', 'label' => 'Tư vấn trực tiếp', 'enabled' => true],
        ])
        ->call('syncCatalogRowFromLabel', 'catalog_customer_sources_json', 0);

    expect($component->get('catalogEditors.catalog_customer_sources_json.0.key'))->toBe('tu_van_truc_tiep');
});

it('auto generates and persists key on save when key is empty', function (): void {
    Livewire::test(IntegrationSettings::class)
        ->set('catalogEditors.catalog_customer_sources_json', [
            ['key' => '', 'label' => 'Nguồn thử nghiệm', 'enabled' => true],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $saved = ClinicSetting::getValue('catalog.customer_sources', []);

    expect($saved)->toBe([
        'nguon_thu_nghiem' => 'Nguồn thử nghiệm',
    ]);
});

it('allows deleting exam indication rows including ext and int', function (): void {
    $component = Livewire::test(IntegrationSettings::class)
        ->set('catalogEditors.catalog_exam_indications_json', [
            ['key' => 'ext', 'label' => 'Ảnh (ext)'],
            ['key' => 'int', 'label' => 'Ảnh (int)'],
            ['key' => 'panorama', 'label' => 'Panorama'],
        ])
        ->call('removeCatalogRow', 'catalog_exam_indications_json', 0);

    $rows = $component->get('catalogEditors.catalog_exam_indications_json');
    $keys = collect($rows)->pluck('key')->values()->all();

    expect($rows)->toHaveCount(2)
        ->and($keys)->not->toContain('ext')
        ->and($keys)->toContain('int')
        ->and($keys)->toContain('panorama');
});

it('does not re-insert ext and int when saving exam indication catalog', function (): void {
    Livewire::test(IntegrationSettings::class)
        ->set('catalogEditors.catalog_exam_indications_json', [
            ['key' => 'panorama', 'label' => 'Panorama'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $saved = ClinicSetting::getValue('catalog.exam_indications', []);

    expect($saved)
        ->toMatchArray([
            'panorama' => 'Panorama',
        ]);
});

it('does not persist disabled catalog rows', function (): void {
    Livewire::test(IntegrationSettings::class)
        ->set('catalogEditors.catalog_customer_sources_json', [
            ['key' => 'walkin', 'label' => 'Khách vãng lai', 'enabled' => true],
            ['key' => 'facebook', 'label' => 'Facebook', 'enabled' => false],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $saved = ClinicSetting::getValue('catalog.customer_sources', []);

    expect($saved)
        ->toBe(['walkin' => 'Khách vãng lai'])
        ->and(array_key_exists('facebook', $saved))->toBeFalse();
});

it('exposes branding provider fields in integration settings', function () {
    $page = app(IntegrationSettings::class);
    $providers = collect($page->getProviders());

    $brandingProvider = $providers->firstWhere('group', 'branding');

    expect($brandingProvider)->not->toBeNull();

    $brandingKeys = collect($brandingProvider['fields'] ?? [])
        ->pluck('key')
        ->values()
        ->all();

    expect($brandingKeys)
        ->toContain('branding.clinic_name')
        ->toContain('branding.logo_url')
        ->toContain('branding.address')
        ->toContain('branding.phone')
        ->toContain('branding.email')
        ->toContain('branding.button_bg_color')
        ->toContain('branding.button_bg_hover_color')
        ->toContain('branding.button_text_color');
});
