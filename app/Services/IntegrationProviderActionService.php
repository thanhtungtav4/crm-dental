<?php

namespace App\Services;

use InvalidArgumentException;

class IntegrationProviderActionService
{
    public function __construct(
        protected EmrIntegrationService $emrIntegrationService,
        protected GoogleCalendarIntegrationService $googleCalendarIntegrationService,
        protected IntegrationProviderHealthReadModelService $integrationProviderHealthReadModelService,
    ) {}

    /**
     * @return array{success:bool,title:string,body:string}
     */
    public function emrConnectionReport(): array
    {
        $result = $this->emrIntegrationService->authenticate();
        $success = ($result['success'] ?? false) === true;

        return [
            'success' => $success,
            'title' => $success ? 'Kết nối EMR thành công' : 'Kết nối EMR thất bại',
            'body' => (string) ($result['message'] ?? ($success ? 'Authenticate thành công.' : 'Không thể kết nối tới EMR.')),
        ];
    }

    /**
     * @return array{title:string,body:string,status:string}
     */
    public function emrConnectionNotification(): array
    {
        $report = $this->emrConnectionReport();

        return $this->notificationPayload(
            title: $report['title'],
            body: $report['body'],
            status: $report['success'] ? 'success' : 'danger',
        );
    }

    /**
     * @return array{success:bool,title:string,body:string,account_email:?string}
     */
    public function googleCalendarConnectionReport(): array
    {
        $result = $this->googleCalendarIntegrationService->testConnection();
        $success = ($result['success'] ?? false) === true;

        return [
            'success' => $success,
            'title' => $success ? 'Kết nối Google Calendar thành công' : 'Kết nối Google Calendar thất bại',
            'body' => $success
                ? collect([
                    (string) ($result['message'] ?? 'Kết nối thành công.'),
                    filled($result['calendar_id'] ?? null) ? 'Calendar ID: '.(string) $result['calendar_id'] : null,
                    filled($result['account_email'] ?? null) ? 'Google Account: '.(string) $result['account_email'] : null,
                ])->filter()->implode("\n")
                : (string) ($result['message'] ?? 'Không thể kết nối Google Calendar.'),
            'account_email' => filled($result['account_email'] ?? null)
                ? (string) $result['account_email']
                : null,
        ];
    }

    /**
     * @return array{title:string,body:string,status:string,account_email:?string}
     */
    public function googleCalendarConnectionNotification(): array
    {
        $report = $this->googleCalendarConnectionReport();

        return [
            ...$this->notificationPayload(
                title: $report['title'],
                body: $report['body'],
                status: $report['success'] ? 'success' : 'danger',
            ),
            'account_email' => $report['account_email'],
        ];
    }

    /**
     * @return array{success:bool,title:string,body:string,score:int}
     */
    public function readinessReport(string $providerKey): array
    {
        $report = $this->integrationProviderHealthReadModelService->provider($providerKey);
        $success = ($report['score'] ?? 0) >= 80;
        $label = $this->readinessLabel($providerKey);

        $body = collect([
            'Điểm sẵn sàng: '.($report['score'] ?? 0).'/100',
            ...array_map(static fn (string $item): string => '• '.$item, $report['issues'] ?? []),
            ...array_map(static fn (string $item): string => '→ '.$item, $report['recommendations'] ?? []),
            filled($report['webhook_url'] ?? null) ? 'Webhook URL: '.(string) $report['webhook_url'] : null,
        ])->filter()->implode("\n");

        return [
            'success' => $success,
            'title' => $success ? "{$label} sẵn sàng tốt" : "{$label} cần bổ sung cấu hình",
            'body' => $body,
            'score' => (int) ($report['score'] ?? 0),
        ];
    }

    /**
     * @return array{title:string,body:string,status:string,score:int}
     */
    public function readinessNotification(string $providerKey): array
    {
        $report = $this->readinessReport($providerKey);

        return [
            ...$this->notificationPayload(
                title: $report['title'],
                body: $report['body'],
                status: $report['success'] ? 'success' : 'warning',
            ),
            'score' => $report['score'],
        ];
    }

    /**
     * @return array{success:bool,message:string,url:?string}
     */
    public function emrConfigUrlReport(): array
    {
        $result = $this->emrIntegrationService->resolveConfigUrl();

        return [
            'success' => ($result['success'] ?? false) === true && filled($result['url'] ?? null),
            'message' => (string) ($result['message'] ?? 'EMR chưa trả về URL cấu hình hợp lệ.'),
            'url' => filled($result['url'] ?? null) ? (string) $result['url'] : null,
        ];
    }

    /**
     * @return array{title:string,body:string,status:string}
     */
    protected function notificationPayload(string $title, string $body, string $status): array
    {
        return [
            'title' => $title,
            'body' => $body,
            'status' => $status,
        ];
    }

    protected function readinessLabel(string $providerKey): string
    {
        return match ($providerKey) {
            'zalo_oa' => 'Zalo OA',
            'facebook_messenger' => 'Facebook Messenger',
            'zns' => 'ZNS',
            'dicom' => 'DICOM / PACS',
            'web_lead' => 'Web Lead API',
            default => throw new InvalidArgumentException("Unsupported readiness report provider [{$providerKey}]."),
        };
    }
}
