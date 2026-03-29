<?php

use App\Filament\Pages\IntegrationSettings;
use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\User;
use Livewire\Livewire;

function configureFacebookWebhookRuntime(): void
{
    ClinicSetting::setValue('facebook.enabled', true, [
        'group' => 'facebook',
        'value_type' => 'boolean',
    ]);

    ClinicSetting::setValue('facebook.webhook_verify_token', 'facebook-verify-token-123456789012345', [
        'group' => 'facebook',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    ClinicSetting::setValue('facebook.app_secret', 'facebook_app_secret_for_signature_001', [
        'group' => 'facebook',
        'value_type' => 'text',
        'is_secret' => true,
    ]);
}

/**
 * @param  array<string, mixed>  $payload
 */
function signFacebookWebhookPayload(array $payload): string
{
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return 'sha256='.hash_hmac('sha256', is_string($payloadJson) ? $payloadJson : '{}', 'facebook_app_secret_for_signature_001');
}

it('rejects facebook webhook verification when integration is disabled', function (): void {
    $this->getJson('/api/v1/integrations/facebook/webhook?hub_mode=subscribe&hub_verify_token=invalid&hub_challenge=abc')
        ->assertServiceUnavailable()
        ->assertJsonPath('message', 'Facebook Messenger integration chưa bật.');
});

it('returns challenge when facebook webhook verification succeeds', function (): void {
    configureFacebookWebhookRuntime();

    $this->get('/api/v1/integrations/facebook/webhook?hub_mode=subscribe&hub_verify_token=facebook-verify-token-123456789012345&hub_challenge=hello-facebook')
        ->assertOk()
        ->assertSeeText('hello-facebook');
});

it('rejects facebook webhook payload when signature is missing or invalid', function (): void {
    configureFacebookWebhookRuntime();

    $payload = [
        'object' => 'page',
        'entry' => [],
    ];

    $this->postJson('/api/v1/integrations/facebook/webhook', $payload)
        ->assertForbidden()
        ->assertJsonPath('message', 'Missing webhook signature.');

    $this->withHeaders([
        'X-Hub-Signature-256' => 'sha256=invalid-signature',
    ])->postJson('/api/v1/integrations/facebook/webhook', $payload)
        ->assertForbidden()
        ->assertJsonPath('message', 'Invalid webhook signature.');
});

it('accepts facebook webhook payload with a valid signature', function (): void {
    configureFacebookWebhookRuntime();

    $payload = [
        'object' => 'page',
        'entry' => [
            [
                'id' => 'page_001',
                'time' => 1_735_689_600_000,
                'messaging' => [
                    [
                        'sender' => ['id' => 'psid_001'],
                        'recipient' => ['id' => 'page_001'],
                        'timestamp' => 1_735_689_600_000,
                        'message' => [
                            'mid' => 'mid.facebook.001',
                            'text' => 'Xin chao Facebook',
                        ],
                    ],
                ],
            ],
        ],
    ];

    $this->withHeaders([
        'X-Hub-Signature-256' => signFacebookWebhookPayload($payload),
    ])->postJson('/api/v1/integrations/facebook/webhook', $payload)
        ->assertSuccessful()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('duplicate', false)
        ->assertJsonPath('processed_events', 1);
});

it('applies throttle middleware to facebook webhook endpoint', function (): void {
    $route = app('router')->getRoutes()->getByName('api.v1.integrations.facebook.webhook');

    expect($route)->not->toBeNull()
        ->and($route?->middleware())->toContain('throttle:facebook-webhook');
});

it('validates required facebook fields when enabling messenger inbox', function (): void {
    Branch::factory()->create([
        'code' => 'BR-FB-SETTINGS',
        'active' => true,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin);

    Livewire::test(IntegrationSettings::class)
        ->set('settings.facebook_enabled', true)
        ->set('settings.facebook_page_id', '')
        ->set('settings.facebook_app_id', '')
        ->set('settings.facebook_app_secret', '')
        ->set('settings.facebook_webhook_verify_token', 'short-token')
        ->set('settings.facebook_page_access_token', '')
        ->set('settings.facebook_send_endpoint', '')
        ->set('settings.facebook_inbox_default_branch_code', 'MISSING-BRANCH')
        ->call('save')
        ->assertHasErrors([
            'settings.facebook_page_id',
            'settings.facebook_app_id',
            'settings.facebook_app_secret',
            'settings.facebook_webhook_verify_token',
            'settings.facebook_page_access_token',
            'settings.facebook_send_endpoint',
            'settings.facebook_inbox_default_branch_code',
        ]);
});
