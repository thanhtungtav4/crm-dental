<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\ClinicSetting;
use Illuminate\Database\Seeder;

class ClinicSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaultBranchCode = Branch::query()
            ->where('active', true)
            ->orderBy('id')
            ->value('code') ?? '';

        $items = [
            // Zalo OA
            ['group' => 'zalo', 'key' => 'zalo.enabled', 'label' => 'Bật tích hợp Zalo OA', 'value' => false, 'value_type' => 'boolean', 'is_secret' => false, 'sort_order' => 10, 'description' => 'Bật/tắt đồng bộ Zalo OA.'],
            ['group' => 'zalo', 'key' => 'zalo.oa_id', 'label' => 'OA ID', 'value' => '', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 20],
            ['group' => 'zalo', 'key' => 'zalo.app_id', 'label' => 'App ID', 'value' => '', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 30],
            ['group' => 'zalo', 'key' => 'zalo.app_secret', 'label' => 'App Secret', 'value' => '', 'value_type' => 'text', 'is_secret' => true, 'sort_order' => 40],
            ['group' => 'zalo', 'key' => 'zalo.webhook_token', 'label' => 'Webhook Verify Token', 'value' => '', 'value_type' => 'text', 'is_secret' => true, 'sort_order' => 50],
            ['group' => 'zalo', 'key' => 'zalo.webhook_rate_limit_per_minute', 'label' => 'Giới hạn webhook / phút', 'value' => 120, 'value_type' => 'integer', 'is_secret' => false, 'sort_order' => 55],

            // ZNS
            ['group' => 'zns', 'key' => 'zns.enabled', 'label' => 'Bật tích hợp ZNS', 'value' => false, 'value_type' => 'boolean', 'is_secret' => false, 'sort_order' => 110],
            ['group' => 'zns', 'key' => 'zns.access_token', 'label' => 'ZNS Access Token', 'value' => '', 'value_type' => 'text', 'is_secret' => true, 'sort_order' => 120],
            ['group' => 'zns', 'key' => 'zns.refresh_token', 'label' => 'ZNS Refresh Token', 'value' => '', 'value_type' => 'text', 'is_secret' => true, 'sort_order' => 130],
            ['group' => 'zns', 'key' => 'zns.auto_send_lead_welcome', 'label' => 'Tự gửi lead welcome', 'value' => false, 'value_type' => 'boolean', 'is_secret' => false, 'sort_order' => 135],
            ['group' => 'zns', 'key' => 'zns.template_lead_welcome', 'label' => 'Template ID lead welcome', 'value' => '', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 136],
            ['group' => 'zns', 'key' => 'zns.auto_send_appointment_reminder', 'label' => 'Tự gửi nhắc lịch hẹn', 'value' => false, 'value_type' => 'boolean', 'is_secret' => false, 'sort_order' => 137],
            ['group' => 'zns', 'key' => 'zns.appointment_reminder_default_hours', 'label' => 'Số giờ nhắc hẹn mặc định', 'value' => 24, 'value_type' => 'integer', 'is_secret' => false, 'sort_order' => 138],
            ['group' => 'zns', 'key' => 'zns.template_appointment', 'label' => 'Template ID Nhắc lịch hẹn', 'value' => '', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 140],
            ['group' => 'zns', 'key' => 'zns.template_payment', 'label' => 'Template ID Nhắc thanh toán', 'value' => '', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 150],
            ['group' => 'zns', 'key' => 'zns.auto_send_birthday', 'label' => 'Tự gửi chúc mừng sinh nhật', 'value' => false, 'value_type' => 'boolean', 'is_secret' => false, 'sort_order' => 151],
            ['group' => 'zns', 'key' => 'zns.template_birthday', 'label' => 'Template ID sinh nhật', 'value' => '', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 152],
            ['group' => 'zns', 'key' => 'zns.send_endpoint', 'label' => 'ZNS send endpoint', 'value' => 'https://business.openapi.zalo.me/message/template', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 160],
            ['group' => 'zns', 'key' => 'zns.request_timeout_seconds', 'label' => 'ZNS request timeout (seconds)', 'value' => 15, 'value_type' => 'integer', 'is_secret' => false, 'sort_order' => 170],
            ['group' => 'zns', 'key' => 'zns.campaign_delivery_max_attempts', 'label' => 'Số lần retry tối đa cho mỗi người nhận campaign', 'value' => 5, 'value_type' => 'integer', 'is_secret' => false, 'sort_order' => 171],

            // Google Calendar
            ['group' => 'google_calendar', 'key' => 'google_calendar.enabled', 'label' => 'Bật tích hợp Google Calendar', 'value' => false, 'value_type' => 'boolean', 'is_secret' => false, 'sort_order' => 210],
            ['group' => 'google_calendar', 'key' => 'google_calendar.client_id', 'label' => 'Client ID', 'value' => '', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 220],
            ['group' => 'google_calendar', 'key' => 'google_calendar.client_secret', 'label' => 'Client Secret', 'value' => '', 'value_type' => 'text', 'is_secret' => true, 'sort_order' => 230],
            ['group' => 'google_calendar', 'key' => 'google_calendar.refresh_token', 'label' => 'Refresh Token', 'value' => '', 'value_type' => 'text', 'is_secret' => true, 'sort_order' => 235],
            ['group' => 'google_calendar', 'key' => 'google_calendar.account_email', 'label' => 'Google Account Email', 'value' => '', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 238],
            ['group' => 'google_calendar', 'key' => 'google_calendar.calendar_id', 'label' => 'Calendar ID', 'value' => '', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 240],
            ['group' => 'google_calendar', 'key' => 'google_calendar.sync_mode', 'label' => 'Chế độ đồng bộ', 'value' => 'one_way_to_google', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 250],

            // VNPay
            ['group' => 'vnpay', 'key' => 'vnpay.enabled', 'label' => 'Bật tích hợp VNPay', 'value' => false, 'value_type' => 'boolean', 'is_secret' => false, 'sort_order' => 310],
            ['group' => 'vnpay', 'key' => 'vnpay.tmn_code', 'label' => 'TMN Code', 'value' => '', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 320],
            ['group' => 'vnpay', 'key' => 'vnpay.hash_secret', 'label' => 'Hash Secret', 'value' => '', 'value_type' => 'text', 'is_secret' => true, 'sort_order' => 330],
            ['group' => 'vnpay', 'key' => 'vnpay.return_url', 'label' => 'Return URL', 'value' => '', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 340],
            ['group' => 'vnpay', 'key' => 'vnpay.ipn_url', 'label' => 'IPN URL', 'value' => '', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 350],
            ['group' => 'vnpay', 'key' => 'vnpay.sandbox', 'label' => 'Chế độ Sandbox', 'value' => true, 'value_type' => 'boolean', 'is_secret' => false, 'sort_order' => 360],

            // EMR
            ['group' => 'emr', 'key' => 'emr.enabled', 'label' => 'Bật tích hợp EMR', 'value' => false, 'value_type' => 'boolean', 'is_secret' => false, 'sort_order' => 410],
            ['group' => 'emr', 'key' => 'emr.provider', 'label' => 'Nhà cung cấp EMR', 'value' => 'internal', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 420],
            ['group' => 'emr', 'key' => 'emr.base_url', 'label' => 'EMR Base URL', 'value' => '', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 430],
            ['group' => 'emr', 'key' => 'emr.api_key', 'label' => 'EMR API Key', 'value' => '', 'value_type' => 'text', 'is_secret' => true, 'sort_order' => 440],
            ['group' => 'emr', 'key' => 'emr.clinic_code', 'label' => 'Mã cơ sở EMR', 'value' => '', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 450],

            // Web lead API
            ['group' => 'web_lead', 'key' => 'web_lead.enabled', 'label' => 'Bật API nhận lead từ web', 'value' => false, 'value_type' => 'boolean', 'is_secret' => false, 'sort_order' => 460],
            ['group' => 'web_lead', 'key' => 'web_lead.api_token', 'label' => 'Web lead API token', 'value' => '', 'value_type' => 'text', 'is_secret' => true, 'sort_order' => 470],
            ['group' => 'web_lead', 'key' => 'web_lead.default_branch_code', 'label' => 'Chi nhánh mặc định cho web lead', 'value' => $defaultBranchCode, 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 480],
            ['group' => 'web_lead', 'key' => 'web_lead.rate_limit_per_minute', 'label' => 'Giới hạn request web lead / phút', 'value' => 60, 'value_type' => 'integer', 'is_secret' => false, 'sort_order' => 490],
            ['group' => 'web_lead', 'key' => 'web_lead.realtime_notification_enabled', 'label' => 'Bật thông báo realtime web lead', 'value' => false, 'value_type' => 'boolean', 'is_secret' => false, 'sort_order' => 492],
            ['group' => 'web_lead', 'key' => 'web_lead.realtime_notification_roles', 'label' => 'Nhóm quyền nhận thông báo realtime web lead', 'value' => ['CSKH'], 'value_type' => 'json', 'is_secret' => false, 'sort_order' => 493],

            // Care runtime settings
            ['group' => 'care', 'key' => 'care.medication_reminder_offset_days', 'label' => 'Số ngày nhắc uống thuốc', 'value' => 0, 'value_type' => 'integer', 'is_secret' => false, 'sort_order' => 510],
            ['group' => 'care', 'key' => 'care.post_treatment_follow_up_offset_days', 'label' => 'Số ngày hỏi thăm sau điều trị', 'value' => 3, 'value_type' => 'integer', 'is_secret' => false, 'sort_order' => 520],
            ['group' => 'care', 'key' => 'care.default_channel', 'label' => 'Kênh CSKH mặc định', 'value' => 'call', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 530],
        ];

        foreach ($items as $item) {
            $this->seedSetting($item);
        }
    }

    /**
     * @param  array{
     *     group: string,
     *     key: string,
     *     label: string,
     *     value: mixed,
     *     value_type: string,
     *     is_secret: bool,
     *     sort_order: int,
     *     description?: string|null
     * }  $item
     */
    private function seedSetting(array $item): void
    {
        ClinicSetting::flushRuntimeCache($item['key']);

        $value = ClinicSetting::query()->where('key', $item['key'])->exists()
            ? ClinicSetting::getValue($item['key'], $item['value'])
            : $item['value'];

        ClinicSetting::setValue(
            key: $item['key'],
            value: $value,
            meta: [
                'group' => $item['group'],
                'label' => $item['label'],
                'value_type' => $item['value_type'],
                'is_secret' => $item['is_secret'],
                'is_active' => true,
                'sort_order' => $item['sort_order'],
                'description' => $item['description'] ?? null,
            ],
        );
    }
}
