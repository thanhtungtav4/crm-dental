<?php

use App\Models\ClinicSetting;
use App\Services\IntegrationProviderHealthReadModelService;

it('builds shared provider health cards and counts from runtime settings', function (): void {
    configureIntegrationProviderHealth('zalo.enabled', true, 'boolean', 'zalo');
    configureIntegrationProviderHealth('zalo.oa_id', 'oa-provider-health', 'text', 'zalo');
    configureIntegrationProviderHealth('zalo.app_id', 'app-provider-health', 'text', 'zalo');
    configureIntegrationProviderHealth('zalo.app_secret', 'secret-provider-health', 'text', 'zalo', isSecret: true);
    configureIntegrationProviderHealth('zalo.webhook_token', 'secure-token-provider-health-1234567890', 'text', 'zalo', isSecret: true);

    configureIntegrationProviderHealth('zns.enabled', true, 'boolean', 'zns');
    configureIntegrationProviderHealth('zns.access_token', '', 'text', 'zns', isSecret: true);
    configureIntegrationProviderHealth('zns.refresh_token', 'zns-refresh-token', 'text', 'zns', isSecret: true);
    configureIntegrationProviderHealth('zns.send_endpoint', 'https://business.openapi.zalo.me/message/template', 'text', 'zns');
    configureIntegrationProviderHealth('zns.template_appointment', 'tpl-zns-health', 'text', 'zns');

    configureIntegrationProviderHealth('google_calendar.enabled', true, 'boolean', 'google_calendar');
    configureIntegrationProviderHealth('google_calendar.client_id', 'gcal-client-id', 'text', 'google_calendar');
    configureIntegrationProviderHealth('google_calendar.client_secret', 'gcal-client-secret', 'text', 'google_calendar', isSecret: true);
    configureIntegrationProviderHealth('google_calendar.refresh_token', 'gcal-refresh-token', 'text', 'google_calendar', isSecret: true);
    configureIntegrationProviderHealth('google_calendar.calendar_id', '', 'text', 'google_calendar');

    $service = app(IntegrationProviderHealthReadModelService::class);
    $cards = collect($service->cards())->keyBy('key');
    $counts = $service->counts();

    expect($cards->keys()->all())->toBe([
        'zalo_oa',
        'zns',
        'google_calendar',
        'emr',
    ])
        ->and($cards->get('zalo_oa')['status'])->toBe('Healthy')
        ->and($cards->get('zalo_oa')['webhook_url'])->toContain('/api/v1/integrations/zalo/webhook')
        ->and($cards->get('zns')['runtime_error_message'])->toBe('Thiếu ZNS access token.')
        ->and($cards->get('google_calendar')['runtime_error_message'])->toBe('Google Calendar chưa cấu hình đầy đủ (client_id/client_secret/refresh_token/calendar_id).')
        ->and($cards->get('emr')['status'])->toBe('Disabled')
        ->and($counts)->toBe([
            'healthy' => 1,
            'degraded' => 2,
            'disabled' => 1,
        ]);
});

function configureIntegrationProviderHealth(
    string $key,
    bool|int|string $value,
    string $valueType,
    string $group,
    bool $isSecret = false,
): void {
    ClinicSetting::setValue($key, $value, [
        'group' => $group,
        'value_type' => $valueType,
        'is_secret' => $isSecret,
        'is_active' => true,
    ]);
}
