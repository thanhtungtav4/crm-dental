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
        $templateAppointment = trim((string) ClinicRuntimeSettings::get('zns.template_appointment', ''));
        $templatePayment = trim((string) ClinicRuntimeSettings::get('zns.template_payment', ''));

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

        if ($templateAppointment === '' && $templatePayment === '') {
            $issues[] = 'Thiếu template ZNS (nhắc lịch hoặc nhắc thanh toán).';
        }

        if ($enabled && $accessToken !== '' && $refreshToken !== '') {
            $recommendations[] = 'Thiết lập quy trình xoay vòng token định kỳ và giám sát hạn token.';
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
