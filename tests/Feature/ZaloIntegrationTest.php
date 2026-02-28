<?php

use App\Filament\Pages\IntegrationSettings;
use App\Models\ClinicSetting;
use App\Services\ZaloIntegrationService;
use Livewire\Livewire;

it('rejects zalo webhook verification when token does not match', function (): void {
    ClinicSetting::setValue('zalo.webhook_token', 'secure-token-12345678901234567890', [
        'group' => 'zalo',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    $this->getJson('/api/v1/integrations/zalo/webhook?hub_verify_token=invalid&hub_challenge=abc')
        ->assertForbidden();
});

it('returns challenge when zalo webhook verification succeeds', function (): void {
    ClinicSetting::setValue('zalo.webhook_token', 'secure-token-12345678901234567890', [
        'group' => 'zalo',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    $this->get('/api/v1/integrations/zalo/webhook?hub_verify_token=secure-token-12345678901234567890&hub_challenge=hello-zalo')
        ->assertOk()
        ->assertSeeText('hello-zalo');
});

it('accepts webhook payload with valid verify token', function (): void {
    ClinicSetting::setValue('zalo.webhook_token', 'secure-token-12345678901234567890', [
        'group' => 'zalo',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    $this->postJson('/api/v1/integrations/zalo/webhook', [
        'verify_token' => 'secure-token-12345678901234567890',
        'event_name' => 'user_send_text',
        'timestamp' => 1735689600,
    ])->assertSuccessful()
        ->assertJsonPath('ok', true);
});

it('validates required zalo fields when enabling zalo oa', function (): void {
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

it('validates zns tokens and templates when zns is enabled', function (): void {
    Livewire::test(IntegrationSettings::class)
        ->set('settings.zns_enabled', true)
        ->set('settings.zns_access_token', '')
        ->set('settings.zns_refresh_token', '')
        ->set('settings.zns_template_appointment', '')
        ->set('settings.zns_template_payment', '')
        ->call('save')
        ->assertHasErrors([
            'settings.zns_access_token',
            'settings.zns_refresh_token',
            'settings.zns_template_appointment',
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
