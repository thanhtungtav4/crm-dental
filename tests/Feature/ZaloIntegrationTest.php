<?php

use App\Filament\Pages\IntegrationSettings;
use App\Models\ClinicSetting;
use App\Models\User;
use App\Services\ZaloIntegrationService;
use Livewire\Livewire;

function configureZaloWebhookRuntime(): void
{
    ClinicSetting::setValue('zalo.enabled', true, [
        'group' => 'zalo',
        'value_type' => 'boolean',
    ]);

    ClinicSetting::setValue('zalo.webhook_token', 'secure-token-12345678901234567890', [
        'group' => 'zalo',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    ClinicSetting::setValue('zalo.app_secret', 'app_secret_for_webhook_signature_001', [
        'group' => 'zalo',
        'value_type' => 'text',
        'is_secret' => true,
    ]);
}

/**
 * @param  array<string, mixed>  $payload
 */
function signZaloWebhookPayload(array $payload): string
{
    $normalize = static function (mixed $value) use (&$normalize): mixed {
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $child) {
            $value[$key] = $normalize($child);
        }

        ksort($value);

        return $value;
    };

    $payloadJson = json_encode($normalize($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (! is_string($payloadJson) || trim($payloadJson) === '') {
        $payloadJson = '{}';
    }

    return hash_hmac('sha256', $payloadJson, 'app_secret_for_webhook_signature_001');
}

it('rejects zalo webhook verification when token does not match', function (): void {
    configureZaloWebhookRuntime();

    $this->getJson('/api/v1/integrations/zalo/webhook?hub_verify_token=invalid&hub_challenge=abc')
        ->assertForbidden();
});

it('rejects zalo webhook verification when integration is disabled', function (): void {
    $this->getJson('/api/v1/integrations/zalo/webhook?hub_verify_token=invalid&hub_challenge=abc')
        ->assertServiceUnavailable()
        ->assertJsonPath('message', 'Zalo OA integration chưa bật.');
});

it('rejects zalo webhook verification when webhook token is missing', function (): void {
    ClinicSetting::setValue('zalo.enabled', true, [
        'group' => 'zalo',
        'value_type' => 'boolean',
    ]);

    ClinicSetting::setValue('zalo.webhook_token', '', [
        'group' => 'zalo',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    $this->getJson('/api/v1/integrations/zalo/webhook?hub_verify_token=invalid&hub_challenge=abc')
        ->assertServiceUnavailable()
        ->assertJsonPath('message', 'Zalo webhook token chưa được cấu hình.');
});

it('returns challenge when zalo webhook verification succeeds', function (): void {
    configureZaloWebhookRuntime();

    $this->get('/api/v1/integrations/zalo/webhook?hub_verify_token=secure-token-12345678901234567890&hub_challenge=hello-zalo')
        ->assertOk()
        ->assertSeeText('hello-zalo');
});

it('accepts webhook payload with valid verify token', function (): void {
    configureZaloWebhookRuntime();

    $payload = [
        'verify_token' => 'secure-token-12345678901234567890',
        'event_name' => 'user_send_text',
        'timestamp' => 1735689600,
        'sender' => ['id' => 'sender_001'],
        'message' => ['text' => 'Xin chào'],
    ];

    $this->withHeaders([
        'X-Zalo-Signature' => signZaloWebhookPayload($payload),
    ])->postJson('/api/v1/integrations/zalo/webhook', $payload)
        ->assertSuccessful()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('duplicate', false);
});

it('ignores duplicated webhook payload by idempotent fingerprint', function (): void {
    configureZaloWebhookRuntime();

    $payload = [
        'verify_token' => 'secure-token-12345678901234567890',
        'event_id' => 'event_001',
        'event_name' => 'user_send_text',
        'timestamp' => 1735689600,
        'sender' => ['id' => 'sender_001'],
        'message' => ['text' => 'Nội dung A'],
    ];

    $this->withHeaders([
        'X-Zalo-Signature' => signZaloWebhookPayload($payload),
    ])->postJson('/api/v1/integrations/zalo/webhook', $payload)
        ->assertSuccessful()
        ->assertJsonPath('duplicate', false);

    $this->withHeaders([
        'X-Zalo-Signature' => signZaloWebhookPayload($payload),
    ])->postJson('/api/v1/integrations/zalo/webhook', $payload)
        ->assertSuccessful()
        ->assertJsonPath('duplicate', true);
});

it('rejects webhook payload when signature is missing or invalid', function (): void {
    configureZaloWebhookRuntime();

    $payload = [
        'event_name' => 'user_send_text',
        'timestamp' => 1735689600,
        'sender' => ['id' => 'sender_001'],
        'message' => ['text' => 'Test chữ ký'],
    ];

    $this->postJson('/api/v1/integrations/zalo/webhook', $payload)
        ->assertForbidden()
        ->assertJsonPath('message', 'Missing webhook signature.');

    $this->withHeaders([
        'X-Zalo-Signature' => 'invalid-signature',
    ])->postJson('/api/v1/integrations/zalo/webhook', $payload)
        ->assertForbidden()
        ->assertJsonPath('message', 'Invalid webhook signature.');
});

it('rejects webhook payload when signature verification runtime is misconfigured', function (): void {
    ClinicSetting::setValue('zalo.enabled', true, [
        'group' => 'zalo',
        'value_type' => 'boolean',
    ]);
    ClinicSetting::setValue('zalo.webhook_token', 'secure-token-12345678901234567890', [
        'group' => 'zalo',
        'value_type' => 'text',
        'is_secret' => true,
    ]);
    ClinicSetting::setValue('zalo.app_secret', '', [
        'group' => 'zalo',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    $payload = [
        'event_name' => 'user_send_text',
        'timestamp' => 1735689600,
        'sender' => ['id' => 'sender_001'],
        'message' => ['text' => 'Thiếu app secret'],
    ];

    $this->postJson('/api/v1/integrations/zalo/webhook', $payload)
        ->assertServiceUnavailable()
        ->assertJsonPath('message', 'Webhook signature verification misconfigured.');
});

it('does not collide fingerprint when same timestamp but different payload content', function (): void {
    configureZaloWebhookRuntime();

    $basePayload = [
        'event_name' => 'user_send_text',
        'timestamp' => 1735689600,
        'oa_id' => 'oa_001',
        'sender' => ['id' => 'sender_001'],
    ];

    $firstPayload = [
        ...$basePayload,
        'message' => ['text' => 'Nội dung 1'],
    ];

    $secondPayload = [
        ...$basePayload,
        'message' => ['text' => 'Nội dung 2'],
    ];

    $this->withHeaders([
        'X-Zalo-Signature' => signZaloWebhookPayload($firstPayload),
    ])->postJson('/api/v1/integrations/zalo/webhook', $firstPayload)
        ->assertSuccessful()
        ->assertJsonPath('duplicate', false);

    $this->withHeaders([
        'X-Zalo-Signature' => signZaloWebhookPayload($secondPayload),
    ])->postJson('/api/v1/integrations/zalo/webhook', $secondPayload)
        ->assertSuccessful()
        ->assertJsonPath('duplicate', false);
});

it('applies throttle middleware to zalo webhook endpoint', function (): void {
    $route = app('router')->getRoutes()->getByName('api.v1.integrations.zalo.webhook');

    expect($route)->not->toBeNull()
        ->and($route?->middleware())->toContain('throttle:zalo-webhook');
});

it('validates required zalo fields when enabling zalo oa', function (): void {
    actingAsZaloIntegrationSettingsAdmin();

    Livewire::test(IntegrationSettings::class)
        ->set('settings.zalo_enabled', true)
        ->set('settings.zalo_oa_id', '')
        ->set('settings.zalo_app_id', '')
        ->set('settings.zalo_app_secret', '')
        ->set('settings.zalo_webhook_token', 'short-token')
        ->call('save')
        ->assertHasErrors([
            'settings.zalo_oa_id',
            'settings.zalo_app_id',
            'settings.zalo_app_secret',
            'settings.zalo_webhook_token',
        ]);
});

it('validates conversation inbox runtime fields when enabling zalo oa inbox', function (): void {
    actingAsZaloIntegrationSettingsAdmin();

    Livewire::test(IntegrationSettings::class)
        ->set('settings.zalo_enabled', true)
        ->set('settings.zalo_oa_id', 'oa_001')
        ->set('settings.zalo_app_id', 'app_001')
        ->set('settings.zalo_app_secret', 'secret_001')
        ->set('settings.zalo_webhook_token', 'secure-token-12345678901234567890')
        ->set('settings.zalo_access_token', '')
        ->set('settings.zalo_inbox_default_branch_code', 'MISSING-BRANCH')
        ->set('settings.zalo_inbox_polling_seconds', 99)
        ->call('save')
        ->assertHasErrors([
            'settings.zalo_access_token',
            'settings.zalo_inbox_default_branch_code',
            'settings.zalo_inbox_polling_seconds',
        ]);
});

it('exposes conversation inbox runtime fields in zalo integration settings', function (): void {
    $page = app(IntegrationSettings::class);
    $providers = collect($page->getProviders());
    $zaloProvider = $providers->firstWhere('group', 'zalo');

    expect($zaloProvider)->not->toBeNull();

    $zaloFields = collect($zaloProvider['fields'] ?? [])
        ->keyBy('key');

    expect($zaloFields->has('zalo.access_token'))->toBeTrue()
        ->and($zaloFields->has('zalo.send_endpoint'))->toBeTrue()
        ->and($zaloFields->has('zalo.inbox_default_branch_code'))->toBeTrue()
        ->and($zaloFields->has('zalo.inbox_polling_seconds'))->toBeTrue();
});

it('validates zns tokens and templates when zns is enabled', function (): void {
    actingAsZaloIntegrationSettingsAdmin();

    Livewire::test(IntegrationSettings::class)
        ->set('settings.zns_enabled', true)
        ->set('settings.zns_access_token', '')
        ->set('settings.zns_refresh_token', '')
        ->set('settings.zns_template_appointment', '')
        ->set('settings.zns_template_payment', '')
        ->set('settings.zns_send_endpoint', '')
        ->call('save')
        ->assertHasErrors([
            'settings.zns_access_token',
            'settings.zns_refresh_token',
            'settings.zns_template_appointment',
            'settings.zns_send_endpoint',
        ]);
});

it('validates zns automation templates when corresponding toggles are enabled', function (): void {
    actingAsZaloIntegrationSettingsAdmin();

    Livewire::test(IntegrationSettings::class)
        ->set('settings.zns_enabled', true)
        ->set('settings.zns_access_token', 'zns_access_token_001')
        ->set('settings.zns_refresh_token', 'zns_refresh_token_001')
        ->set('settings.zns_send_endpoint', 'https://business.openapi.zalo.me/message/template')
        ->set('settings.zns_auto_send_lead_welcome', true)
        ->set('settings.zns_template_lead_welcome', '')
        ->set('settings.zns_auto_send_appointment_reminder', true)
        ->set('settings.zns_template_appointment', '')
        ->set('settings.zns_auto_send_birthday', true)
        ->set('settings.zns_template_birthday', '')
        ->call('save')
        ->assertHasErrors([
            'settings.zns_template_lead_welcome',
            'settings.zns_template_appointment',
            'settings.zns_template_birthday',
        ]);
});

it('produces zalo readiness report with webhook endpoint', function (): void {
    ClinicSetting::setValue('zalo.enabled', true, ['group' => 'zalo', 'value_type' => 'boolean']);
    ClinicSetting::setValue('zalo.oa_id', 'oa_001', ['group' => 'zalo', 'value_type' => 'text']);
    ClinicSetting::setValue('zalo.app_id', 'app_001', ['group' => 'zalo', 'value_type' => 'text']);
    ClinicSetting::setValue('zalo.app_secret', 'app_secret_001', ['group' => 'zalo', 'value_type' => 'text', 'is_secret' => true]);
    ClinicSetting::setValue('zalo.webhook_token', 'secure-token-12345678901234567890', ['group' => 'zalo', 'value_type' => 'text', 'is_secret' => true]);

    $report = app(ZaloIntegrationService::class)->auditOaReadiness();

    expect($report['score'])->toBeGreaterThanOrEqual(80)
        ->and($report['webhook_url'])->toContain('/api/v1/integrations/zalo/webhook');
});

function actingAsZaloIntegrationSettingsAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    test()->actingAs($admin);

    return $admin;
}
