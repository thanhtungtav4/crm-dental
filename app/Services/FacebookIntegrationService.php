<?php

namespace App\Services;

use App\Support\ClinicRuntimeSettings;

class FacebookIntegrationService
{
    /**
     * @return array{enabled:bool,score:int,issues:array<int,string>,recommendations:array<int,string>,webhook_url:string}
     */
    public function auditMessengerReadiness(): array
    {
        $enabled = ClinicRuntimeSettings::boolean('facebook.enabled', false);
        $pageId = ClinicRuntimeSettings::facebookPageId();
        $appId = ClinicRuntimeSettings::facebookAppId();
        $appSecret = ClinicRuntimeSettings::facebookAppSecret();
        $verifyToken = ClinicRuntimeSettings::facebookWebhookVerifyToken();
        $pageAccessToken = ClinicRuntimeSettings::facebookPageAccessToken();
        $sendEndpoint = ClinicRuntimeSettings::facebookSendEndpoint();
        $defaultBranchCode = ClinicRuntimeSettings::facebookInboxDefaultBranchCode();

        $issues = [];
        $recommendations = [];

        if (! $enabled) {
            $issues[] = 'Facebook Messenger đang tắt.';
            $recommendations[] = 'Bật toggle "Bật tích hợp Facebook Messenger" trước khi cấu hình webhook.';
        }

        if ($pageId === '') {
            $issues[] = 'Thiếu Page ID.';
        }

        if ($appId === '') {
            $issues[] = 'Thiếu App ID.';
        }

        if ($appSecret === '') {
            $issues[] = 'Thiếu App Secret.';
        }

        if ($verifyToken === '') {
            $issues[] = 'Thiếu Webhook Verify Token.';
        } elseif (mb_strlen($verifyToken) < 24) {
            $issues[] = 'Webhook Verify Token quá ngắn (< 24 ký tự).';
            $recommendations[] = 'Dùng token ngẫu nhiên ít nhất 24 ký tự để giảm rủi ro bị đoán.';
        }

        if ($pageAccessToken === '') {
            $issues[] = 'Thiếu Page Access Token.';
        }

        if ($sendEndpoint === '') {
            $issues[] = 'Thiếu Messenger send endpoint.';
        }

        if ($defaultBranchCode === '') {
            $recommendations[] = 'Chọn chi nhánh mặc định để hội thoại Messenger mới được route đúng queue CSKH.';
        }

        if ($enabled && $verifyToken !== '' && mb_strlen($verifyToken) >= 24) {
            $recommendations[] = 'Đăng ký webhook URL trong Meta App Dashboard và subscribe trường messages cho Page.';
        }

        return [
            'enabled' => $enabled,
            'score' => $this->calculateScore($issues),
            'issues' => $issues,
            'recommendations' => array_values(array_unique($recommendations)),
            'webhook_url' => route('api.v1.integrations.facebook.webhook'),
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
