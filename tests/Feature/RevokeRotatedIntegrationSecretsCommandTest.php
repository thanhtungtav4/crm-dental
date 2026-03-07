<?php

use App\Models\AuditLog;
use App\Models\ClinicSetting;
use App\Services\IntegrationSecretRotationService;

it('supports dry run when expired integration secret grace tokens exist', function (): void {
    ClinicSetting::setValue('web_lead.api_token', 'dry-run-old-web-token', [
        'group' => 'web_lead',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    ClinicSetting::setValue('web_lead.api_token_grace_minutes', 5, [
        'group' => 'web_lead',
        'value_type' => 'integer',
    ]);

    app(IntegrationSecretRotationService::class)->rotate(
        settingKey: 'web_lead.api_token',
        newSecret: 'dry-run-new-web-token',
        reason: 'Dry run rotation test.',
    );

    ClinicSetting::setValue('web_lead.api_token_grace_expires_at', now()->subMinute()->toISOString(), [
        'group' => 'web_lead',
        'value_type' => 'text',
    ]);

    $this->artisan('integrations:revoke-rotated-secrets', [
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('dry_run=yes')
        ->assertSuccessful();

    expect(ClinicSetting::getValue('web_lead.api_token_previous_secret'))->toBe('dry-run-old-web-token');

    $audit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_AUTOMATION)
        ->where('action', AuditLog::ACTION_RUN)
        ->where('metadata->command', 'integrations:revoke-rotated-secrets')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and((int) data_get($audit?->metadata, 'summary.total_expired'))->toBeGreaterThanOrEqual(1)
        ->and((int) data_get($audit?->metadata, 'summary.revoked'))->toBe(0);
});

it('revokes expired zalo webhook grace token and keeps the active token valid', function (): void {
    ClinicSetting::setValue('zalo.enabled', true, [
        'group' => 'zalo',
        'value_type' => 'boolean',
    ]);

    ClinicSetting::setValue('zalo.webhook_token', 'zalo-old-verify-token-1234567890', [
        'group' => 'zalo',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    ClinicSetting::setValue('zalo.webhook_token_grace_minutes', 5, [
        'group' => 'zalo',
        'value_type' => 'integer',
    ]);

    app(IntegrationSecretRotationService::class)->rotate(
        settingKey: 'zalo.webhook_token',
        newSecret: 'zalo-new-verify-token-1234567890',
        reason: 'Zalo webhook rotation command test.',
    );

    $this->get('/api/v1/integrations/zalo/webhook?hub_verify_token=zalo-old-verify-token-1234567890&hub_challenge=old-token-ok')
        ->assertOk()
        ->assertSeeText('old-token-ok');

    ClinicSetting::setValue('zalo.webhook_token_grace_expires_at', now()->subMinute()->toISOString(), [
        'group' => 'zalo',
        'value_type' => 'text',
    ]);

    $this->artisan('integrations:revoke-rotated-secrets')
        ->expectsOutputToContain('dry_run=no')
        ->assertSuccessful();

    expect(ClinicSetting::getValue('zalo.webhook_token_previous_secret'))->toBeNull();

    $this->getJson('/api/v1/integrations/zalo/webhook?hub_verify_token=zalo-old-verify-token-1234567890&hub_challenge=old-token-blocked')
        ->assertForbidden();

    $this->get('/api/v1/integrations/zalo/webhook?hub_verify_token=zalo-new-verify-token-1234567890&hub_challenge=new-token-ok')
        ->assertOk()
        ->assertSeeText('new-token-ok');

    $audit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_AUTOMATION)
        ->where('action', AuditLog::ACTION_RUN)
        ->where('metadata->command', 'integrations:revoke-rotated-secrets')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and((int) data_get($audit?->metadata, 'summary.total_expired'))->toBeGreaterThanOrEqual(1)
        ->and((int) data_get($audit?->metadata, 'summary.revoked'))->toBeGreaterThanOrEqual(1);
});
