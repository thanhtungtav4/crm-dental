<?php

namespace App\Services;

use App\Support\ClinicRuntimeSettings;
use InvalidArgumentException;

class IntegrationProviderHealthReadModelService
{
    public function __construct(
        protected DicomReadinessService $dicomReadinessService,
        protected EmrIntegrationService $emrIntegrationService,
        protected GoogleCalendarIntegrationService $googleCalendarIntegrationService,
        protected ZaloIntegrationService $zaloIntegrationService,
        protected ZnsProviderClient $znsProviderClient,
    ) {}

    /**
     * @return array<int, array{
     *     key:string,
     *     label:string,
     *     description:string,
     *     enabled:bool,
     *     tone:string,
     *     status:string,
     *     score:int,
     *     issues:array<int, string>,
     *     recommendations:array<int, string>,
     *     meta:array<int, array{label:string, value:int|string}>,
     *     issue_count:int,
     *     recommendation_count:int,
     *     runtime_error_message:?string,
     *     webhook_url:?string
     * }>
     */
    public function cards(): array
    {
        return [
            $this->provider('zalo_oa'),
            $this->provider('zns'),
            $this->provider('google_calendar'),
            $this->provider('emr'),
            $this->provider('dicom'),
        ];
    }

    /**
     * @return array{healthy:int,degraded:int,disabled:int}
     */
    public function counts(): array
    {
        $cards = collect($this->cards());

        return [
            'healthy' => $cards->where('tone', 'success')->count(),
            'degraded' => $cards->where('tone', 'danger')->count(),
            'disabled' => $cards->where('tone', 'info')->count(),
        ];
    }

    /**
     * @return array{
     *     key:string,
     *     label:string,
     *     description:string,
     *     enabled:bool,
     *     tone:string,
     *     status:string,
     *     score:int,
     *     issues:array<int, string>,
     *     recommendations:array<int, string>,
     *     meta:array<int, array{label:string, value:int|string}>,
     *     issue_count:int,
     *     recommendation_count:int,
     *     runtime_error_message:?string,
     *     webhook_url:?string
     * }
     */
    public function provider(string $key): array
    {
        return match ($key) {
            'zalo_oa' => $this->zaloOaCard(),
            'zns' => $this->znsCard(),
            'google_calendar' => $this->googleCalendarCard(),
            'emr' => $this->emrCard(),
            'dicom' => $this->dicomCard(),
            default => throw new InvalidArgumentException("Unsupported integration provider [{$key}]."),
        };
    }

    /**
     * @param  array<int, string>  $issues
     * @param  array<int, string>  $recommendations
     * @param  array<int, array{label:string, value:int|string}>  $meta
     * @return array{
     *     key:string,
     *     label:string,
     *     description:string,
     *     enabled:bool,
     *     tone:string,
     *     status:string,
     *     score:int,
     *     issues:array<int, string>,
     *     recommendations:array<int, string>,
     *     meta:array<int, array{label:string, value:int|string}>,
     *     issue_count:int,
     *     recommendation_count:int,
     *     runtime_error_message:?string,
     *     webhook_url:?string
     * }
     */
    protected function buildCard(
        string $key,
        string $label,
        string $description,
        bool $enabled,
        array $issues,
        array $recommendations,
        array $meta = [],
        ?string $runtimeErrorMessage = null,
        ?string $webhookUrl = null,
    ): array {
        $issues = array_values(array_unique($issues));
        $recommendations = array_values(array_unique($recommendations));
        $score = $enabled ? max(0, 100 - (count($issues) * 20)) : 0;

        if (! $enabled) {
            $tone = 'info';
            $status = 'Disabled';
        } elseif ($issues === []) {
            $tone = 'success';
            $status = 'Healthy';
        } else {
            $tone = 'danger';
            $status = 'Needs configuration';
        }

        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'enabled' => $enabled,
            'tone' => $tone,
            'status' => $status,
            'score' => $score,
            'issues' => $issues,
            'recommendations' => $recommendations,
            'meta' => $meta,
            'issue_count' => count($issues),
            'recommendation_count' => count($recommendations),
            'runtime_error_message' => $runtimeErrorMessage,
            'webhook_url' => $webhookUrl,
        ];
    }

    /**
     * @return array{
     *     key:string,
     *     label:string,
     *     description:string,
     *     enabled:bool,
     *     tone:string,
     *     status:string,
     *     score:int,
     *     issues:array<int, string>,
     *     recommendations:array<int, string>,
     *     meta:array<int, array{label:string, value:int|string}>,
     *     issue_count:int,
     *     recommendation_count:int,
     *     runtime_error_message:?string,
     *     webhook_url:?string
     * }
     */
    protected function zaloOaCard(): array
    {
        $report = $this->zaloIntegrationService->auditOaReadiness();

        return $this->buildCard(
            key: 'zalo_oa',
            label: 'Zalo OA',
            description: 'Webhook OA, verify token, App ID/App Secret va trust endpoint inbound.',
            enabled: (bool) ($report['enabled'] ?? false),
            issues: (array) ($report['issues'] ?? []),
            recommendations: (array) ($report['recommendations'] ?? []),
            meta: [
                [
                    'label' => 'Webhook URL',
                    'value' => (string) ($report['webhook_url'] ?? '-'),
                ],
                [
                    'label' => 'Grace window',
                    'value' => ClinicRuntimeSettings::zaloWebhookTokenGraceMinutes(),
                ],
            ],
            webhookUrl: filled($report['webhook_url'] ?? null) ? (string) $report['webhook_url'] : null,
        );
    }

    /**
     * @return array{
     *     key:string,
     *     label:string,
     *     description:string,
     *     enabled:bool,
     *     tone:string,
     *     status:string,
     *     score:int,
     *     issues:array<int, string>,
     *     recommendations:array<int, string>,
     *     meta:array<int, array{label:string, value:int|string}>,
     *     issue_count:int,
     *     recommendation_count:int,
     *     runtime_error_message:?string,
     *     webhook_url:?string
     * }
     */
    protected function znsCard(): array
    {
        $report = $this->zaloIntegrationService->auditZnsReadiness();
        $enabled = (bool) ($report['enabled'] ?? false);
        $templateCount = collect([
            ClinicRuntimeSettings::znsTemplateLeadWelcome(),
            ClinicRuntimeSettings::znsTemplateAppointment(),
            ClinicRuntimeSettings::znsTemplatePayment(),
            ClinicRuntimeSettings::znsTemplateBirthday(),
        ])->filter(static fn (string $templateId): bool => $templateId !== '')
            ->count();
        $automationCount = collect([
            ClinicRuntimeSettings::znsAutoSendLeadWelcome(),
            ClinicRuntimeSettings::znsAutoSendAppointmentReminder(),
            ClinicRuntimeSettings::znsAutoSendBirthdayGreeting(),
        ])->filter(static fn (bool $enabled): bool => $enabled)
            ->count();

        return $this->buildCard(
            key: 'zns',
            label: 'ZNS',
            description: 'Token gửi, endpoint provider va template automation cho lead, appointment, birthday.',
            enabled: $enabled,
            issues: (array) ($report['issues'] ?? []),
            recommendations: (array) ($report['recommendations'] ?? []),
            meta: [
                [
                    'label' => 'Send endpoint',
                    'value' => ClinicRuntimeSettings::znsSendEndpoint() !== '' ? ClinicRuntimeSettings::znsSendEndpoint() : '-',
                ],
                [
                    'label' => 'Templates',
                    'value' => $templateCount,
                ],
                [
                    'label' => 'Automation on',
                    'value' => $automationCount,
                ],
            ],
            runtimeErrorMessage: $enabled ? $this->znsProviderClient->configurationErrorMessage() : null,
        );
    }

    /**
     * @return array{
     *     key:string,
     *     label:string,
     *     description:string,
     *     enabled:bool,
     *     tone:string,
     *     status:string,
     *     score:int,
     *     issues:array<int, string>,
     *     recommendations:array<int, string>,
     *     meta:array<int, array{label:string, value:int|string}>,
     *     issue_count:int,
     *     recommendation_count:int,
     *     runtime_error_message:?string,
     *     webhook_url:?string
     * }
     */
    protected function googleCalendarCard(): array
    {
        $enabled = ClinicRuntimeSettings::isGoogleCalendarEnabled();
        $issues = [];
        $recommendations = [];

        if (! $enabled) {
            $recommendations[] = 'Bật Google Calendar khi cần đẩy lịch hẹn CRM sang lịch ngoài cho điều phối.';
        }

        if (ClinicRuntimeSettings::googleCalendarClientId() === '') {
            $issues[] = 'Thiếu Client ID.';
        }

        if (ClinicRuntimeSettings::googleCalendarClientSecret() === '') {
            $issues[] = 'Thiếu Client Secret.';
        }

        if (ClinicRuntimeSettings::googleCalendarRefreshToken() === '') {
            $issues[] = 'Thiếu Refresh Token.';
        }

        if (ClinicRuntimeSettings::googleCalendarCalendarId() === '') {
            $issues[] = 'Thiếu Calendar ID.';
        }

        if ($enabled && ! ClinicRuntimeSettings::googleCalendarAllowsPushToGoogle()) {
            $issues[] = 'Chế độ sync hiện không hỗ trợ CRM -> Google.';
        }

        if ($enabled && $this->googleCalendarIntegrationService->isConfigured()) {
            $recommendations[] = 'Chạy Test connection sau mỗi lần xoay refresh token hoặc đổi calendar đích.';
        }

        return $this->buildCard(
            key: 'google_calendar',
            label: 'Google Calendar',
            description: 'OAuth client, refresh token, calendar mapping va sync mode CRM -> Google.',
            enabled: $enabled,
            issues: $issues,
            recommendations: $recommendations,
            meta: [
                [
                    'label' => 'Sync mode',
                    'value' => ClinicRuntimeSettings::googleCalendarSyncMode(),
                ],
                [
                    'label' => 'Calendar ID',
                    'value' => ClinicRuntimeSettings::googleCalendarCalendarId() !== '' ? ClinicRuntimeSettings::googleCalendarCalendarId() : '-',
                ],
                [
                    'label' => 'Account email',
                    'value' => ClinicRuntimeSettings::googleCalendarAccountEmail() !== '' ? ClinicRuntimeSettings::googleCalendarAccountEmail() : '-',
                ],
            ],
            runtimeErrorMessage: $enabled && ! $this->googleCalendarIntegrationService->isConfigured()
                ? 'Google Calendar chưa cấu hình đầy đủ (client_id/client_secret/refresh_token/calendar_id).'
                : null,
        );
    }

    /**
     * @return array{
     *     key:string,
     *     label:string,
     *     description:string,
     *     enabled:bool,
     *     tone:string,
     *     status:string,
     *     score:int,
     *     issues:array<int, string>,
     *     recommendations:array<int, string>,
     *     meta:array<int, array{label:string, value:int|string}>,
     *     issue_count:int,
     *     recommendation_count:int,
     *     runtime_error_message:?string,
     *     webhook_url:?string
     * }
     */
    protected function emrCard(): array
    {
        $enabled = ClinicRuntimeSettings::isEmrEnabled();
        $issues = [];
        $recommendations = [];

        if (! $enabled) {
            $recommendations[] = 'Bật EMR khi cần đẩy MPI va payload lâm sàng sang hệ thống hồ sơ ngoài.';
        }

        if (ClinicRuntimeSettings::emrBaseUrl() === '') {
            $issues[] = 'Thiếu EMR base URL.';
        }

        if (ClinicRuntimeSettings::emrApiKey() === '') {
            $issues[] = 'Thiếu EMR API key.';
        }

        if ($enabled && $this->emrIntegrationService->isConfigured()) {
            $recommendations[] = 'Chạy Authenticate hoặc Config URL sau khi xoay API key để xác nhận provider phản hồi đúng.';
        }

        if ($enabled && ClinicRuntimeSettings::emrClinicCode() === '') {
            $recommendations[] = 'Cân nhắc cấu hình clinic code để đối soát mapping cơ sở với EMR rõ ràng hơn.';
        }

        return $this->buildCard(
            key: 'emr',
            label: 'EMR',
            description: 'Base URL, API key, provider mapping va channel sync CRM -> EMR.',
            enabled: $enabled,
            issues: $issues,
            recommendations: $recommendations,
            meta: [
                [
                    'label' => 'Provider',
                    'value' => ClinicRuntimeSettings::emrProvider() !== '' ? ClinicRuntimeSettings::emrProvider() : '-',
                ],
                [
                    'label' => 'Clinic code',
                    'value' => ClinicRuntimeSettings::emrClinicCode() !== '' ? ClinicRuntimeSettings::emrClinicCode() : '-',
                ],
                [
                    'label' => 'Retention days',
                    'value' => ClinicRuntimeSettings::emrOperationalRetentionDays(),
                ],
            ],
            runtimeErrorMessage: $enabled && ! $this->emrIntegrationService->isConfigured()
                ? 'EMR chưa cấu hình đầy đủ (base_url/api_key).'
                : null,
        );
    }

    /**
     * @return array{
     *     key:string,
     *     label:string,
     *     description:string,
     *     enabled:bool,
     *     tone:string,
     *     status:string,
     *     score:int,
     *     issues:array<int, string>,
     *     recommendations:array<int, string>,
     *     meta:array<int, array{label:string, value:int|string}>,
     *     issue_count:int,
     *     recommendation_count:int,
     *     runtime_error_message:?string,
     *     webhook_url:?string
     * }
     */
    protected function dicomCard(): array
    {
        $snapshot = $this->dicomReadinessService->snapshot();
        $enabled = (bool) ($snapshot['enabled'] ?? false);
        $checks = collect($snapshot['checks'] ?? []);
        $issues = $checks
            ->filter(fn (array $check): bool => ($check['passed'] ?? false) !== true)
            ->map(fn (array $check): string => (string) ($check['message'] ?? 'DICOM readiness chưa đạt.'))
            ->values()
            ->all();
        $recommendations = [];

        if (! $enabled) {
            $recommendations[] = 'Bật DICOM/PACS khi cần readiness gate riêng cho imaging upload và PACS endpoint.';
        }

        if ($enabled && $issues === []) {
            $recommendations[] = 'Chạy emr:check-dicom-readiness --probe --strict sau mỗi lần đổi DICOM base URL hoặc auth token.';
        }

        if ($enabled && $issues !== []) {
            $recommendations[] = 'Bổ sung đầy đủ DICOM base URL, facility code, và auth token trước khi bật strict readiness gate.';
        }

        return $this->buildCard(
            key: 'dicom',
            label: 'DICOM / PACS',
            description: 'Readiness gate cho imaging endpoint, facility code và auth token của PACS/DICOM.',
            enabled: $enabled,
            issues: $issues,
            recommendations: $recommendations,
            meta: [
                [
                    'label' => 'Base URL',
                    'value' => (string) data_get($snapshot, 'config.base_url', '-') ?: '-',
                ],
                [
                    'label' => 'Facility',
                    'value' => (string) data_get($snapshot, 'config.facility_code', '-') ?: '-',
                ],
                [
                    'label' => 'Timeout',
                    'value' => (int) data_get($snapshot, 'config.timeout_seconds', 0).'s',
                ],
                [
                    'label' => 'Probe',
                    'value' => 'Config only',
                ],
            ],
            runtimeErrorMessage: $enabled && $issues !== []
                ? (string) ($issues[0] ?? 'DICOM/PACS chưa cấu hình đầy đủ.')
                : null,
        );
    }
}
