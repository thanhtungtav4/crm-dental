<?php

namespace App\Services;

use App\Support\ClinicRuntimeSettings;

class ZaloIntegrationService
{
    /**
     * @return array{enabled:bool,score:int,issues:array<int,string>,recommendations:array<int,string>,webhook_url:string}
     */
    public function auditOaReadiness(): array
    {
        $enabled = ClinicRuntimeSettings::boolean('zalo.enabled', false);
        $oaId = trim((string) ClinicRuntimeSettings::get('zalo.oa_id', ''));
        $appId = trim((string) ClinicRuntimeSettings::get('zalo.app_id', ''));
        $appSecret = trim((string) ClinicRuntimeSettings::get('zalo.app_secret', ''));
        $webhookToken = trim((string) ClinicRuntimeSettings::get('zalo.webhook_token', ''));

        $issues = [];
        $recommendations = [];

        if (! $enabled) {
            $issues[] = 'Zalo OA đang tắt.';
            $recommendations[] = 'Bật toggle "Bật tích hợp Zalo OA" trước khi cấu hình webhook.';
        }

        if ($oaId === '') {
            $issues[] = 'Thiếu OA ID.';
        }

        if ($appId === '') {
            $issues[] = 'Thiếu App ID.';
        }

        if ($appSecret === '') {
            $issues[] = 'Thiếu App Secret.';
        }

        if ($webhookToken === '') {
            $issues[] = 'Thiếu Webhook Verify Token.';
        } elseif (mb_strlen($webhookToken) < 24) {
            $issues[] = 'Webhook Verify Token quá ngắn (< 24 ký tự).';
            $recommendations[] = 'Dùng token ngẫu nhiên ít nhất 24 ký tự để giảm rủi ro bị đoán.';
        }

        if ($enabled && $webhookToken !== '' && mb_strlen($webhookToken) >= 24) {
            $recommendations[] = 'Đăng ký webhook URL trong Zalo OA Console và bật HTTPS-only.';
        }

        if (ClinicRuntimeSettings::zaloAccessToken() === '') {
            $recommendations[] = 'Cấu hình access token để gửi phản hồi từ CRM inbox.';
        }

        if (ClinicRuntimeSettings::zaloInboxDefaultBranchCode() === '') {
            $recommendations[] = 'Chọn chi nhánh mặc định để hội thoại mới được route đúng queue CSKH.';
        }

        return [
            'enabled' => $enabled,
            'score' => $this->calculateScore($issues),
            'issues' => $issues,
            'recommendations' => array_values(array_unique($recommendations)),
            'webhook_url' => route('api.v1.integrations.zalo.webhook'),
        ];
    }

    /**
     * @return array{enabled:bool,score:int,issues:array<int,string>,recommendations:array<int,string>}
     */
    public function auditZnsReadiness(): array
    {
        $enabled = ClinicRuntimeSettings::boolean('zns.enabled', false);
        $accessToken = trim((string) ClinicRuntimeSettings::get('zns.access_token', ''));
        $refreshToken = trim((string) ClinicRuntimeSettings::get('zns.refresh_token', ''));
        $templateLeadWelcome = ClinicRuntimeSettings::znsTemplateLeadWelcome();
        $templateAppointment = ClinicRuntimeSettings::znsTemplateAppointment();
        $templatePayment = ClinicRuntimeSettings::znsTemplatePayment();
        $templateBirthday = ClinicRuntimeSettings::znsTemplateBirthday();
        $autoLeadWelcome = ClinicRuntimeSettings::znsAutoSendLeadWelcome();
        $autoAppointmentReminder = ClinicRuntimeSettings::znsAutoSendAppointmentReminder();
        $autoBirthdayGreeting = ClinicRuntimeSettings::znsAutoSendBirthdayGreeting();
        $sendEndpoint = trim((string) ClinicRuntimeSettings::znsSendEndpoint());
        $timeoutSeconds = ClinicRuntimeSettings::znsRequestTimeoutSeconds();

        $issues = [];
        $recommendations = [];

        if (! $enabled) {
            $issues[] = 'ZNS đang tắt.';
            $recommendations[] = 'Bật toggle "Bật tích hợp ZNS" khi đã có token hợp lệ.';
        }

        if ($accessToken === '') {
            $issues[] = 'Thiếu Access Token.';
        }

        if ($refreshToken === '') {
            $issues[] = 'Thiếu Refresh Token.';
        }

        if (
            $templateLeadWelcome === ''
            && $templateAppointment === ''
            && $templatePayment === ''
            && $templateBirthday === ''
        ) {
            $issues[] = 'Thiếu template ZNS (lead welcome/nhắc lịch/nhắc thanh toán/sinh nhật).';
        }

        if ($sendEndpoint === '') {
            $issues[] = 'Thiếu ZNS send endpoint.';
        }

        if ($timeoutSeconds < 3 || $timeoutSeconds > 30) {
            $issues[] = 'Timeout gọi ZNS ngoài phạm vi an toàn (3-30 giây).';
        }

        if ($autoLeadWelcome && $templateLeadWelcome === '') {
            $issues[] = 'Đã bật auto gửi lead welcome nhưng chưa cấu hình template lead welcome.';
        }

        if ($autoAppointmentReminder && $templateAppointment === '') {
            $issues[] = 'Đã bật auto gửi nhắc lịch hẹn nhưng chưa cấu hình template appointment.';
        }

        if ($autoBirthdayGreeting && $templateBirthday === '') {
            $issues[] = 'Đã bật auto gửi chúc mừng sinh nhật nhưng chưa cấu hình template birthday.';
        }

        if ($enabled && $accessToken !== '' && $refreshToken !== '' && $sendEndpoint !== '') {
            $recommendations[] = 'Thiết lập quy trình xoay vòng token định kỳ và giám sát hạn token.';
        }

        if ($enabled && ! $autoLeadWelcome && ! $autoAppointmentReminder && ! $autoBirthdayGreeting) {
            $recommendations[] = 'Bật ít nhất một luồng automation ZNS (lead welcome/nhắc hẹn/sinh nhật) để khai thác kênh chăm sóc tự động.';
        }

        return [
            'enabled' => $enabled,
            'score' => $this->calculateScore($issues),
            'issues' => $issues,
            'recommendations' => array_values(array_unique($recommendations)),
        ];
    }

    /**
     * @param  array<int, string>  $issues
     */
    protected function calculateScore(array $issues): int
    {
        return max(0, 100 - (count($issues) * 20));
    }
}
