<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;

it('resolves vietnamese breezy browser sessions translations', function (): void {
    App::setLocale('vi');

    $keys = [
        'filament-breezy::default.profile.subheading',
        'filament-breezy::default.profile.browser_sessions.heading',
        'filament-breezy::default.profile.browser_sessions.content',
        'filament-breezy::default.profile.browser_sessions.logout_other_sessions',
    ];

    foreach ($keys as $key) {
        $translated = __($key);

        expect(Lang::has($key))->toBeTrue()
            ->and($translated)->not()->toBe($key);
    }
});

it('resolves vietnamese passkeys translations', function (): void {
    App::setLocale('vi');

    $keys = [
        'passkeys::passkeys.passkeys',
        'passkeys::passkeys.create',
        'passkeys::passkeys.delete',
        'passkeys::passkeys.last_used',
        'passkeys::passkeys.not_used_yet',
        'filament-passkeys::passkeys.description',
    ];

    foreach ($keys as $key) {
        $translated = __($key);

        expect(Lang::has($key))->toBeTrue()
            ->and($translated)->not()->toBe($key);
    }
});

it('resolves vietnamese filament firewall translations', function (): void {
    App::setLocale('vi');

    $keys = [
        'filament-firewall::filament-firewall.filament.resource.ip.navigationLabel',
        'filament-firewall::filament-firewall.filament.resource.ip.pluralModelLabel',
        'filament-firewall::filament-firewall.table.column.ip',
        'filament-firewall::filament-firewall.action.addMyIp',
        'filament-firewall::filament-firewall.labels.allow',
    ];

    foreach ($keys as $key) {
        $translated = __($key);

        expect(Lang::has($key))->toBeTrue()
            ->and($translated)->not()->toBe($key);
    }
});
