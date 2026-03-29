<?php

use App\Models\ClinicSetting;
use App\Services\IntegrationProviderRuntimeGate;

it('returns skip states for disabled provider command lanes', function (): void {
    $gate = app(IntegrationProviderRuntimeGate::class);

    expect($gate->emrSyncCommandStatus())->toBe([
        'state' => 'skip',
        'message' => 'EMR integration đang tắt. Không có dữ liệu cần sync.',
    ])
        ->and($gate->emrInternalIngressStatus())->toBe([
            'state' => 'skip',
            'message' => 'EMR internal API chưa được bật.',
        ])
        ->and($gate->zaloWebhookVerifyStatus())->toBe([
            'state' => 'skip',
            'message' => 'Zalo OA integration chưa bật.',
        ])
        ->and($gate->zaloWebhookDeliveryStatus())->toBe([
            'state' => 'skip',
            'message' => 'Zalo OA integration chưa bật.',
        ])
        ->and($gate->facebookWebhookVerifyStatus())->toBe([
            'state' => 'skip',
            'message' => 'Facebook Messenger integration chưa bật.',
        ])
        ->and($gate->facebookWebhookDeliveryStatus())->toBe([
            'state' => 'skip',
            'message' => 'Facebook Messenger integration chưa bật.',
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
        ])
        ->and($gate->webLeadIngressStatus())->toBe([
            'state' => 'skip',
            'message' => 'Web lead API chưa được bật.',
        ])
        ->and($gate->allowsEmrInternalIngress())->toBeFalse()
        ->and($gate->allowsZaloWebhookVerify())->toBeFalse()
        ->and($gate->allowsZaloWebhookDelivery())->toBeFalse()
        ->and($gate->allowsFacebookWebhookVerify())->toBeFalse()
        ->and($gate->allowsFacebookWebhookDelivery())->toBeFalse()
        ->and($gate->allowsWebLeadIngress())->toBeFalse();
});

it('fails web lead ingress when token has not been configured', function (): void {
    configureRuntimeGateSetting('web_lead.enabled', true, 'boolean', 'web_lead');
    configureRuntimeGateSetting('web_lead.api_token', '', 'text', 'web_lead', isSecret: true);

    $gate = app(IntegrationProviderRuntimeGate::class);

    expect($gate->webLeadIngressStatus())->toBe([
        'state' => 'fail',
        'message' => 'Web lead API token chưa được cấu hình.',
    ]);
});

it('fails emr internal ingress when api key has not been configured', function (): void {
    configureRuntimeGateSetting('emr.enabled', true, 'boolean', 'emr');
    configureRuntimeGateSetting('emr.api_key', '', 'text', 'emr', isSecret: true);

    $gate = app(IntegrationProviderRuntimeGate::class);

    expect($gate->emrInternalIngressStatus())->toBe([
        'state' => 'fail',
        'message' => 'EMR API key chưa được cấu hình.',
    ]);
});

it('fails zalo webhook ingress when required secrets have not been configured', function (): void {
    configureRuntimeGateSetting('zalo.enabled', true, 'boolean', 'zalo');
    configureRuntimeGateSetting('zalo.webhook_token', '', 'text', 'zalo', isSecret: true);
    configureRuntimeGateSetting('zalo.app_secret', '', 'text', 'zalo', isSecret: true);

    $gate = app(IntegrationProviderRuntimeGate::class);

    expect($gate->zaloWebhookVerifyStatus())->toBe([
        'state' => 'fail',
        'message' => 'Zalo webhook token chưa được cấu hình.',
    ])
        ->and($gate->zaloWebhookDeliveryStatus())->toBe([
            'state' => 'fail',
            'message' => 'Webhook signature verification misconfigured.',
        ]);
});

it('fails facebook webhook ingress when required secrets have not been configured', function (): void {
    configureRuntimeGateSetting('facebook.enabled', true, 'boolean', 'facebook');
    configureRuntimeGateSetting('facebook.webhook_verify_token', '', 'text', 'facebook', isSecret: true);
    configureRuntimeGateSetting('facebook.app_secret', '', 'text', 'facebook', isSecret: true);

    $gate = app(IntegrationProviderRuntimeGate::class);

    expect($gate->facebookWebhookVerifyStatus())->toBe([
        'state' => 'fail',
        'message' => 'Facebook webhook verify token chưa được cấu hình.',
    ])
        ->and($gate->facebookWebhookDeliveryStatus())->toBe([
            'state' => 'fail',
            'message' => 'Facebook webhook signature verification misconfigured.',
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

    configureRuntimeGateSetting('zalo.enabled', true, 'boolean', 'zalo');
    configureRuntimeGateSetting('zalo.webhook_token', 'zalo-webhook-token', 'text', 'zalo', isSecret: true);
    configureRuntimeGateSetting('zalo.app_secret', 'zalo-app-secret', 'text', 'zalo', isSecret: true);

    configureRuntimeGateSetting('facebook.enabled', true, 'boolean', 'facebook');
    configureRuntimeGateSetting('facebook.webhook_verify_token', 'facebook-webhook-token', 'text', 'facebook', isSecret: true);
    configureRuntimeGateSetting('facebook.app_secret', 'facebook-app-secret', 'text', 'facebook', isSecret: true);

    configureRuntimeGateSetting('web_lead.enabled', true, 'boolean', 'web_lead');
    configureRuntimeGateSetting('web_lead.api_token', 'web-lead-token', 'text', 'web_lead', isSecret: true);

    $gate = app(IntegrationProviderRuntimeGate::class);

    expect($gate->googleCalendarSyncCommandStatus())->toBe([
        'state' => 'ready',
        'message' => null,
    ])
        ->and($gate->allowsGoogleCalendarPublish())->toBeTrue()
        ->and($gate->allowsEmrPublish())->toBeTrue()
        ->and($gate->allowsZnsPublish())->toBeTrue()
        ->and($gate->emrInternalIngressStatus())->toBe([
            'state' => 'ready',
            'message' => null,
        ])
        ->and($gate->allowsEmrInternalIngress())->toBeTrue()
        ->and($gate->zaloWebhookVerifyStatus())->toBe([
            'state' => 'ready',
            'message' => null,
        ])
        ->and($gate->zaloWebhookDeliveryStatus())->toBe([
            'state' => 'ready',
            'message' => null,
        ])
        ->and($gate->allowsZaloWebhookVerify())->toBeTrue()
        ->and($gate->allowsZaloWebhookDelivery())->toBeTrue()
        ->and($gate->facebookWebhookVerifyStatus())->toBe([
            'state' => 'ready',
            'message' => null,
        ])
        ->and($gate->facebookWebhookDeliveryStatus())->toBe([
            'state' => 'ready',
            'message' => null,
        ])
        ->and($gate->allowsFacebookWebhookVerify())->toBeTrue()
        ->and($gate->allowsFacebookWebhookDelivery())->toBeTrue()
        ->and($gate->webLeadIngressStatus())->toBe([
            'state' => 'ready',
            'message' => null,
        ])
        ->and($gate->allowsWebLeadIngress())->toBeTrue();
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
