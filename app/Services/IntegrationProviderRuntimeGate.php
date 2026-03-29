<?php

namespace App\Services;

use App\Support\ClinicRuntimeSettings;

class IntegrationProviderRuntimeGate
{
    /**
     * @var array<string, array{
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
    protected array $providerHealthCache = [];

    public function __construct(
        protected IntegrationProviderHealthReadModelService $integrationProviderHealthReadModelService,
    ) {}

    /**
     * @return array{state:string,message:?string}
     */
    public function emrSyncCommandStatus(): array
    {
        if (! ClinicRuntimeSettings::isEmrEnabled()) {
            return $this->skip('EMR integration đang tắt. Không có dữ liệu cần sync.');
        }

        return $this->runtimeStatus('emr');
    }

    public function allowsEmrPublish(): bool
    {
        return $this->emrSyncCommandStatus()['state'] === 'ready';
    }

    /**
     * @return array{state:string,message:?string}
     */
    public function emrInternalIngressStatus(): array
    {
        if (! ClinicRuntimeSettings::boolean('emr.enabled', false)) {
            return $this->skip('EMR internal API chưa được bật.');
        }

        if (trim((string) ClinicRuntimeSettings::get('emr.api_key', '')) === '') {
            return $this->fail('EMR API key chưa được cấu hình.');
        }

        return $this->ready();
    }

    public function allowsEmrInternalIngress(): bool
    {
        return $this->emrInternalIngressStatus()['state'] === 'ready';
    }

    /**
     * @return array{state:string,message:?string}
     */
    public function zaloWebhookVerifyStatus(): array
    {
        if (! ClinicRuntimeSettings::boolean('zalo.enabled', false)) {
            return $this->skip('Zalo OA integration chưa bật.');
        }

        if (trim((string) ClinicRuntimeSettings::get('zalo.webhook_token', '')) === '') {
            return $this->fail('Zalo webhook token chưa được cấu hình.');
        }

        return $this->ready();
    }

    public function allowsZaloWebhookVerify(): bool
    {
        return $this->zaloWebhookVerifyStatus()['state'] === 'ready';
    }

    /**
     * @return array{state:string,message:?string}
     */
    public function zaloWebhookDeliveryStatus(): array
    {
        if (! ClinicRuntimeSettings::boolean('zalo.enabled', false)) {
            return $this->skip('Zalo OA integration chưa bật.');
        }

        if (trim((string) ClinicRuntimeSettings::get('zalo.app_secret', '')) === '') {
            return $this->fail('Webhook signature verification misconfigured.');
        }

        return $this->ready();
    }

    public function allowsZaloWebhookDelivery(): bool
    {
        return $this->zaloWebhookDeliveryStatus()['state'] === 'ready';
    }

    /**
     * @return array{state:string,message:?string}
     */
    public function facebookWebhookVerifyStatus(): array
    {
        if (! ClinicRuntimeSettings::boolean('facebook.enabled', false)) {
            return $this->skip('Facebook Messenger integration chưa bật.');
        }

        if (ClinicRuntimeSettings::facebookWebhookVerifyToken() === '') {
            return $this->fail('Facebook webhook verify token chưa được cấu hình.');
        }

        return $this->ready();
    }

    public function allowsFacebookWebhookVerify(): bool
    {
        return $this->facebookWebhookVerifyStatus()['state'] === 'ready';
    }

    /**
     * @return array{state:string,message:?string}
     */
    public function facebookWebhookDeliveryStatus(): array
    {
        if (! ClinicRuntimeSettings::boolean('facebook.enabled', false)) {
            return $this->skip('Facebook Messenger integration chưa bật.');
        }

        if (ClinicRuntimeSettings::facebookAppSecret() === '') {
            return $this->fail('Facebook webhook signature verification misconfigured.');
        }

        return $this->ready();
    }

    public function allowsFacebookWebhookDelivery(): bool
    {
        return $this->facebookWebhookDeliveryStatus()['state'] === 'ready';
    }

    /**
     * @return array{state:string,message:?string}
     */
    public function googleCalendarSyncCommandStatus(): array
    {
        if (! ClinicRuntimeSettings::isGoogleCalendarEnabled()) {
            return $this->skip('Google Calendar integration đang tắt. Không có dữ liệu cần sync.');
        }

        if (! ClinicRuntimeSettings::googleCalendarAllowsPushToGoogle()) {
            return $this->skip('Google Calendar sync mode hiện không hỗ trợ CRM -> Google. Bỏ qua.');
        }

        return $this->runtimeStatus('google_calendar');
    }

    public function allowsGoogleCalendarPublish(): bool
    {
        return $this->googleCalendarSyncCommandStatus()['state'] === 'ready';
    }

    /**
     * @return array{state:string,message:?string}
     */
    public function znsAutomationSyncCommandStatus(): array
    {
        if (! ClinicRuntimeSettings::boolean('zns.enabled', false)) {
            return $this->skip('ZNS integration đang tắt. Không có event automation cần xử lý.');
        }

        return $this->runtimeStatus('zns', ' Không thể xử lý event automation.');
    }

    /**
     * @return array{state:string,message:?string}
     */
    public function znsCampaignCommandStatus(): array
    {
        if (! ClinicRuntimeSettings::boolean('zns.enabled', false)) {
            return $this->skip('ZNS đang tắt, bỏ qua chạy campaign.');
        }

        return $this->runtimeStatus('zns', ' Không thể chạy campaign ZNS.');
    }

    /**
     * @return array{state:string,message:?string}
     */
    public function znsCampaignWorkflowStatus(): array
    {
        if (! ClinicRuntimeSettings::boolean('zns.enabled', false)) {
            return $this->fail('ZNS đang tắt, không thể chạy campaign.');
        }

        $providerHealth = $this->providerHealth('zns');
        $runtimeErrorMessage = $this->runtimeErrorMessage('zns');

        if ($runtimeErrorMessage !== null) {
            return $this->fail($runtimeErrorMessage);
        }

        if (($providerHealth['score'] ?? 0) < 80) {
            return $this->fail('ZNS chưa sẵn sàng: '.implode(' ', $providerHealth['issues'] ?? []));
        }

        return $this->ready();
    }

    public function allowsZnsPublish(): bool
    {
        return ClinicRuntimeSettings::boolean('zns.enabled', false)
            && $this->runtimeErrorMessage('zns') === null;
    }

    /**
     * @return array{state:string,message:?string}
     */
    public function webLeadIngressStatus(): array
    {
        if (! ClinicRuntimeSettings::boolean('web_lead.enabled', false)) {
            return $this->skip('Web lead API chưa được bật.');
        }

        if (trim((string) ClinicRuntimeSettings::get('web_lead.api_token', '')) === '') {
            return $this->fail('Web lead API token chưa được cấu hình.');
        }

        return $this->ready();
    }

    public function allowsWebLeadIngress(): bool
    {
        return $this->webLeadIngressStatus()['state'] === 'ready';
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
    public function providerHealth(string $providerKey): array
    {
        return $this->providerHealthCache[$providerKey]
            ??= $this->integrationProviderHealthReadModelService->provider($providerKey);
    }

    public function runtimeErrorMessage(string $providerKey): ?string
    {
        $runtimeErrorMessage = $this->providerHealth($providerKey)['runtime_error_message'] ?? null;

        if (! is_string($runtimeErrorMessage) || trim($runtimeErrorMessage) === '') {
            return null;
        }

        return trim($runtimeErrorMessage);
    }

    /**
     * @return array{state:string,message:?string}
     */
    protected function runtimeStatus(string $providerKey, string $failureSuffix = ''): array
    {
        $runtimeErrorMessage = $this->runtimeErrorMessage($providerKey);

        if ($runtimeErrorMessage !== null) {
            return $this->fail($runtimeErrorMessage.$failureSuffix);
        }

        return $this->ready();
    }

    /**
     * @return array{state:string,message:?string}
     */
    protected function ready(): array
    {
        return [
            'state' => 'ready',
            'message' => null,
        ];
    }

    /**
     * @return array{state:string,message:?string}
     */
    protected function skip(string $message): array
    {
        return [
            'state' => 'skip',
            'message' => $message,
        ];
    }

    /**
     * @return array{state:string,message:?string}
     */
    protected function fail(string $message): array
    {
        return [
            'state' => 'fail',
            'message' => $message,
        ];
    }
}
