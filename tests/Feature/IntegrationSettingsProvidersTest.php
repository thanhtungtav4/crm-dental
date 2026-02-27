<?php

use App\Filament\Pages\IntegrationSettings;
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
        ->toContain('wire:model.blur="settings.emr_provider"');
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
