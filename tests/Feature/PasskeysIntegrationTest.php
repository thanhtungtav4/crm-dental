<?php

it('registers passkeys authentication routes', function (): void {
    expect(route('passkeys.authentication_options'))->toContain('/passkeys/authentication-options')
        ->and(route('passkeys.login'))->toContain('/passkeys/authenticate')
        ->and(route('passkeys.authentication_options', [], false))->toBe('/passkeys/authentication-options')
        ->and(route('passkeys.login', [], false))->toBe('/passkeys/authenticate');
});

it('renders passkey login action on filament login page', function (): void {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('\\/passkeys\\/authentication-options', false)
        ->assertSee('/passkeys/authenticate', false)
        ->assertSee('passkey', false);
});

it('uses secure proxy headers and relative passkey routes on the login page', function (): void {
    $this->withServerVariables([
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_X_FORWARDED_FOR' => '127.0.0.1',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_HOST' => 'crm.nttung.dev',
        'HTTP_X_FORWARDED_PORT' => '443',
    ])->get('/admin/login')
        ->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
        ->assertSee('\\/passkeys\\/authentication-options', false)
        ->assertSee('/passkeys/authenticate', false)
        ->assertDontSee('http://crm.nttung.dev/passkeys/authentication-options', false)
        ->assertDontSee('http://crm.nttung.dev/passkeys/authenticate', false);
});
