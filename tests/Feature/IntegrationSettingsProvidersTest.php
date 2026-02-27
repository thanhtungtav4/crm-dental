<?php

use App\Filament\Pages\IntegrationSettings;

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
