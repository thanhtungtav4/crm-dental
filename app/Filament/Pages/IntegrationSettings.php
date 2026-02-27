<?php

namespace App\Filament\Pages;

use App\Models\ClinicSetting;
use App\Models\ClinicSettingLog;
use App\Services\EmrIntegrationService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use UnitEnum;

class IntegrationSettings extends Page
{
    use HasPageShield;

    public const AUDIT_LOG_PERMISSION = 'View:IntegrationSettingsAuditLog';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = 'Cài đặt tích hợp';

    protected static string|UnitEnum|null $navigationGroup = 'Cài đặt hệ thống';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'integration-settings';

    protected string $view = 'filament.pages.integration-settings';

    public array $settings = [];

    public function mount(): void
    {
        $this->loadSettingsState();
    }

    public function getHeading(): string
    {
        return 'Cài đặt tích hợp';
    }

    public function getTitle(): string
    {
        return $this->getHeading();
    }

    public function getSubheading(): string
    {
        return 'Quản lý cấu hình kết nối Zalo, ZNS, Google Calendar, EMR và runtime CSKH.';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getProviders(): array
    {
        return [
            [
                'group' => 'zalo',
                'title' => 'Zalo OA',
                'description' => 'Thiết lập thông tin kết nối OA và webhook.',
                'fields' => [
                    ['state' => 'zalo_enabled', 'key' => 'zalo.enabled', 'label' => 'Bật tích hợp Zalo OA', 'type' => 'boolean', 'default' => false, 'sort_order' => 10],
                    ['state' => 'zalo_oa_id', 'key' => 'zalo.oa_id', 'label' => 'OA ID', 'type' => 'text', 'default' => '', 'sort_order' => 20],
                    ['state' => 'zalo_app_id', 'key' => 'zalo.app_id', 'label' => 'App ID', 'type' => 'text', 'default' => '', 'sort_order' => 30],
                    ['state' => 'zalo_app_secret', 'key' => 'zalo.app_secret', 'label' => 'App Secret', 'type' => 'text', 'default' => '', 'is_secret' => true, 'sort_order' => 40],
                    ['state' => 'zalo_webhook_token', 'key' => 'zalo.webhook_token', 'label' => 'Webhook Verify Token', 'type' => 'text', 'default' => '', 'is_secret' => true, 'sort_order' => 50],
                ],
            ],
            [
                'group' => 'zns',
                'title' => 'Zalo ZNS',
                'description' => 'Thiết lập token và mẫu tin ZNS cho các luồng CSKH.',
                'fields' => [
                    ['state' => 'zns_enabled', 'key' => 'zns.enabled', 'label' => 'Bật tích hợp ZNS', 'type' => 'boolean', 'default' => false, 'sort_order' => 110],
                    ['state' => 'zns_access_token', 'key' => 'zns.access_token', 'label' => 'Access Token', 'type' => 'text', 'default' => '', 'is_secret' => true, 'sort_order' => 120],
                    ['state' => 'zns_refresh_token', 'key' => 'zns.refresh_token', 'label' => 'Refresh Token', 'type' => 'text', 'default' => '', 'is_secret' => true, 'sort_order' => 130],
                    ['state' => 'zns_template_appointment', 'key' => 'zns.template_appointment', 'label' => 'Template nhắc lịch hẹn', 'type' => 'text', 'default' => '', 'sort_order' => 140],
                    ['state' => 'zns_template_payment', 'key' => 'zns.template_payment', 'label' => 'Template nhắc thanh toán', 'type' => 'text', 'default' => '', 'sort_order' => 150],
                ],
            ],
            [
                'group' => 'google_calendar',
                'title' => 'Google Calendar',
                'description' => 'Đồng bộ lịch hẹn với Google Calendar.',
                'fields' => [
                    ['state' => 'google_calendar_enabled', 'key' => 'google_calendar.enabled', 'label' => 'Bật tích hợp Google Calendar', 'type' => 'boolean', 'default' => false, 'sort_order' => 210],
                    ['state' => 'google_calendar_client_id', 'key' => 'google_calendar.client_id', 'label' => 'Client ID', 'type' => 'text', 'default' => '', 'sort_order' => 220],
                    ['state' => 'google_calendar_client_secret', 'key' => 'google_calendar.client_secret', 'label' => 'Client Secret', 'type' => 'text', 'default' => '', 'is_secret' => true, 'sort_order' => 230],
                    ['state' => 'google_calendar_calendar_id', 'key' => 'google_calendar.calendar_id', 'label' => 'Calendar ID', 'type' => 'text', 'default' => '', 'sort_order' => 240],
                    ['state' => 'google_calendar_sync_mode', 'key' => 'google_calendar.sync_mode', 'label' => 'Chế độ đồng bộ', 'type' => 'text', 'default' => 'two_way', 'sort_order' => 250],
                ],
            ],
            [
                'group' => 'emr',
                'title' => 'EMR',
                'description' => 'Thiết lập tích hợp bệnh án điện tử (EMR).',
                'fields' => [
                    ['state' => 'emr_enabled', 'key' => 'emr.enabled', 'label' => 'Bật tích hợp EMR', 'type' => 'boolean', 'default' => false, 'sort_order' => 410],
                    ['state' => 'emr_provider', 'key' => 'emr.provider', 'label' => 'Nhà cung cấp', 'type' => 'text', 'default' => 'internal', 'sort_order' => 420],
                    ['state' => 'emr_base_url', 'key' => 'emr.base_url', 'label' => 'Base URL', 'type' => 'url', 'default' => '', 'sort_order' => 430],
                    ['state' => 'emr_api_key', 'key' => 'emr.api_key', 'label' => 'API Key', 'type' => 'text', 'default' => '', 'is_secret' => true, 'sort_order' => 440],
                    ['state' => 'emr_clinic_code', 'key' => 'emr.clinic_code', 'label' => 'Mã cơ sở', 'type' => 'text', 'default' => '', 'sort_order' => 450],
                ],
            ],
            [
                'group' => 'care',
                'title' => 'Runtime CSKH',
                'description' => 'Cấu hình thời gian nhắc việc và kênh mặc định cho các ticket CSKH tự động.',
                'fields' => [
                    ['state' => 'care_medication_reminder_offset_days', 'key' => 'care.medication_reminder_offset_days', 'label' => 'Số ngày nhắc uống thuốc (sau ngày điều trị)', 'type' => 'integer', 'default' => 0, 'sort_order' => 510],
                    ['state' => 'care_post_treatment_follow_up_offset_days', 'key' => 'care.post_treatment_follow_up_offset_days', 'label' => 'Số ngày hỏi thăm sau điều trị', 'type' => 'integer', 'default' => 3, 'sort_order' => 520],
                    ['state' => 'care_recall_default_offset_days', 'key' => 'care.recall_default_offset_days', 'label' => 'Số ngày recall mặc định sau thủ thuật hoàn tất', 'type' => 'integer', 'default' => 180, 'sort_order' => 525],
                    [
                        'state' => 'care_default_channel',
                        'key' => 'care.default_channel',
                        'label' => 'Kênh CSKH mặc định',
                        'type' => 'select',
                        'default' => 'call',
                        'options' => [
                            'call' => 'Gọi điện',
                            'zalo' => 'Zalo OA',
                            'zns' => 'ZNS',
                            'sms' => 'SMS',
                            'email' => 'Email',
                            'facebook' => 'Facebook',
                            'other' => 'Khác',
                        ],
                        'sort_order' => 530,
                    ],
                    ['state' => 'care_no_show_recovery_delay_hours', 'key' => 'care.no_show_recovery_delay_hours', 'label' => 'Số giờ chờ trước khi tạo ticket no-show recovery', 'type' => 'integer', 'default' => 2, 'sort_order' => 540],
                    ['state' => 'care_plan_follow_up_delay_days', 'key' => 'care.plan_follow_up_delay_days', 'label' => 'Số ngày theo dõi kế hoạch chưa chốt', 'type' => 'integer', 'default' => 2, 'sort_order' => 550],
                    ['state' => 'care_invoice_aging_reminder_delay_days', 'key' => 'care.invoice_aging_reminder_delay_days', 'label' => 'Số ngày quá hạn trước khi nhắc thanh toán', 'type' => 'integer', 'default' => 1, 'sort_order' => 560],
                    ['state' => 'care_invoice_aging_reminder_min_interval_hours', 'key' => 'care.invoice_aging_reminder_min_interval_hours', 'label' => 'Khoảng cách tối thiểu giữa 2 lần nhắc thanh toán (giờ)', 'type' => 'integer', 'default' => 24, 'sort_order' => 570],
                ],
            ],
            [
                'group' => 'report',
                'title' => 'Runtime báo cáo',
                'description' => 'SLA cho snapshot KPI vận hành và kiểm tra data lineage.',
                'fields' => [
                    ['state' => 'report_snapshot_sla_hours', 'key' => 'report.snapshot_sla_hours', 'label' => 'SLA tạo snapshot (giờ)', 'type' => 'integer', 'default' => 6, 'sort_order' => 580],
                    ['state' => 'report_snapshot_stale_after_hours', 'key' => 'report.snapshot_stale_after_hours', 'label' => 'Ngưỡng stale snapshot (giờ)', 'type' => 'integer', 'default' => 24, 'sort_order' => 590],
                ],
            ],
            [
                'group' => 'scheduler',
                'title' => 'Runtime scheduler',
                'description' => 'Policy timeout/retry/alert cho scheduler command chạy automation.',
                'fields' => [
                    [
                        'state' => 'scheduler_automation_actor_user_id',
                        'key' => 'scheduler.automation_actor_user_id',
                        'label' => 'User ID service account chạy scheduler',
                        'type' => 'integer',
                        'default' => config('care.scheduler_automation_actor_user_id'),
                        'sort_order' => 592,
                    ],
                    [
                        'state' => 'scheduler_automation_actor_required_role',
                        'key' => 'scheduler.automation_actor_required_role',
                        'label' => 'Role bắt buộc cho scheduler actor',
                        'type' => 'text',
                        'default' => config('care.scheduler_automation_actor_required_role', 'AutomationService'),
                        'sort_order' => 593,
                    ],
                    ['state' => 'scheduler_command_timeout_seconds', 'key' => 'scheduler.command_timeout_seconds', 'label' => 'Timeout mỗi lần chạy command (giây)', 'type' => 'integer', 'default' => 180, 'sort_order' => 595],
                    ['state' => 'scheduler_command_max_attempts', 'key' => 'scheduler.command_max_attempts', 'label' => 'Số lần retry tối đa', 'type' => 'integer', 'default' => 2, 'sort_order' => 596],
                    ['state' => 'scheduler_command_retry_delay_seconds', 'key' => 'scheduler.command_retry_delay_seconds', 'label' => 'Thời gian chờ giữa các lần retry (giây)', 'type' => 'integer', 'default' => 15, 'sort_order' => 597],
                    ['state' => 'scheduler_command_alert_after_seconds', 'key' => 'scheduler.command_alert_after_seconds', 'label' => 'Ngưỡng cảnh báo SLA runtime (giây)', 'type' => 'integer', 'default' => 120, 'sort_order' => 598],
                ],
            ],
            [
                'group' => 'finance',
                'title' => 'Runtime tài chính',
                'description' => 'Cấu hình policy thu tiền cọc, thu trước và overpay.',
                'fields' => [
                    [
                        'state' => 'finance_allow_overpay',
                        'key' => 'finance.allow_overpay',
                        'label' => 'Cho phép thu vượt công nợ (overpay)',
                        'type' => 'boolean',
                        'default' => true,
                        'sort_order' => 610,
                    ],
                    [
                        'state' => 'finance_allow_prepay_draft',
                        'key' => 'finance.allow_prepay_draft',
                        'label' => 'Cho phép thu trước ở hóa đơn nháp',
                        'type' => 'boolean',
                        'default' => false,
                        'sort_order' => 620,
                    ],
                    [
                        'state' => 'finance_allow_deposit',
                        'key' => 'finance.allow_deposit',
                        'label' => 'Cho phép đánh dấu phiếu cọc',
                        'type' => 'boolean',
                        'default' => true,
                        'sort_order' => 630,
                    ],
                ],
            ],
            [
                'group' => 'loyalty',
                'title' => 'Runtime loyalty',
                'description' => 'Cấu hình điểm thưởng, referral và ngưỡng tier loyalty.',
                'fields' => [
                    [
                        'state' => 'loyalty_points_per_ten_thousand_vnd',
                        'key' => 'loyalty.points_per_ten_thousand_vnd',
                        'label' => 'Điểm nhận trên mỗi 10.000đ thanh toán',
                        'type' => 'integer',
                        'default' => 1,
                        'sort_order' => 640,
                    ],
                    [
                        'state' => 'loyalty_referral_bonus_referrer_points',
                        'key' => 'loyalty.referral_bonus_referrer_points',
                        'label' => 'Điểm thưởng cho người giới thiệu',
                        'type' => 'integer',
                        'default' => 100,
                        'sort_order' => 650,
                    ],
                    [
                        'state' => 'loyalty_referral_bonus_referee_points',
                        'key' => 'loyalty.referral_bonus_referee_points',
                        'label' => 'Điểm thưởng cho người được giới thiệu',
                        'type' => 'integer',
                        'default' => 50,
                        'sort_order' => 660,
                    ],
                    [
                        'state' => 'loyalty_reactivation_bonus_points',
                        'key' => 'loyalty.reactivation_bonus_points',
                        'label' => 'Điểm thưởng reactivation',
                        'type' => 'integer',
                        'default' => 80,
                        'sort_order' => 670,
                    ],
                    [
                        'state' => 'loyalty_reactivation_inactive_days',
                        'key' => 'loyalty.reactivation_inactive_days',
                        'label' => 'Số ngày không quay lại để vào chiến dịch reactivation',
                        'type' => 'integer',
                        'default' => 90,
                        'sort_order' => 680,
                    ],
                    [
                        'state' => 'loyalty_tier_silver_revenue_threshold',
                        'key' => 'loyalty.tier_silver_revenue_threshold',
                        'label' => 'Ngưỡng doanh thu lên hạng Silver (VNĐ)',
                        'type' => 'integer',
                        'default' => 5000000,
                        'sort_order' => 690,
                    ],
                    [
                        'state' => 'loyalty_tier_gold_revenue_threshold',
                        'key' => 'loyalty.tier_gold_revenue_threshold',
                        'label' => 'Ngưỡng doanh thu lên hạng Gold (VNĐ)',
                        'type' => 'integer',
                        'default' => 20000000,
                        'sort_order' => 700,
                    ],
                    [
                        'state' => 'loyalty_tier_platinum_revenue_threshold',
                        'key' => 'loyalty.tier_platinum_revenue_threshold',
                        'label' => 'Ngưỡng doanh thu lên hạng Platinum (VNĐ)',
                        'type' => 'integer',
                        'default' => 50000000,
                        'sort_order' => 710,
                    ],
                ],
            ],
            [
                'group' => 'risk',
                'title' => 'Runtime risk scoring',
                'description' => 'Cấu hình baseline model no-show/churn và ticket can thiệp nguy cơ cao.',
                'fields' => [
                    [
                        'state' => 'risk_no_show_window_days',
                        'key' => 'risk.no_show_window_days',
                        'label' => 'Window ngày cho feature no-show',
                        'type' => 'integer',
                        'default' => 90,
                        'sort_order' => 720,
                    ],
                    [
                        'state' => 'risk_medium_threshold',
                        'key' => 'risk.medium_threshold',
                        'label' => 'Ngưỡng risk mức Medium',
                        'type' => 'integer',
                        'default' => 45,
                        'sort_order' => 730,
                    ],
                    [
                        'state' => 'risk_high_threshold',
                        'key' => 'risk.high_threshold',
                        'label' => 'Ngưỡng risk mức High',
                        'type' => 'integer',
                        'default' => 70,
                        'sort_order' => 740,
                    ],
                    [
                        'state' => 'risk_auto_create_high_risk_ticket',
                        'key' => 'risk.auto_create_high_risk_ticket',
                        'label' => 'Tự tạo ticket CSKH cho bệnh nhân risk cao',
                        'type' => 'boolean',
                        'default' => true,
                        'sort_order' => 750,
                    ],
                ],
            ],
        ];
    }

    public function save(): void
    {
        $rules = [];

        foreach ($this->getProviders() as $provider) {
            foreach ($provider['fields'] as $field) {
                $attribute = "settings.{$field['state']}";

                if (($field['key'] ?? null) === 'scheduler.automation_actor_user_id') {
                    $rules[$attribute] = ['nullable', 'integer', 'exists:users,id'];

                    continue;
                }

                $rules[$attribute] = match ($field['type']) {
                    'boolean' => ['boolean'],
                    'url' => ['nullable', 'url', 'max:500'],
                    'integer' => ['nullable', 'integer'],
                    'select' => ['required', Rule::in(array_keys($field['options'] ?? []))],
                    default => ['nullable', 'string', 'max:3000'],
                };
            }
        }

        $validated = $this->validate($rules);

        foreach ($this->getProviders() as $provider) {
            foreach ($provider['fields'] as $field) {
                $statePath = "settings.{$field['state']}";
                $valueType = match ($field['type']) {
                    'url', 'select' => 'text',
                    default => $field['type'],
                };
                $value = data_get($validated, $statePath, $field['default'] ?? null);
                $normalizedNewValue = $this->normalizeValueForCompare($value, $valueType);
                $oldValue = ClinicSetting::getValue(
                    key: $field['key'],
                    default: $field['default'] ?? null,
                );
                $normalizedOldValue = $this->normalizeValueForCompare($oldValue, $valueType);

                $record = ClinicSetting::setValue(
                    key: $field['key'],
                    value: $value,
                    meta: [
                        'group' => $provider['group'],
                        'label' => $field['label'],
                        'value_type' => $valueType,
                        'is_secret' => (bool) ($field['is_secret'] ?? false),
                        'is_active' => true,
                        'sort_order' => (int) ($field['sort_order'] ?? 0),
                    ],
                );

                if ($normalizedOldValue === $normalizedNewValue) {
                    continue;
                }

                $this->logSettingChange(
                    setting: $record,
                    oldValue: $normalizedOldValue,
                    newValue: $normalizedNewValue,
                    isSecret: (bool) ($field['is_secret'] ?? false),
                );
            }
        }

        Notification::make()
            ->title('Đã lưu cài đặt tích hợp')
            ->success()
            ->send();
    }

    public function testEmrConnection(): void
    {
        $result = app(EmrIntegrationService::class)->authenticate();

        if (($result['success'] ?? false) === true) {
            Notification::make()
                ->title('Kết nối EMR thành công')
                ->body((string) ($result['message'] ?? 'Authenticate thành công.'))
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Kết nối EMR thất bại')
            ->body((string) ($result['message'] ?? 'Không thể kết nối tới EMR.'))
            ->danger()
            ->send();
    }

    public function openEmrConfigUrl(): void
    {
        $result = app(EmrIntegrationService::class)->resolveConfigUrl();

        if (($result['success'] ?? false) !== true || ! filled($result['url'] ?? null)) {
            Notification::make()
                ->title('Không thể mở trang cấu hình EMR')
                ->body((string) ($result['message'] ?? 'EMR chưa trả về URL cấu hình hợp lệ.'))
                ->warning()
                ->send();

            return;
        }

        $this->redirect((string) $result['url'], navigate: false);
    }

    protected function loadSettingsState(): void
    {
        $state = [];

        foreach ($this->getProviders() as $provider) {
            foreach ($provider['fields'] as $field) {
                $state[$field['state']] = ClinicSetting::getValue(
                    key: $field['key'],
                    default: $field['default'] ?? null,
                );
            }
        }

        $this->settings = $state;
    }

    public function getRecentLogs(): Collection
    {
        if (! $this->canViewAuditLogs() || ! Schema::hasTable('clinic_setting_logs')) {
            return collect();
        }

        return ClinicSettingLog::query()
            ->with('changedBy:id,name')
            ->latest('changed_at')
            ->limit(20)
            ->get();
    }

    protected function normalizeValueForCompare(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => is_numeric($value) ? (int) $value : 0,
            'json' => is_string($value) ? (json_decode($value, true) ?? []) : (array) $value,
            default => filled($value) ? trim((string) $value) : null,
        };
    }

    protected function logSettingChange(
        ClinicSetting $setting,
        mixed $oldValue,
        mixed $newValue,
        bool $isSecret = false,
    ): void {
        if (! Schema::hasTable('clinic_setting_logs')) {
            return;
        }

        ClinicSettingLog::query()->create([
            'clinic_setting_id' => $setting->id,
            'setting_group' => $setting->group ?? 'integration',
            'setting_key' => $setting->key,
            'setting_label' => $setting->label,
            'old_value' => $this->valueForAuditLog($oldValue, $isSecret),
            'new_value' => $this->valueForAuditLog($newValue, $isSecret),
            'is_secret' => $isSecret,
            'changed_by' => auth()->id(),
            'changed_at' => now(),
        ]);
    }

    public function canViewAuditLogs(): bool
    {
        return (bool) auth()->user()?->can(static::AUDIT_LOG_PERMISSION);
    }

    protected function valueForAuditLog(mixed $value, bool $isSecret): ?string
    {
        if ($isSecret) {
            return '••••••';
        }

        if ($value === null || $value === '') {
            return '(trống)';
        }

        if (is_bool($value)) {
            return $value ? 'Bật' : 'Tắt';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '(trống)';
        }

        return (string) $value;
    }
}
