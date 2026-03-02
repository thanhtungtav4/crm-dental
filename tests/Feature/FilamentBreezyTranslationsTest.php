<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;

it('resolves critical breezy translation keys in vietnamese locale', function (): void {
    App::setLocale('vi');

    $keys = [
        'filament-breezy::default.profile.subheading',
        'filament-breezy::default.profile.browser_sessions.content',
        'filament-breezy::default.profile.browser_sessions.device',
        'filament-breezy::default.profile.browser_sessions.logout_other_sessions',
    ];

    foreach ($keys as $key) {
        $translated = trans($key);

        expect(Lang::has($key))->toBeTrue()
            ->and($translated)->not()->toBe($key);
    }
});
