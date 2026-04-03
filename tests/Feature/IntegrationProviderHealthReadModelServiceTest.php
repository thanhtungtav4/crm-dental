<?php

use App\Models\ClinicSetting;
use App\Services\IntegrationProviderHealthReadModelService;

it('builds shared provider health cards and counts from runtime settings', function (): void {
    configureIntegrationProviderHealth('zalo.enabled', true, 'boolean', 'zalo');
    configureIntegrationProviderHealth('zalo.oa_id', 'oa-provider-health', 'text', 'zalo');
    configureIntegrationProviderHealth('zalo.app_id', 'app-provider-health', 'text', 'zalo');
    configureIntegrationProviderHealth('zalo.app_secret', 'secret-provider-health', 'text', 'zalo', isSecret: true);
    configureIntegrationProviderHealth('zalo.webhook_token', 'secure-token-provider-health-1234567890', 'text', 'zalo', isSecret: true);

    configureIntegrationProviderHealth('facebook.enabled', true, 'boolean', 'facebook');
    configureIntegrationProviderHealth('facebook.page_id', 'page-provider-health', 'text', 'facebook');
    configureIntegrationProviderHealth('facebook.app_id', 'facebook-app-id', 'text', 'facebook');
    configureIntegrationProviderHealth('facebook.app_secret', 'facebook-app-secret', 'text', 'facebook', isSecret: true);
    configureIntegrationProviderHealth('facebook.webhook_verify_token', 'facebook-verify-token-1234567890', 'text', 'facebook', isSecret: true);
    configureIntegrationProviderHealth('facebook.page_access_token', 'facebook-page-access-token', 'text', 'facebook', isSecret: true);
    configureIntegrationProviderHealth('facebook.send_endpoint', 'https://graph.facebook.com/v23.0/me/messages', 'text', 'facebook');
    configureIntegrationProviderHealth('facebook.inbox_default_branch_code', 'BR-WEB-01', 'text', 'facebook');

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
    configureIntegrationProviderHealth('emr.dicom.enabled', true, 'boolean', 'emr');
    configureIntegrationProviderHealth('emr.dicom.base_url', 'https://dicom.example.test', 'text', 'emr');
    configureIntegrationProviderHealth('emr.dicom.facility_code', 'HCM-01', 'text', 'emr');
    configureIntegrationProviderHealth('emr.dicom.auth_token', 'dicom-provider-health-token', 'text', 'emr', isSecret: true);
    configureIntegrationProviderHealth('web_lead.enabled', true, 'boolean', 'web_lead');
    configureIntegrationProviderHealth('web_lead.api_token', 'wla_provider_health_token', 'text', 'web_lead', isSecret: true);
    configureIntegrationProviderHealth('web_lead.default_branch_code', 'BR-WEB-01', 'text', 'web_lead');
    configureIntegrationProviderHealth('web_lead.realtime_notification_enabled', true, 'boolean', 'web_lead');
    configureIntegrationProviderHealth('web_lead.realtime_notification_roles', ['CSKH'], 'json', 'web_lead');
    configureIntegrationProviderHealth('web_lead.internal_email_enabled', true, 'boolean', 'web_lead');
    configureIntegrationProviderHealth('web_lead.internal_email_recipient_roles', ['CSKH'], 'json', 'web_lead');
    configureIntegrationProviderHealth('web_lead.internal_email_recipient_emails', 'lead-box@example.test', 'text', 'web_lead');
    configureIntegrationProviderHealth('web_lead.internal_email_smtp_host', 'smtp.example.test', 'text', 'web_lead');
    configureIntegrationProviderHealth('web_lead.internal_email_smtp_port', 587, 'integer', 'web_lead');
    configureIntegrationProviderHealth('web_lead.internal_email_smtp_username', 'lead-bot@example.test', 'text', 'web_lead');
    configureIntegrationProviderHealth('web_lead.internal_email_smtp_password', 'secret-provider-health', 'text', 'web_lead', isSecret: true);
    configureIntegrationProviderHealth('web_lead.internal_email_smtp_scheme', 'tls', 'text', 'web_lead');
    configureIntegrationProviderHealth('web_lead.internal_email_from_address', 'lead-bot@example.test', 'text', 'web_lead');
    configureIntegrationProviderHealth('web_lead.internal_email_from_name', 'CRM Lead Bot', 'text', 'web_lead');

    \App\Models\Branch::factory()->create([
        'code' => 'BR-WEB-01',
        'active' => true,
    ]);

    $service = app(IntegrationProviderHealthReadModelService::class);
    $cards = collect($service->cards())->keyBy('key');
    $counts = $service->counts();

    expect($cards->keys()->all())->toBe([
        'zalo_oa',
        'facebook_messenger',
        'zns',
        'google_calendar',
        'emr',
        'dicom',
        'web_lead',
    ])
        ->and($cards->get('zalo_oa')['status'])->toBe('Healthy')
        ->and($cards->get('zalo_oa')['webhook_url'])->toContain('/api/v1/integrations/zalo/webhook')
        ->and($cards->get('facebook_messenger')['status'])->toBe('Healthy')
        ->and($cards->get('facebook_messenger')['webhook_url'])->toContain('/api/v1/integrations/facebook/webhook')
        ->and($cards->get('zns')['runtime_error_message'])->toBe('Thiếu ZNS access token.')
        ->and($cards->get('google_calendar')['runtime_error_message'])->toBe('Google Calendar chưa cấu hình đầy đủ (client_id/client_secret/refresh_token/calendar_id).')
        ->and($cards->get('emr')['status'])->toBe('Disabled')
        ->and($cards->get('dicom')['status'])->toBe('Healthy')
        ->and($cards->get('web_lead')['status'])->toBe('Healthy')
        ->and($cards->get('web_lead')['meta'][0]['value'])->toContain('/api/v1/web-leads')
        ->and($counts)->toBe([
            'healthy' => 4,
            'degraded' => 2,
            'disabled' => 1,
        ]);
});

it('marks web lead provider as degraded when inbound token or internal mail runtime drifts', function (): void {
    configureIntegrationProviderHealth('web_lead.enabled', true, 'boolean', 'web_lead');
    configureIntegrationProviderHealth('web_lead.api_token', '', 'text', 'web_lead', isSecret: true);
    configureIntegrationProviderHealth('web_lead.default_branch_code', 'MISSING-BRANCH', 'text', 'web_lead');
    configureIntegrationProviderHealth('web_lead.internal_email_enabled', true, 'boolean', 'web_lead');
    configureIntegrationProviderHealth('web_lead.internal_email_recipient_roles', [], 'json', 'web_lead');
    configureIntegrationProviderHealth('web_lead.internal_email_recipient_emails', '', 'text', 'web_lead');
    configureIntegrationProviderHealth('web_lead.internal_email_smtp_host', '', 'text', 'web_lead');
    configureIntegrationProviderHealth('web_lead.internal_email_from_address', '', 'text', 'web_lead');

    $card = app(IntegrationProviderHealthReadModelService::class)->provider('web_lead');

    expect($card['status'])->toBe('Needs configuration')
        ->and($card['runtime_error_message'])->toBe('Web Lead API chưa cấu hình API token.')
        ->and($card['issues'])->toContain('Thiếu Web Lead API token.')
        ->and($card['issues'])->toContain('Chi nhánh mặc định của Web Lead không hợp lệ hoặc không còn hoạt động.')
        ->and($card['issues'])->toContain('Bật email nội bộ nhưng chưa cấu hình người nhận.')
        ->and(collect($card['issues'])->contains(fn (string $message): bool => str_contains($message, 'SMTP host')))->toBeTrue();
});

it('builds provider health snapshot cards with presentation payloads', function (): void {
    configureIntegrationProviderHealth('zalo.enabled', true, 'boolean', 'zalo');
    configureIntegrationProviderHealth('zalo.oa_id', 'oa-provider-health', 'text', 'zalo');
    configureIntegrationProviderHealth('zalo.app_id', 'app-provider-health', 'text', 'zalo');
    configureIntegrationProviderHealth('zalo.app_secret', 'secret-provider-health', 'text', 'zalo', isSecret: true);
    configureIntegrationProviderHealth('zalo.webhook_token', 'secure-token-provider-health-1234567890', 'text', 'zalo', isSecret: true);

    configureIntegrationProviderHealth('zns.enabled', true, 'boolean', 'zns');
    configureIntegrationProviderHealth('zns.access_token', '', 'text', 'zns', isSecret: true);
    configureIntegrationProviderHealth('zns.refresh_token', 'zns-refresh-token', 'text', 'zns', isSecret: true);
    configureIntegrationProviderHealth('zns.send_endpoint', 'https://business.openapi.zalo.me/message/template', 'text', 'zns');

    $cards = collect(app(IntegrationProviderHealthReadModelService::class)->snapshotCards())->keyBy('key');

    expect($cards->get('zalo_oa'))->toMatchArray([
        'label' => 'Zalo OA',
        'status_badge' => [
            'label' => 'Healthy',
            'classes' => 'border-success-200 bg-success-50 text-success-700 dark:border-success-900/60 dark:bg-success-950/30 dark:text-success-200',
        ],
        'summary_badge' => [
            'label' => 'Score 100/100',
            'classes' => 'border-success-200 bg-success-50 text-success-700 dark:border-success-900/60 dark:bg-success-950/30 dark:text-success-200',
        ],
        'issue_badge' => null,
        'status_message' => null,
    ])
        ->and($cards->get('zalo_oa')['meta_preview'][0]['label'])->toBe('Webhook URL')
        ->and($cards->get('zns'))->toMatchArray([
            'label' => 'ZNS',
            'status_badge' => [
                'label' => 'Needs configuration',
                'classes' => 'border-danger-200 bg-danger-50 text-danger-700 dark:border-danger-900/60 dark:bg-danger-950/30 dark:text-danger-100',
            ],
            'summary_badge' => [
                'label' => 'Score 60/100',
                'classes' => 'border-danger-200 bg-danger-50 text-danger-700 dark:border-danger-900/60 dark:bg-danger-950/30 dark:text-danger-100',
            ],
            'issue_badge' => [
                'label' => '2 issue',
                'classes' => 'border-danger-200 bg-danger-50 text-danger-700 dark:border-danger-900/60 dark:bg-danger-950/30 dark:text-danger-100',
            ],
            'status_message' => 'Thiếu ZNS access token.',
            'status_message_classes' => 'border-danger-200 bg-danger-50 text-danger-900 dark:border-danger-900/60 dark:bg-danger-950/30 dark:text-danger-100',
        ]);
});

function configureIntegrationProviderHealth(
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
