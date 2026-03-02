<?php

it('registers passkeys authentication routes', function (): void {
    expect(route('passkeys.authentication_options'))->toContain('/passkeys/authentication-options')
        ->and(route('passkeys.login'))->toContain('/passkeys/authenticate');
});

it('renders passkey login action on filament login page', function (): void {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('/passkeys/authentication-options', false)
        ->assertSee('/passkeys/authenticate', false)
        ->assertSee('passkey', false);
});
