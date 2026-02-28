<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('clinic_settings')) {
            return;
        }

        $now = now();

        $settings = [
            [
                'group' => 'web_lead',
                'key' => 'web_lead.realtime_notification_enabled',
                'label' => 'Bật thông báo realtime web lead',
                'value' => '0',
                'value_type' => 'boolean',
                'is_secret' => false,
                'is_active' => true,
                'sort_order' => 492,
                'description' => 'Bật/tắt gửi thông báo realtime khi có lead từ website.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'group' => 'web_lead',
                'key' => 'web_lead.realtime_notification_roles',
                'label' => 'Nhóm quyền nhận thông báo realtime web lead',
                'value' => json_encode(['CSKH'], JSON_UNESCAPED_UNICODE),
                'value_type' => 'json',
                'is_secret' => false,
                'is_active' => true,
                'sort_order' => 493,
                'description' => 'Danh sách role nhận thông báo realtime web lead.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('clinic_settings')->updateOrInsert(
                ['key' => $setting['key']],
                $setting,
            );
        }
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('clinic_settings')) {
            return;
        }

        DB::table('clinic_settings')
            ->whereIn('key', [
                'web_lead.realtime_notification_enabled',
                'web_lead.realtime_notification_roles',
            ])
            ->delete();
    }
};
