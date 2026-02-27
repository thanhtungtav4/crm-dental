<?php

namespace Database\Seeders;

use App\Models\ClinicSetting;
use Illuminate\Database\Seeder;

class ClinicSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // Zalo OA
            ['group' => 'zalo', 'key' => 'zalo.enabled', 'label' => 'Bật tích hợp Zalo OA', 'value' => false, 'value_type' => 'boolean', 'is_secret' => false, 'sort_order' => 10, 'description' => 'Bật/tắt đồng bộ Zalo OA.'],
            ['group' => 'zalo', 'key' => 'zalo.oa_id', 'label' => 'OA ID', 'value' => '', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 20],
            ['group' => 'zalo', 'key' => 'zalo.app_id', 'label' => 'App ID', 'value' => '', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 30],
            ['group' => 'zalo', 'key' => 'zalo.app_secret', 'label' => 'App Secret', 'value' => '', 'value_type' => 'text', 'is_secret' => true, 'sort_order' => 40],
            ['group' => 'zalo', 'key' => 'zalo.webhook_token', 'label' => 'Webhook Verify Token', 'value' => '', 'value_type' => 'text', 'is_secret' => true, 'sort_order' => 50],

            // ZNS
            ['group' => 'zns', 'key' => 'zns.enabled', 'label' => 'Bật tích hợp ZNS', 'value' => false, 'value_type' => 'boolean', 'is_secret' => false, 'sort_order' => 110],
            ['group' => 'zns', 'key' => 'zns.access_token', 'label' => 'ZNS Access Token', 'value' => '', 'value_type' => 'text', 'is_secret' => true, 'sort_order' => 120],
            ['group' => 'zns', 'key' => 'zns.refresh_token', 'label' => 'ZNS Refresh Token', 'value' => '', 'value_type' => 'text', 'is_secret' => true, 'sort_order' => 130],
            ['group' => 'zns', 'key' => 'zns.template_appointment', 'label' => 'Template ID Nhắc lịch hẹn', 'value' => '', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 140],
            ['group' => 'zns', 'key' => 'zns.template_payment', 'label' => 'Template ID Nhắc thanh toán', 'value' => '', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 150],

            // Google Calendar
            ['group' => 'google_calendar', 'key' => 'google_calendar.enabled', 'label' => 'Bật tích hợp Google Calendar', 'value' => false, 'value_type' => 'boolean', 'is_secret' => false, 'sort_order' => 210],
            ['group' => 'google_calendar', 'key' => 'google_calendar.client_id', 'label' => 'Client ID', 'value' => '', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 220],
            ['group' => 'google_calendar', 'key' => 'google_calendar.client_secret', 'label' => 'Client Secret', 'value' => '', 'value_type' => 'text', 'is_secret' => true, 'sort_order' => 230],
            ['group' => 'google_calendar', 'key' => 'google_calendar.calendar_id', 'label' => 'Calendar ID', 'value' => '', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 240],
            ['group' => 'google_calendar', 'key' => 'google_calendar.sync_mode', 'label' => 'Chế độ đồng bộ', 'value' => 'two_way', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 250],

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
            ['group' => 'web_lead', 'key' => 'web_lead.default_branch_code', 'label' => 'Chi nhánh mặc định cho web lead', 'value' => '', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 480],
            ['group' => 'web_lead', 'key' => 'web_lead.rate_limit_per_minute', 'label' => 'Giới hạn request web lead / phút', 'value' => 60, 'value_type' => 'integer', 'is_secret' => false, 'sort_order' => 490],

            // Care runtime settings
            ['group' => 'care', 'key' => 'care.medication_reminder_offset_days', 'label' => 'Số ngày nhắc uống thuốc', 'value' => 0, 'value_type' => 'integer', 'is_secret' => false, 'sort_order' => 510],
            ['group' => 'care', 'key' => 'care.post_treatment_follow_up_offset_days', 'label' => 'Số ngày hỏi thăm sau điều trị', 'value' => 3, 'value_type' => 'integer', 'is_secret' => false, 'sort_order' => 520],
            ['group' => 'care', 'key' => 'care.default_channel', 'label' => 'Kênh CSKH mặc định', 'value' => 'call', 'value_type' => 'text', 'is_secret' => false, 'sort_order' => 530],
        ];

        foreach ($items as $item) {
            ClinicSetting::setValue(
                key: $item['key'],
                value: $item['value'],
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
}
