<?php

use App\Models\ClinicSetting;
use App\Services\IntegrationProviderActionService;
use Illuminate\Support\Facades\Http;

it('formats readiness notifications from shared provider health cards', function (): void {
    configureProviderActionSetting('zalo.enabled', true, 'boolean', 'zalo');
    configureProviderActionSetting('zalo.oa_id', 'oa-action-001', 'text', 'zalo');
    configureProviderActionSetting('zalo.app_id', 'app-action-001', 'text', 'zalo');
    configureProviderActionSetting('zalo.app_secret', 'secret-action-001', 'text', 'zalo', isSecret: true);
    configureProviderActionSetting('zalo.webhook_token', 'verify-action-token-1234567890', 'text', 'zalo', isSecret: true);

    $report = app(IntegrationProviderActionService::class)->readinessReport('zalo_oa');

    expect($report['success'])->toBeTrue()
        ->and($report['title'])->toBe('Zalo OA sẵn sàng tốt')
        ->and($report['body'])->toContain('Điểm sẵn sàng:')
        ->and($report['body'])->toContain('Webhook URL:')
        ->and($report['score'])->toBeGreaterThanOrEqual(80);
});

it('builds readiness notification payload from shared provider health cards', function (): void {
    configureProviderActionSetting('zalo.enabled', true, 'boolean', 'zalo');
    configureProviderActionSetting('zalo.oa_id', 'oa-action-001', 'text', 'zalo');
    configureProviderActionSetting('zalo.app_id', 'app-action-001', 'text', 'zalo');
    configureProviderActionSetting('zalo.app_secret', 'secret-action-001', 'text', 'zalo', isSecret: true);
    configureProviderActionSetting('zalo.webhook_token', 'verify-action-token-1234567890', 'text', 'zalo', isSecret: true);

    $payload = app(IntegrationProviderActionService::class)->readinessNotification('zalo_oa');

    expect($payload['title'])->toBe('Zalo OA sẵn sàng tốt')
        ->and($payload['status'])->toBe('success')
        ->and($payload['body'])->toContain('Điểm sẵn sàng:')
        ->and($payload['score'])->toBeGreaterThanOrEqual(80);
});

it('formats readiness notifications for dicom and web lead providers', function (): void {
    configureProviderActionSetting('emr.dicom.enabled', true, 'boolean', 'emr');
    configureProviderActionSetting('emr.dicom.base_url', 'https://dicom.example.test', 'text', 'emr');
    configureProviderActionSetting('emr.dicom.facility_code', 'HCM-01', 'text', 'emr');
    configureProviderActionSetting('emr.dicom.auth_token', 'dicom-action-token', 'text', 'emr', isSecret: true);

    configureProviderActionSetting('web_lead.enabled', true, 'boolean', 'web_lead');
    configureProviderActionSetting('web_lead.api_token', 'wla_action_token_1234567890', 'text', 'web_lead', isSecret: true);
    configureProviderActionSetting('web_lead.default_branch_code', '', 'text', 'web_lead');

    $service = app(IntegrationProviderActionService::class);

    $dicom = $service->readinessReport('dicom');
    $webLead = $service->readinessReport('web_lead');

    expect($dicom['success'])->toBeTrue()
        ->and($dicom['title'])->toBe('DICOM / PACS sẵn sàng tốt')
        ->and($dicom['body'])->toContain('Điểm sẵn sàng:')
        ->and($dicom['score'])->toBeGreaterThanOrEqual(80)
        ->and($webLead['success'])->toBeTrue()
        ->and($webLead['title'])->toBe('Web Lead API sẵn sàng tốt')
        ->and($webLead['body'])->toContain('Điểm sẵn sàng:')
        ->and($webLead['score'])->toBeGreaterThanOrEqual(80);
});

it('formats google calendar connection report and surfaces account email', function (): void {
    configureProviderActionSetting('google_calendar.enabled', true, 'boolean', 'google_calendar');
    configureProviderActionSetting('google_calendar.client_id', 'gcal-client-id', 'text', 'google_calendar');
    configureProviderActionSetting('google_calendar.client_secret', 'gcal-client-secret', 'text', 'google_calendar', isSecret: true);
    configureProviderActionSetting('google_calendar.refresh_token', 'gcal-refresh-token', 'text', 'google_calendar', isSecret: true);
    configureProviderActionSetting('google_calendar.calendar_id', 'crm-calendar@example.com', 'text', 'google_calendar');

    Http::preventStrayRequests();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'google-access-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ], 200),
        'https://www.googleapis.com/calendar/v3/calendars/*' => Http::response([
            'id' => 'crm-calendar@example.com',
        ], 200),
    ]);

    $report = app(IntegrationProviderActionService::class)->googleCalendarConnectionReport();

    expect($report['success'])->toBeTrue()
        ->and($report['title'])->toBe('Kết nối Google Calendar thành công')
        ->and($report['body'])->toContain('Calendar ID: crm-calendar@example.com')
        ->and($report['body'])->toContain('Google Account: crm-calendar@example.com')
        ->and($report['account_email'])->toBe('crm-calendar@example.com');
});

it('builds google calendar connection notification payload and preserves account email', function (): void {
    configureProviderActionSetting('google_calendar.enabled', true, 'boolean', 'google_calendar');
    configureProviderActionSetting('google_calendar.client_id', 'gcal-client-id', 'text', 'google_calendar');
    configureProviderActionSetting('google_calendar.client_secret', 'gcal-client-secret', 'text', 'google_calendar', isSecret: true);
    configureProviderActionSetting('google_calendar.refresh_token', 'gcal-refresh-token', 'text', 'google_calendar', isSecret: true);
    configureProviderActionSetting('google_calendar.calendar_id', 'crm-calendar@example.com', 'text', 'google_calendar');

    Http::preventStrayRequests();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'google-access-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ], 200),
        'https://www.googleapis.com/calendar/v3/calendars/*' => Http::response([
            'id' => 'crm-calendar@example.com',
        ], 200),
    ]);

    $payload = app(IntegrationProviderActionService::class)->googleCalendarConnectionNotification();

    expect($payload['title'])->toBe('Kết nối Google Calendar thành công')
        ->and($payload['status'])->toBe('success')
        ->and($payload['body'])->toContain('Google Account: crm-calendar@example.com')
        ->and($payload['account_email'])->toBe('crm-calendar@example.com');
});

it('formats emr action reports for authenticate and config url flows', function (): void {
    configureProviderActionSetting('emr.enabled', true, 'boolean', 'emr');
    configureProviderActionSetting('emr.base_url', 'https://emr.example.test', 'text', 'emr');
    configureProviderActionSetting('emr.api_key', 'emr-api-key', 'text', 'emr', isSecret: true);
    configureProviderActionSetting('emr.provider', 'internal', 'text', 'emr');
    configureProviderActionSetting('emr.clinic_code', 'CLINIC-01', 'text', 'emr');

    Http::preventStrayRequests();
    Http::fake([
        'https://emr.example.test/api/emr/authenticate' => Http::response([
            'message' => 'Authenticated',
        ], 200),
        'https://emr.example.test/api/emr/config-url' => Http::response([
            'message' => 'Ready',
            'url' => 'https://emr.example.test/configuration',
        ], 200),
    ]);

    $service = app(IntegrationProviderActionService::class);
    $connectionReport = $service->emrConnectionReport();
    $configUrlReport = $service->emrConfigUrlReport();

    expect($connectionReport)->toBe([
        'success' => true,
        'title' => 'Kết nối EMR thành công',
        'body' => 'Authenticated',
    ])
        ->and($configUrlReport)->toBe([
            'success' => true,
            'message' => 'Ready',
            'url' => 'https://emr.example.test/configuration',
        ]);
});

it('builds emr connection notification payload', function (): void {
    configureProviderActionSetting('emr.enabled', true, 'boolean', 'emr');
    configureProviderActionSetting('emr.base_url', 'https://emr.example.test', 'text', 'emr');
    configureProviderActionSetting('emr.api_key', 'emr-api-key', 'text', 'emr', isSecret: true);
    configureProviderActionSetting('emr.provider', 'internal', 'text', 'emr');
    configureProviderActionSetting('emr.clinic_code', 'CLINIC-01', 'text', 'emr');

    Http::preventStrayRequests();
    Http::fake([
        'https://emr.example.test/api/emr/authenticate' => Http::response([
            'message' => 'Authenticated',
        ], 200),
    ]);

    $payload = app(IntegrationProviderActionService::class)->emrConnectionNotification();

    expect($payload)->toBe([
        'title' => 'Kết nối EMR thành công',
        'body' => 'Authenticated',
        'status' => 'success',
    ]);
});

function configureProviderActionSetting(
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
