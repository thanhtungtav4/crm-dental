<?php

use App\Models\ClinicSetting;
use App\Services\IntegrationProviderRuntimeGate;

it('returns skip states for disabled provider command lanes', function (): void {
    $gate = app(IntegrationProviderRuntimeGate::class);

    expect($gate->emrSyncCommandStatus())->toBe([
        'state' => 'skip',
        'message' => 'EMR integration đang tắt. Không có dữ liệu cần sync.',
    ])
        ->and($gate->googleCalendarSyncCommandStatus())->toBe([
            'state' => 'skip',
            'message' => 'Google Calendar integration đang tắt. Không có dữ liệu cần sync.',
        ])
        ->and($gate->znsAutomationSyncCommandStatus())->toBe([
            'state' => 'skip',
            'message' => 'ZNS integration đang tắt. Không có event automation cần xử lý.',
        ])
        ->and($gate->znsCampaignCommandStatus())->toBe([
            'state' => 'skip',
            'message' => 'ZNS đang tắt, bỏ qua chạy campaign.',
        ])
        ->and($gate->znsCampaignWorkflowStatus())->toBe([
            'state' => 'fail',
            'message' => 'ZNS đang tắt, không thể chạy campaign.',
        ]);
});

it('surfaces provider runtime failures through shared command gates', function (): void {
    configureRuntimeGateSetting('emr.enabled', true, 'boolean', 'emr');
    configureRuntimeGateSetting('emr.base_url', '', 'text', 'emr');
    configureRuntimeGateSetting('emr.api_key', '', 'text', 'emr', isSecret: true);

    configureRuntimeGateSetting('google_calendar.enabled', true, 'boolean', 'google_calendar');
    configureRuntimeGateSetting('google_calendar.client_id', 'gcal-client-id', 'text', 'google_calendar');
    configureRuntimeGateSetting('google_calendar.client_secret', 'gcal-client-secret', 'text', 'google_calendar', isSecret: true);
    configureRuntimeGateSetting('google_calendar.refresh_token', 'gcal-refresh-token', 'text', 'google_calendar', isSecret: true);
    configureRuntimeGateSetting('google_calendar.calendar_id', '', 'text', 'google_calendar');

    configureRuntimeGateSetting('zns.enabled', true, 'boolean', 'zns');
    configureRuntimeGateSetting('zns.access_token', '', 'text', 'zns', isSecret: true);
    configureRuntimeGateSetting('zns.send_endpoint', 'https://business.openapi.zalo.me/message/template', 'text', 'zns');

    $gate = app(IntegrationProviderRuntimeGate::class);

    expect($gate->emrSyncCommandStatus())->toBe([
        'state' => 'fail',
        'message' => 'EMR chưa cấu hình đầy đủ (base_url/api_key).',
    ])
        ->and($gate->googleCalendarSyncCommandStatus())->toBe([
            'state' => 'fail',
            'message' => 'Google Calendar chưa cấu hình đầy đủ (client_id/client_secret/refresh_token/calendar_id).',
        ])
        ->and($gate->znsAutomationSyncCommandStatus())->toBe([
            'state' => 'fail',
            'message' => 'Thiếu ZNS access token. Không thể xử lý event automation.',
        ])
        ->and($gate->znsCampaignCommandStatus())->toBe([
            'state' => 'fail',
            'message' => 'Thiếu ZNS access token. Không thể chạy campaign ZNS.',
        ])
        ->and($gate->znsCampaignWorkflowStatus())->toBe([
            'state' => 'fail',
            'message' => 'Thiếu ZNS access token.',
        ])
        ->and($gate->allowsEmrPublish())->toBeFalse()
        ->and($gate->allowsGoogleCalendarPublish())->toBeFalse()
        ->and($gate->allowsZnsPublish())->toBeFalse();
});

it('allows healthy providers to publish through the shared runtime gate', function (): void {
    configureRuntimeGateSetting('google_calendar.enabled', true, 'boolean', 'google_calendar');
    configureRuntimeGateSetting('google_calendar.client_id', 'gcal-client-id', 'text', 'google_calendar');
    configureRuntimeGateSetting('google_calendar.client_secret', 'gcal-client-secret', 'text', 'google_calendar', isSecret: true);
    configureRuntimeGateSetting('google_calendar.refresh_token', 'gcal-refresh-token', 'text', 'google_calendar', isSecret: true);
    configureRuntimeGateSetting('google_calendar.calendar_id', 'crm-calendar@example.com', 'text', 'google_calendar');
    configureRuntimeGateSetting('google_calendar.sync_mode', 'one_way_to_google', 'text', 'google_calendar');

    configureRuntimeGateSetting('emr.enabled', true, 'boolean', 'emr');
    configureRuntimeGateSetting('emr.base_url', 'https://emr.example.test', 'text', 'emr');
    configureRuntimeGateSetting('emr.api_key', 'emr-api-key', 'text', 'emr', isSecret: true);

    configureRuntimeGateSetting('zns.enabled', true, 'boolean', 'zns');
    configureRuntimeGateSetting('zns.access_token', 'zns-access-token', 'text', 'zns', isSecret: true);
    configureRuntimeGateSetting('zns.send_endpoint', 'https://business.openapi.zalo.me/message/template', 'text', 'zns');

    $gate = app(IntegrationProviderRuntimeGate::class);

    expect($gate->googleCalendarSyncCommandStatus())->toBe([
        'state' => 'ready',
        'message' => null,
    ])
        ->and($gate->allowsGoogleCalendarPublish())->toBeTrue()
        ->and($gate->allowsEmrPublish())->toBeTrue()
        ->and($gate->allowsZnsPublish())->toBeTrue();
});

function configureRuntimeGateSetting(
    string $key,
    array|bool|int|string $value,
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
