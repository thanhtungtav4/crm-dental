<?php

namespace App\Filament\Pages;

use App\Models\ClinicSetting;
use App\Models\ClinicSettingLog;
use App\Services\EmrIntegrationService;
use App\Support\ClinicRuntimeSettings;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class IntegrationSettings extends Page
{
    use HasPageShield;

    public const AUDIT_LOG_PERMISSION = 'View:IntegrationSettingsAuditLog';

    private const EXAM_INDICATIONS_STATE = 'catalog_exam_indications_json';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = 'Cài đặt tích hợp';

    protected static string|UnitEnum|null $navigationGroup = 'Cài đặt hệ thống';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'integration-settings';

    protected string $view = 'filament.pages.integration-settings';

    public array $settings = [];

    /**
     * @var array<string, array<int, array{key: string, label: string, enabled: bool}>>
     */
    public array $catalogEditors = [];

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
                'group' => 'web_lead',
                'title' => 'Web Lead API',
                'description' => 'Nhận lead từ form website vào module Khách hàng (Lead).',
                'fields' => [
                    ['state' => 'web_lead_enabled', 'key' => 'web_lead.enabled', 'label' => 'Bật API nhận lead từ web', 'type' => 'boolean', 'default' => config('services.web_lead.enabled', false), 'sort_order' => 460],
                    ['state' => 'web_lead_api_token', 'key' => 'web_lead.api_token', 'label' => 'API Token', 'type' => 'text', 'default' => config('services.web_lead.token', ''), 'is_secret' => true, 'sort_order' => 470],
                    ['state' => 'web_lead_default_branch_code', 'key' => 'web_lead.default_branch_code', 'label' => 'Chi nhánh mặc định khi web không gửi branch_code', 'type' => 'text', 'default' => '', 'sort_order' => 480],
                    ['state' => 'web_lead_rate_limit_per_minute', 'key' => 'web_lead.rate_limit_per_minute', 'label' => 'Giới hạn request/phút', 'type' => 'integer', 'default' => config('services.web_lead.rate_limit_per_minute', 60), 'sort_order' => 490],
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
                'group' => 'branding',
                'title' => 'Branding phòng khám',
                'description' => 'Cấu hình logo, thông tin phòng khám và màu nút dùng chung trên admin + biểu mẫu in.',
                'fields' => [
                    ['state' => 'branding_clinic_name', 'key' => 'branding.clinic_name', 'label' => 'Tên phòng khám', 'type' => 'text', 'default' => config('app.name', 'Dental CRM'), 'sort_order' => 600],
                    ['state' => 'branding_logo_url', 'key' => 'branding.logo_url', 'label' => 'Logo URL', 'type' => 'url', 'default' => asset('images/logo.svg'), 'sort_order' => 601],
                    ['state' => 'branding_address', 'key' => 'branding.address', 'label' => 'Địa chỉ phòng khám', 'type' => 'text', 'default' => '', 'sort_order' => 602],
                    ['state' => 'branding_phone', 'key' => 'branding.phone', 'label' => 'Hotline', 'type' => 'text', 'default' => '', 'sort_order' => 603],
                    ['state' => 'branding_email', 'key' => 'branding.email', 'label' => 'Email hiển thị', 'type' => 'email', 'default' => '', 'sort_order' => 604],
                    ['state' => 'branding_button_bg_color', 'key' => 'branding.button_bg_color', 'label' => 'Màu nền nút (hex)', 'type' => 'color', 'default' => '#2f66f6', 'sort_order' => 605],
                    ['state' => 'branding_button_bg_hover_color', 'key' => 'branding.button_bg_hover_color', 'label' => 'Màu nền nút hover (hex)', 'type' => 'color', 'default' => '#2456dc', 'sort_order' => 606],
                    ['state' => 'branding_button_text_color', 'key' => 'branding.button_text_color', 'label' => 'Màu chữ nút (hex)', 'type' => 'color', 'default' => '#ffffff', 'sort_order' => 607],
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
            [
                'group' => 'catalog',
                'title' => 'Danh mục động',
                'description' => 'Cấu hình danh mục tùy chọn dùng chung theo dạng Mã → Nhãn (UI thân thiện, không cần sửa JSON).',
                'fields' => [
                    [
                        'state' => 'catalog_exam_indications_json',
                        'key' => 'catalog.exam_indications',
                        'label' => 'Danh mục chỉ định khám',
                        'type' => 'json',
                        'default' => ClinicRuntimeSettings::defaultExamIndicationOptions(),
                        'sort_order' => 760,
                    ],
                    [
                        'state' => 'catalog_customer_sources_json',
                        'key' => 'catalog.customer_sources',
                        'label' => 'Danh mục nguồn khách hàng',
                        'type' => 'json',
                        'default' => ClinicRuntimeSettings::defaultCustomerSourceOptions(),
                        'sort_order' => 761,
                    ],
                    [
                        'state' => 'catalog_customer_statuses_json',
                        'key' => 'catalog.customer_statuses',
                        'label' => 'Danh mục trạng thái lead',
                        'type' => 'json',
                        'default' => ClinicRuntimeSettings::defaultCustomerStatusOptions(),
                        'sort_order' => 762,
                    ],
                    [
                        'state' => 'catalog_care_types_json',
                        'key' => 'catalog.care_types',
                        'label' => 'Danh mục loại chăm sóc',
                        'type' => 'json',
                        'default' => ClinicRuntimeSettings::defaultCareTypeOptions(),
                        'sort_order' => 763,
                    ],
                    [
                        'state' => 'catalog_payment_sources_json',
                        'key' => 'catalog.payment_sources',
                        'label' => 'Danh mục nguồn thanh toán',
                        'type' => 'json',
                        'default' => ClinicRuntimeSettings::defaultPaymentSourceLabels(),
                        'sort_order' => 764,
                    ],
                    [
                        'state' => 'catalog_payment_directions_json',
                        'key' => 'catalog.payment_directions',
                        'label' => 'Danh mục loại phiếu thanh toán',
                        'type' => 'json',
                        'default' => ClinicRuntimeSettings::defaultPaymentDirectionLabels(),
                        'sort_order' => 765,
                    ],
                    [
                        'state' => 'catalog_gender_options_json',
                        'key' => 'catalog.gender_options',
                        'label' => 'Danh mục giới tính',
                        'type' => 'json',
                        'default' => ClinicRuntimeSettings::defaultGenderOptions(),
                        'sort_order' => 766,
                    ],
                ],
            ],
        ];
    }

    public function save(): void
    {
        $catalogPayloads = $this->validateAndNormalizeCatalogEditors();

        foreach ($catalogPayloads as $state => $catalog) {
            $this->settings[$state] = json_encode($catalog, JSON_UNESCAPED_UNICODE) ?: '{}';
        }

        $rules = [];

        foreach ($this->getProviders() as $provider) {
            foreach ($provider['fields'] as $field) {
                $attribute = "settings.{$field['state']}";

                if (($field['key'] ?? null) === 'scheduler.automation_actor_user_id') {
                    $rules[$attribute] = ['nullable', 'integer', 'exists:users,id'];

                    continue;
                }

                if (($field['key'] ?? null) === 'web_lead.default_branch_code') {
                    $rules[$attribute] = [
                        'nullable',
                        'string',
                        'max:64',
                        Rule::exists('branches', 'code')
                            ->where(fn ($query) => $query->where('active', true)->whereNull('deleted_at')),
                    ];

                    continue;
                }

                $rules[$attribute] = match ($field['type']) {
                    'boolean' => ['boolean'],
                    'url' => ['nullable', 'url', 'max:500'],
                    'email' => ['nullable', 'email', 'max:255'],
                    'integer' => ['nullable', 'integer'],
                    'color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
                    'json' => ['nullable', 'json'],
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
                    'url', 'email', 'select', 'color' => 'text',
                    default => $field['type'],
                };
                $value = data_get($validated, $statePath, $field['default'] ?? null);

                if (($field['type'] ?? null) === 'json') {
                    $value = $this->decodeJsonFieldValue($value);
                }

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

    public function generateWebLeadApiToken(): void
    {
        $this->settings['web_lead_api_token'] = 'wla_'.Str::random(48);

        Notification::make()
            ->title('Đã tạo API token mới')
            ->body('Token mới đã được điền vào form. Nhấn "Lưu cài đặt tích hợp" để áp dụng.')
            ->success()
            ->send();
    }

    public function addCatalogRow(string $state): void
    {
        $field = $this->findFieldByState($state);

        if (($field['type'] ?? null) !== 'json') {
            return;
        }

        if (! isset($this->catalogEditors[$state]) || ! is_array($this->catalogEditors[$state])) {
            $this->catalogEditors[$state] = [];
        }

        $this->catalogEditors[$state][] = ['key' => '', 'label' => '', 'enabled' => true];
    }

    public function removeCatalogRow(string $state, int $index): void
    {
        $field = $this->findFieldByState($state);

        if (($field['type'] ?? null) !== 'json') {
            return;
        }

        if (! isset($this->catalogEditors[$state][$index])) {
            return;
        }

        unset($this->catalogEditors[$state][$index]);
        $this->catalogEditors[$state] = array_values($this->catalogEditors[$state]);

        if ($this->catalogEditors[$state] === []) {
            $this->catalogEditors[$state][] = ['key' => '', 'label' => '', 'enabled' => true];
        }
    }

    public function restoreCatalogDefaults(string $state): void
    {
        $field = $this->findFieldByState($state);

        if (($field['type'] ?? null) !== 'json') {
            return;
        }

        $this->catalogEditors[$state] = $this->catalogRowsFromValue($field['default'] ?? [], $state);
    }

    public function normalizeCatalogRowKey(string $state, int $index): void
    {
        $field = $this->findFieldByState($state);

        if (($field['type'] ?? null) !== 'json') {
            return;
        }

        if (! isset($this->catalogEditors[$state][$index])) {
            return;
        }

        $this->catalogEditors[$state][$index]['key'] = $this->normalizeCatalogKeyForState(
            $state,
            (string) ($this->catalogEditors[$state][$index]['key'] ?? ''),
        );
    }

    public function syncCatalogRowFromLabel(string $state, int $index): void
    {
        $field = $this->findFieldByState($state);

        if (($field['type'] ?? null) !== 'json') {
            return;
        }

        if (! isset($this->catalogEditors[$state][$index])) {
            return;
        }

        $rowKey = $this->normalizeCatalogKeyForState(
            $state,
            (string) ($this->catalogEditors[$state][$index]['key'] ?? ''),
        );

        if ($rowKey !== '') {
            return;
        }

        $label = trim((string) ($this->catalogEditors[$state][$index]['label'] ?? ''));

        if ($label === '') {
            return;
        }

        $this->catalogEditors[$state][$index]['key'] = $this->generateUniqueCatalogKeyFromLabel(
            $state,
            $label,
            $index,
        );
    }

    protected function loadSettingsState(): void
    {
        $state = [];
        $catalogEditors = [];

        foreach ($this->getProviders() as $provider) {
            foreach ($provider['fields'] as $field) {
                $value = ClinicSetting::getValue(
                    key: $field['key'],
                    default: $field['default'] ?? null,
                );

                if (($field['type'] ?? null) === 'json') {
                    $rows = $this->catalogRowsFromValue($value, (string) $field['state']);
                    $catalogEditors[$field['state']] = $rows;
                    $state[$field['state']] = json_encode(
                        $this->catalogRowsToMap($rows, (string) $field['state']),
                        JSON_UNESCAPED_UNICODE,
                    ) ?: '{}';

                    continue;
                }

                $state[$field['state']] = $this->formatFieldStateValue($field, $value);
            }
        }

        $this->settings = $state;
        $this->catalogEditors = $catalogEditors;
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

    protected function formatFieldStateValue(array $field, mixed $value): mixed
    {
        return $value;
    }

    protected function decodeJsonFieldValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    protected function findFieldByState(string $state): ?array
    {
        foreach ($this->getProviders() as $provider) {
            foreach ($provider['fields'] as $field) {
                if (($field['state'] ?? null) === $state) {
                    return $field;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected function validateAndNormalizeCatalogEditors(): array
    {
        $payloads = [];
        $errors = [];

        foreach ($this->getProviders() as $provider) {
            foreach ($provider['fields'] as $field) {
                if (($field['type'] ?? null) !== 'json') {
                    continue;
                }

                $state = (string) ($field['state'] ?? '');
                $rows = $this->catalogEditors[$state] ?? [];

                if (! is_array($rows)) {
                    $rows = [];
                }

                $catalog = [];

                foreach ($rows as $index => $row) {
                    $enabled = filter_var($row['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
                    $key = $this->normalizeCatalogKeyForState(
                        $state,
                        (string) ($row['key'] ?? ''),
                    );
                    $label = trim((string) ($row['label'] ?? ''));
                    $line = $index + 1;

                    if ($key === '' && $label !== '') {
                        $key = $this->generateUniqueCatalogKeyFromLabel($state, $label, $index);
                    }

                    $this->catalogEditors[$state][$index]['enabled'] = $enabled;

                    if ($key === '' && $label === '') {
                        continue;
                    }

                    if (! $enabled) {
                        $this->catalogEditors[$state][$index]['key'] = $key;
                        $this->catalogEditors[$state][$index]['label'] = $label;

                        continue;
                    }

                    if ($key === '') {
                        $errors["catalogEditors.{$state}.{$index}.key"] = "Dòng {$line}: không thể tự sinh mã từ nhãn hiển thị.";

                        continue;
                    }

                    if ($label === '') {
                        $errors["catalogEditors.{$state}.{$index}.label"] = "Dòng {$line}: vui lòng nhập nhãn.";

                        continue;
                    }

                    if (array_key_exists($key, $catalog)) {
                        $errors["catalogEditors.{$state}.{$index}.key"] = "Dòng {$line}: mã \"{$key}\" đang bị trùng.";

                        continue;
                    }

                    $catalog[$key] = $label;
                    $this->catalogEditors[$state][$index]['key'] = $key;
                    $this->catalogEditors[$state][$index]['label'] = $label;
                }

                if (($this->catalogEditors[$state] ?? []) === []) {
                    $this->catalogEditors[$state][] = ['key' => '', 'label' => '', 'enabled' => true];
                }

                $payloads[$state] = $catalog;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $payloads;
    }

    protected function normalizeCatalogKeyForState(string $state, string $value): string
    {
        $normalized = $this->normalizeCatalogKey($value);

        if ($state === self::EXAM_INDICATIONS_STATE) {
            $normalized = ClinicRuntimeSettings::normalizeExamIndicationKey($normalized);
        }

        return $normalized;
    }

    protected function normalizeCatalogKey(string $value): string
    {
        $normalized = Str::of(trim($value))
            ->ascii()
            ->lower()
            ->value();
        $normalized = preg_replace('/\s+/', '_', $normalized) ?? '';
        $normalized = preg_replace('/[^a-z0-9_-]/', '', $normalized) ?? '';

        return trim($normalized);
    }

    /**
     * @return array<int, array{key: string, label: string, enabled: bool}>
     */
    protected function catalogRowsFromValue(mixed $value, string $state = ''): array
    {
        $decoded = $this->decodeJsonFieldValue($value);
        $rows = [];

        foreach ($decoded as $key => $label) {
            $normalizedKey = $this->normalizeCatalogKeyForState($state, (string) $key);
            $normalizedLabel = trim((string) $label);

            if ($normalizedKey === '' || $normalizedLabel === '') {
                continue;
            }

            $rows[] = [
                'key' => $normalizedKey,
                'label' => $normalizedLabel,
                'enabled' => true,
            ];
        }

        if ($rows === []) {
            $rows[] = ['key' => '', 'label' => '', 'enabled' => true];
        }

        if ($state !== '') {
            $catalog = $this->catalogRowsToMap($rows, $state);
            $rows = collect($catalog)
                ->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label, 'enabled' => true])
                ->values()
                ->all();
        }

        return $rows;
    }

    /**
     * @param  array<int, array{key: string, label: string, enabled?: bool}>  $rows
     * @return array<string, string>
     */
    protected function catalogRowsToMap(array $rows, string $state = ''): array
    {
        $catalog = [];

        foreach ($rows as $row) {
            $enabled = filter_var($row['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);

            if (! $enabled) {
                continue;
            }

            $key = $this->normalizeCatalogKeyForState($state, (string) ($row['key'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));

            if ($key === '' || $label === '') {
                continue;
            }

            $catalog[$key] = $label;
        }

        return $catalog;
    }

    protected function generateUniqueCatalogKeyFromLabel(string $state, string $label, int $exceptIndex = -1): string
    {
        $baseKey = $this->normalizeCatalogKeyForState($state, $label);

        if ($baseKey === '') {
            return '';
        }

        $existingKeys = collect($this->catalogEditors[$state] ?? [])
            ->map(function (array $row, int $rowIndex) use ($state, $exceptIndex): string {
                if ($rowIndex === $exceptIndex) {
                    return '';
                }

                return $this->normalizeCatalogKeyForState($state, (string) ($row['key'] ?? ''));
            })
            ->filter()
            ->values()
            ->all();

        if (! in_array($baseKey, $existingKeys, true)) {
            return $baseKey;
        }

        $suffix = 2;

        while (in_array($baseKey.'_'.$suffix, $existingKeys, true)) {
            $suffix++;
        }

        return $baseKey.'_'.$suffix;
    }
}
