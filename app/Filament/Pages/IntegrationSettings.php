<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\ClinicSettingLog;
use App\Models\User;
use App\Services\IntegrationOperationalReadModelService;
use App\Services\IntegrationProviderActionService;
use App\Services\IntegrationProviderHealthReadModelService;
use App\Services\IntegrationSecretRotationService;
use App\Services\IntegrationSettingsAuditReadModelService;
use App\Support\ClinicRuntimeSettings;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Spatie\Permission\Models\Role;
use UnitEnum;

class IntegrationSettings extends Page
{
    use HasPageShield;

    public const AUDIT_LOG_PERMISSION = 'View:IntegrationSettingsAuditLog';

    public const MANAGE_RUNTIME_PERMISSION = 'Manage:IntegrationRuntimeSettings';

    public const MANAGE_SECRETS_PERMISSION = 'Manage:IntegrationSecrets';

    private const EXAM_INDICATIONS_STATE = 'catalog_exam_indications_json';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = 'Cài đặt tích hợp';

    protected static string|UnitEnum|null $navigationGroup = 'Cài đặt hệ thống';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'integration-settings';

    protected string $view = 'filament.pages.integration-settings';

    public array $settings = [];

    #[Locked]
    public string $settingsRevision = '';

    /**
     * @var array<string, array<int, array{key: string, label: string, enabled: bool}>>
     */
    public array $catalogEditors = [];

    public static function canAccess(): bool
    {
        $authUser = auth()->user();

        return $authUser instanceof User
            && $authUser->hasRole('Admin')
            && $authUser->can('View:IntegrationSettings');
    }

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
        return 'Quản lý cấu hình kết nối Zalo, Facebook, ZNS, Google Calendar, EMR và runtime CSKH.';
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
                    ['state' => 'zalo_access_token', 'key' => 'zalo.access_token', 'label' => 'Access Token', 'type' => 'text', 'default' => '', 'is_secret' => true, 'sort_order' => 45],
                    ['state' => 'zalo_webhook_token', 'key' => 'zalo.webhook_token', 'label' => 'Webhook Verify Token', 'type' => 'text', 'default' => '', 'is_secret' => true, 'sort_order' => 50],
                    ['state' => 'zalo_webhook_token_grace_minutes', 'key' => 'zalo.webhook_token_grace_minutes', 'label' => 'Grace window webhook token cũ (phút)', 'type' => 'integer', 'default' => ClinicRuntimeSettings::zaloWebhookTokenGraceMinutes(), 'sort_order' => 51],
                    ['state' => 'zalo_webhook_rate_limit_per_minute', 'key' => 'zalo.webhook_rate_limit_per_minute', 'label' => 'Giới hạn webhook/phút', 'type' => 'integer', 'default' => 120, 'sort_order' => 55],
                    ['state' => 'zalo_webhook_retention_days', 'key' => 'zalo.webhook_retention_days', 'label' => 'Giữ webhook Zalo (ngày)', 'type' => 'integer', 'default' => ClinicRuntimeSettings::zaloWebhookRetentionDays(), 'sort_order' => 56],
                    ['state' => 'zalo_send_endpoint', 'key' => 'zalo.send_endpoint', 'label' => 'Zalo OA send endpoint', 'type' => 'url', 'default' => ClinicRuntimeSettings::zaloSendEndpoint(), 'sort_order' => 57],
                    ['state' => 'zalo_inbox_default_branch_code', 'key' => 'zalo.inbox_default_branch_code', 'label' => 'Chi nhánh mặc định cho inbox Zalo', 'type' => 'text', 'default' => ClinicRuntimeSettings::zaloInboxDefaultBranchCode(), 'sort_order' => 58],
                    ['state' => 'zalo_inbox_polling_seconds', 'key' => 'zalo.inbox_polling_seconds', 'label' => 'Chu kỳ polling inbox (giây)', 'type' => 'integer', 'default' => ClinicRuntimeSettings::zaloInboxPollingSeconds(), 'sort_order' => 59],
                ],
            ],
            [
                'group' => 'facebook',
                'title' => 'Facebook Messenger',
                'description' => 'Thiết lập Page inbox, webhook và token gửi phản hồi cho Messenger.',
                'fields' => [
                    ['state' => 'facebook_enabled', 'key' => 'facebook.enabled', 'label' => 'Bật tích hợp Facebook Messenger', 'type' => 'boolean', 'default' => false, 'sort_order' => 60],
                    ['state' => 'facebook_page_id', 'key' => 'facebook.page_id', 'label' => 'Page ID', 'type' => 'text', 'default' => ClinicRuntimeSettings::facebookPageId(), 'sort_order' => 61],
                    ['state' => 'facebook_app_id', 'key' => 'facebook.app_id', 'label' => 'App ID', 'type' => 'text', 'default' => ClinicRuntimeSettings::facebookAppId(), 'sort_order' => 62],
                    ['state' => 'facebook_app_secret', 'key' => 'facebook.app_secret', 'label' => 'App Secret', 'type' => 'text', 'default' => ClinicRuntimeSettings::facebookAppSecret(), 'is_secret' => true, 'sort_order' => 63],
                    ['state' => 'facebook_webhook_verify_token', 'key' => 'facebook.webhook_verify_token', 'label' => 'Webhook Verify Token', 'type' => 'text', 'default' => ClinicRuntimeSettings::facebookWebhookVerifyToken(), 'is_secret' => true, 'sort_order' => 64],
                    ['state' => 'facebook_page_access_token', 'key' => 'facebook.page_access_token', 'label' => 'Page Access Token', 'type' => 'text', 'default' => ClinicRuntimeSettings::facebookPageAccessToken(), 'is_secret' => true, 'sort_order' => 65],
                    ['state' => 'facebook_send_endpoint', 'key' => 'facebook.send_endpoint', 'label' => 'Messenger send endpoint', 'type' => 'url', 'default' => ClinicRuntimeSettings::facebookSendEndpoint(), 'sort_order' => 66],
                    ['state' => 'facebook_inbox_default_branch_code', 'key' => 'facebook.inbox_default_branch_code', 'label' => 'Chi nhánh mặc định cho inbox Facebook', 'type' => 'text', 'default' => ClinicRuntimeSettings::facebookInboxDefaultBranchCode(), 'sort_order' => 67],
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
                    ['state' => 'zns_auto_send_lead_welcome', 'key' => 'zns.auto_send_lead_welcome', 'label' => 'Tự gửi tin chào mừng khi có lead mới từ web', 'type' => 'boolean', 'default' => false, 'sort_order' => 135],
                    ['state' => 'zns_template_lead_welcome', 'key' => 'zns.template_lead_welcome', 'label' => 'Template chào mừng lead mới', 'type' => 'text', 'default' => '', 'sort_order' => 136],
                    ['state' => 'zns_auto_send_appointment_reminder', 'key' => 'zns.auto_send_appointment_reminder', 'label' => 'Tự gửi ZNS nhắc lịch hẹn', 'type' => 'boolean', 'default' => false, 'sort_order' => 137],
                    ['state' => 'zns_appointment_reminder_default_hours', 'key' => 'zns.appointment_reminder_default_hours', 'label' => 'Số giờ nhắc hẹn mặc định trước giờ hẹn', 'type' => 'integer', 'default' => 24, 'sort_order' => 138],
                    ['state' => 'zns_template_appointment', 'key' => 'zns.template_appointment', 'label' => 'Template nhắc lịch hẹn', 'type' => 'text', 'default' => '', 'sort_order' => 140],
                    ['state' => 'zns_template_payment', 'key' => 'zns.template_payment', 'label' => 'Template nhắc thanh toán', 'type' => 'text', 'default' => '', 'sort_order' => 150],
                    ['state' => 'zns_auto_send_birthday', 'key' => 'zns.auto_send_birthday', 'label' => 'Tự gửi ZNS chúc mừng sinh nhật', 'type' => 'boolean', 'default' => false, 'sort_order' => 151],
                    ['state' => 'zns_template_birthday', 'key' => 'zns.template_birthday', 'label' => 'Template chúc mừng sinh nhật', 'type' => 'text', 'default' => '', 'sort_order' => 152],
                    ['state' => 'zns_send_endpoint', 'key' => 'zns.send_endpoint', 'label' => 'ZNS send endpoint', 'type' => 'url', 'default' => ClinicRuntimeSettings::znsSendEndpoint(), 'sort_order' => 160],
                    ['state' => 'zns_request_timeout_seconds', 'key' => 'zns.request_timeout_seconds', 'label' => 'Timeout gọi ZNS (giây)', 'type' => 'integer', 'default' => ClinicRuntimeSettings::znsRequestTimeoutSeconds(), 'sort_order' => 170],
                    ['state' => 'zns_campaign_delivery_max_attempts', 'key' => 'zns.campaign_delivery_max_attempts', 'label' => 'Số lần retry tối đa cho mỗi người nhận campaign', 'type' => 'integer', 'default' => ClinicRuntimeSettings::znsCampaignDeliveryMaxAttempts(), 'sort_order' => 171],
                    ['state' => 'zns_retention_days', 'key' => 'zns.retention_days', 'label' => 'Giữ dữ liệu vận hành ZNS (ngày)', 'type' => 'integer', 'default' => ClinicRuntimeSettings::znsOperationalRetentionDays(), 'sort_order' => 172],
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
                    ['state' => 'google_calendar_refresh_token', 'key' => 'google_calendar.refresh_token', 'label' => 'Refresh Token', 'type' => 'text', 'default' => '', 'is_secret' => true, 'sort_order' => 235],
                    ['state' => 'google_calendar_account_email', 'key' => 'google_calendar.account_email', 'label' => 'Google Account Email', 'type' => 'email', 'default' => '', 'sort_order' => 238],
                    ['state' => 'google_calendar_calendar_id', 'key' => 'google_calendar.calendar_id', 'label' => 'Calendar ID', 'type' => 'text', 'default' => '', 'sort_order' => 240],
                    [
                        'state' => 'google_calendar_sync_mode',
                        'key' => 'google_calendar.sync_mode',
                        'label' => 'Chế độ đồng bộ',
                        'type' => 'select',
                        'default' => 'one_way_to_google',
                        'options' => ClinicRuntimeSettings::googleCalendarSyncModeOptions(),
                        'sort_order' => 250,
                    ],
                    ['state' => 'google_calendar_retention_days', 'key' => 'google_calendar.retention_days', 'label' => 'Giữ dữ liệu vận hành Google Calendar (ngày)', 'type' => 'integer', 'default' => ClinicRuntimeSettings::googleCalendarOperationalRetentionDays(), 'sort_order' => 260],
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
                    ['state' => 'emr_api_key_grace_minutes', 'key' => 'emr.api_key_grace_minutes', 'label' => 'Grace window API key cũ (phút)', 'type' => 'integer', 'default' => ClinicRuntimeSettings::emrApiKeyGraceMinutes(), 'sort_order' => 441],
                    ['state' => 'emr_clinic_code', 'key' => 'emr.clinic_code', 'label' => 'Mã cơ sở', 'type' => 'text', 'default' => '', 'sort_order' => 450],
                    [
                        'state' => 'emr_media_storage_disk',
                        'key' => 'emr.media.storage_disk',
                        'label' => 'Disk lưu hồ ảnh lâm sàng',
                        'type' => 'select',
                        'default' => config('care.emr_media_storage_disk', 'local'),
                        'options' => $this->mediaDiskOptions(),
                        'sort_order' => 451,
                    ],
                    ['state' => 'emr_media_signed_url_ttl_minutes', 'key' => 'emr.media.signed_url_ttl_minutes', 'label' => 'TTL signed URL hồ ảnh (phút)', 'type' => 'integer', 'default' => config('care.emr_media_signed_url_ttl_minutes', 5), 'sort_order' => 452],
                    ['state' => 'emr_media_retention_enabled', 'key' => 'emr.media.retention_enabled', 'label' => 'Bật retention class-aware cho hồ ảnh lâm sàng', 'type' => 'boolean', 'default' => config('care.emr_media_retention_enabled', true), 'sort_order' => 453],
                    ['state' => 'emr_media_retention_days_clinical_operational', 'key' => 'emr.media.retention_days_clinical_operational', 'label' => 'Retention class clinical_operational (ngày)', 'type' => 'integer', 'default' => data_get(config('care.emr_media_retention_days', []), 'clinical_operational', 365), 'sort_order' => 454],
                    ['state' => 'emr_media_retention_days_temporary', 'key' => 'emr.media.retention_days_temporary', 'label' => 'Retention class temporary (ngày)', 'type' => 'integer', 'default' => data_get(config('care.emr_media_retention_days', []), 'temporary', 30), 'sort_order' => 455],
                    ['state' => 'emr_media_retention_days_clinical_legal', 'key' => 'emr.media.retention_days_clinical_legal', 'label' => 'Retention class clinical_legal (ngày, 0 = giữ vô hạn)', 'type' => 'integer', 'default' => data_get(config('care.emr_media_retention_days', []), 'clinical_legal', 0), 'sort_order' => 456],
                    ['state' => 'emr_dicom_enabled', 'key' => 'emr.dicom.enabled', 'label' => 'Bật readiness DICOM/PACS (optional)', 'type' => 'boolean', 'default' => config('care.emr_dicom_enabled', false), 'sort_order' => 457],
                    ['state' => 'emr_dicom_base_url', 'key' => 'emr.dicom.base_url', 'label' => 'DICOM base URL', 'type' => 'url', 'default' => config('care.emr_dicom_base_url', ''), 'sort_order' => 458],
                    ['state' => 'emr_dicom_facility_code', 'key' => 'emr.dicom.facility_code', 'label' => 'DICOM facility code', 'type' => 'text', 'default' => config('care.emr_dicom_facility_code', ''), 'sort_order' => 459],
                    ['state' => 'emr_dicom_timeout_seconds', 'key' => 'emr.dicom.timeout_seconds', 'label' => 'DICOM timeout (giây)', 'type' => 'integer', 'default' => config('care.emr_dicom_timeout_seconds', 10), 'sort_order' => 460],
                    ['state' => 'emr_dicom_auth_token', 'key' => 'emr.dicom.auth_token', 'label' => 'DICOM auth token', 'type' => 'text', 'default' => config('care.emr_dicom_auth_token', ''), 'is_secret' => true, 'sort_order' => 461],
                    ['state' => 'emr_retention_days', 'key' => 'emr.retention_days', 'label' => 'Giữ dữ liệu vận hành EMR (ngày)', 'type' => 'integer', 'default' => ClinicRuntimeSettings::emrOperationalRetentionDays(), 'sort_order' => 462],
                ],
            ],
            [
                'group' => 'web_lead',
                'title' => 'Web Lead API',
                'description' => 'Nhận lead từ form website vào module Khách hàng (Lead).',
                'fields' => [
                    ['state' => 'web_lead_enabled', 'key' => 'web_lead.enabled', 'label' => 'Bật API nhận lead từ web', 'type' => 'boolean', 'default' => config('services.web_lead.enabled', false), 'sort_order' => 460],
                    ['state' => 'web_lead_api_token', 'key' => 'web_lead.api_token', 'label' => 'API Token', 'type' => 'text', 'default' => config('services.web_lead.token', ''), 'is_secret' => true, 'sort_order' => 470],
                    ['state' => 'web_lead_api_token_grace_minutes', 'key' => 'web_lead.api_token_grace_minutes', 'label' => 'Grace window API token cũ (phút)', 'type' => 'integer', 'default' => ClinicRuntimeSettings::webLeadApiTokenGraceMinutes(), 'sort_order' => 471],
                    ['state' => 'web_lead_default_branch_code', 'key' => 'web_lead.default_branch_code', 'label' => 'Chi nhánh mặc định khi web không gửi branch_code', 'type' => 'text', 'default' => '', 'sort_order' => 480],
                    ['state' => 'web_lead_rate_limit_per_minute', 'key' => 'web_lead.rate_limit_per_minute', 'label' => 'Giới hạn request/phút', 'type' => 'integer', 'default' => config('services.web_lead.rate_limit_per_minute', 60), 'sort_order' => 490],
                    ['state' => 'web_lead_realtime_notification_enabled', 'key' => 'web_lead.realtime_notification_enabled', 'label' => 'Bật thông báo realtime khi có web lead mới', 'type' => 'boolean', 'default' => false, 'sort_order' => 492],
                    ['state' => 'web_lead_realtime_notification_roles', 'key' => 'web_lead.realtime_notification_roles', 'label' => 'Nhóm quyền nhận thông báo realtime', 'type' => 'roles', 'default' => ['CSKH'], 'options' => $this->roleOptions(), 'sort_order' => 493],
                    ['state' => 'web_lead_internal_email_enabled', 'key' => 'web_lead.internal_email_enabled', 'label' => 'Bật email nội bộ khi có web lead mới', 'type' => 'boolean', 'default' => false, 'sort_order' => 494],
                    ['state' => 'web_lead_internal_email_recipient_roles', 'key' => 'web_lead.internal_email_recipient_roles', 'label' => 'Nhóm quyền nhận email nội bộ', 'type' => 'roles', 'default' => ['CSKH'], 'options' => $this->roleOptions(), 'sort_order' => 495],
                    ['state' => 'web_lead_internal_email_recipient_emails', 'key' => 'web_lead.internal_email_recipient_emails', 'label' => 'Mailbox nhận nội bộ (mỗi dòng một email)', 'type' => 'textarea', 'default' => '', 'sort_order' => 496],
                    ['state' => 'web_lead_internal_email_subject_prefix', 'key' => 'web_lead.internal_email_subject_prefix', 'label' => 'Prefix subject email nội bộ', 'type' => 'text', 'default' => '[CRM Lead]', 'sort_order' => 497],
                    ['state' => 'web_lead_internal_email_queue', 'key' => 'web_lead.internal_email_queue', 'label' => 'Tên queue email nội bộ', 'type' => 'text', 'default' => 'web-lead-mail', 'sort_order' => 498],
                    ['state' => 'web_lead_internal_email_max_attempts', 'key' => 'web_lead.internal_email_max_attempts', 'label' => 'Số lần retry tối đa', 'type' => 'integer', 'default' => 5, 'sort_order' => 499],
                    ['state' => 'web_lead_internal_email_retry_delay_minutes', 'key' => 'web_lead.internal_email_retry_delay_minutes', 'label' => 'Khoảng cách retry (phút)', 'type' => 'integer', 'default' => 10, 'sort_order' => 500],
                    ['state' => 'web_lead_internal_email_smtp_host', 'key' => 'web_lead.internal_email_smtp_host', 'label' => 'SMTP host', 'type' => 'text', 'default' => '', 'sort_order' => 501],
                    ['state' => 'web_lead_internal_email_smtp_port', 'key' => 'web_lead.internal_email_smtp_port', 'label' => 'SMTP port', 'type' => 'integer', 'default' => 587, 'sort_order' => 502],
                    ['state' => 'web_lead_internal_email_smtp_username', 'key' => 'web_lead.internal_email_smtp_username', 'label' => 'SMTP username', 'type' => 'text', 'default' => '', 'sort_order' => 503],
                    ['state' => 'web_lead_internal_email_smtp_password', 'key' => 'web_lead.internal_email_smtp_password', 'label' => 'SMTP password', 'type' => 'text', 'default' => '', 'is_secret' => true, 'sort_order' => 504],
                    [
                        'state' => 'web_lead_internal_email_smtp_scheme',
                        'key' => 'web_lead.internal_email_smtp_scheme',
                        'label' => 'SMTP scheme',
                        'type' => 'select',
                        'default' => 'tls',
                        'options' => [
                            'tls' => 'TLS',
                            'ssl' => 'SSL',
                            'none' => 'Không mã hóa',
                        ],
                        'sort_order' => 505,
                    ],
                    ['state' => 'web_lead_internal_email_smtp_timeout_seconds', 'key' => 'web_lead.internal_email_smtp_timeout_seconds', 'label' => 'SMTP timeout (giây)', 'type' => 'integer', 'default' => 10, 'sort_order' => 506],
                    ['state' => 'web_lead_internal_email_from_address', 'key' => 'web_lead.internal_email_from_address', 'label' => 'From address', 'type' => 'email', 'default' => '', 'sort_order' => 507],
                    ['state' => 'web_lead_internal_email_from_name', 'key' => 'web_lead.internal_email_from_name', 'label' => 'From name', 'type' => 'text', 'default' => config('app.name', 'Dental CRM'), 'sort_order' => 508],
                    ['state' => 'web_lead_retention_days', 'key' => 'web_lead.retention_days', 'label' => 'Giữ log web lead ingestion (ngày)', 'type' => 'integer', 'default' => ClinicRuntimeSettings::webLeadOperationalRetentionDays(), 'sort_order' => 498],
                ],
            ],
            [
                'group' => 'popup',
                'title' => 'Popup thông báo nội bộ',
                'description' => 'Thông báo realtime theo chi nhánh + nhóm quyền. Polling 10s, hiển thị một lần cho mỗi user.',
                'fields' => [
                    ['state' => 'popup_enabled', 'key' => 'popup.enabled', 'label' => 'Bật popup thông báo nội bộ', 'type' => 'boolean', 'default' => false, 'sort_order' => 494],
                    ['state' => 'popup_polling_seconds', 'key' => 'popup.polling_seconds', 'label' => 'Chu kỳ polling (giây)', 'type' => 'integer', 'default' => 10, 'sort_order' => 495],
                    ['state' => 'popup_retention_days', 'key' => 'popup.retention_days', 'label' => 'Giữ log popup (ngày)', 'type' => 'integer', 'default' => 180, 'sort_order' => 496],
                    ['state' => 'popup_sender_roles', 'key' => 'popup.sender_roles', 'label' => 'Nhóm quyền được gửi popup toàn hệ thống', 'type' => 'roles', 'default' => ['Admin', 'Manager'], 'options' => $this->roleOptions(), 'sort_order' => 497],
                ],
            ],
            [
                'group' => 'photos',
                'title' => 'Thư viện ảnh lâm sàng',
                'description' => 'Retention policy cho ảnh bệnh nhân theo vòng đời dữ liệu lâm sàng.',
                'fields' => [
                    ['state' => 'photos_retention_enabled', 'key' => 'photos.retention_enabled', 'label' => 'Bật tự động dọn ảnh quá hạn', 'type' => 'boolean', 'default' => false, 'sort_order' => 500],
                    ['state' => 'photos_retention_days', 'key' => 'photos.retention_days', 'label' => 'Giữ ảnh trong bao nhiêu ngày (0 = không dọn)', 'type' => 'integer', 'default' => 0, 'sort_order' => 501],
                    ['state' => 'photos_retention_include_xray', 'key' => 'photos.retention_include_xray', 'label' => 'Áp retention cho cả ảnh X-quang', 'type' => 'boolean', 'default' => false, 'sort_order' => 502],
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
                'group' => 'security',
                'title' => 'Runtime bảo mật',
                'description' => 'Cấu hình MFA, session timeout và lockout khi đăng nhập sai nhiều lần.',
                'fields' => [
                    ['state' => 'security_mfa_required_roles', 'key' => 'security.mfa_required_roles', 'label' => 'Role bắt buộc MFA', 'type' => 'roles', 'default' => config('care.security_mfa_required_roles', ['Admin', 'Manager']), 'options' => $this->roleOptions(), 'sort_order' => 591],
                    ['state' => 'security_session_idle_timeout_minutes', 'key' => 'security.session_idle_timeout_minutes', 'label' => 'Session idle timeout (phút)', 'type' => 'integer', 'default' => 30, 'sort_order' => 592],
                    ['state' => 'security_login_max_attempts', 'key' => 'security.login_max_attempts', 'label' => 'Số lần đăng nhập sai tối đa trước khi khóa', 'type' => 'integer', 'default' => 5, 'sort_order' => 593],
                    ['state' => 'security_login_lockout_minutes', 'key' => 'security.login_lockout_minutes', 'label' => 'Thời gian khóa đăng nhập (phút)', 'type' => 'integer', 'default' => 15, 'sort_order' => 594],
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
        $this->authorizeManageRuntimeSettings();
        $this->resetValidation('settingsRevision');

        $catalogPayloads = $this->validateAndNormalizeCatalogEditors();
        $rotationNotifications = [];
        $changedFieldLabels = [];
        $sensitiveFieldLabels = [];

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

                if (($field['key'] ?? null) === 'popup.polling_seconds') {
                    $rules[$attribute] = ['nullable', 'integer', 'min:5', 'max:60'];

                    continue;
                }

                if (($field['key'] ?? null) === 'popup.retention_days') {
                    $rules[$attribute] = ['nullable', 'integer', 'min:1', 'max:3650'];

                    continue;
                }

                if (($field['key'] ?? null) === 'zalo.webhook_rate_limit_per_minute') {
                    $rules[$attribute] = ['nullable', 'integer', 'min:10', 'max:2000'];

                    continue;
                }

                if (($field['key'] ?? null) === 'zalo.webhook_token_grace_minutes') {
                    $rules[$attribute] = ['nullable', 'integer', 'min:5', 'max:10080'];

                    continue;
                }

                if (($field['key'] ?? null) === 'zalo.webhook_retention_days') {
                    $rules[$attribute] = ['nullable', 'integer', 'min:1', 'max:3650'];

                    continue;
                }

                if (($field['key'] ?? null) === 'zns.request_timeout_seconds') {
                    $rules[$attribute] = ['nullable', 'integer', 'min:3', 'max:30'];

                    continue;
                }

                if (($field['key'] ?? null) === 'zns.campaign_delivery_max_attempts') {
                    $rules[$attribute] = ['nullable', 'integer', 'min:1', 'max:10'];

                    continue;
                }

                if (($field['key'] ?? null) === 'zns.retention_days') {
                    $rules[$attribute] = ['nullable', 'integer', 'min:1', 'max:3650'];

                    continue;
                }

                if (($field['key'] ?? null) === 'google_calendar.retention_days') {
                    $rules[$attribute] = ['nullable', 'integer', 'min:1', 'max:3650'];

                    continue;
                }

                if (($field['key'] ?? null) === 'zns.appointment_reminder_default_hours') {
                    $rules[$attribute] = ['nullable', 'integer', 'min:1', 'max:168'];

                    continue;
                }

                if (($field['key'] ?? null) === 'emr.media.signed_url_ttl_minutes') {
                    $rules[$attribute] = ['nullable', 'integer', 'min:1', 'max:120'];

                    continue;
                }

                if (in_array(($field['key'] ?? null), [
                    'emr.media.retention_days_clinical_operational',
                    'emr.media.retention_days_temporary',
                    'emr.media.retention_days_clinical_legal',
                ], true)) {
                    $rules[$attribute] = ['nullable', 'integer', 'min:0', 'max:36500'];

                    continue;
                }

                if (($field['key'] ?? null) === 'emr.dicom.timeout_seconds') {
                    $rules[$attribute] = ['nullable', 'integer', 'min:3', 'max:120'];

                    continue;
                }

                if (($field['key'] ?? null) === 'emr.retention_days') {
                    $rules[$attribute] = ['nullable', 'integer', 'min:1', 'max:3650'];

                    continue;
                }

                if (($field['key'] ?? null) === 'emr.api_key_grace_minutes') {
                    $rules[$attribute] = ['nullable', 'integer', 'min:5', 'max:10080'];

                    continue;
                }

                if (($field['key'] ?? null) === 'web_lead.retention_days') {
                    $rules[$attribute] = ['nullable', 'integer', 'min:1', 'max:3650'];

                    continue;
                }

                if (($field['key'] ?? null) === 'web_lead.api_token_grace_minutes') {
                    $rules[$attribute] = ['nullable', 'integer', 'min:5', 'max:10080'];

                    continue;
                }

                if (($field['key'] ?? null) === 'web_lead.internal_email_max_attempts') {
                    $rules[$attribute] = ['nullable', 'integer', 'min:1', 'max:10'];

                    continue;
                }

                if (($field['key'] ?? null) === 'web_lead.internal_email_retry_delay_minutes') {
                    $rules[$attribute] = ['nullable', 'integer', 'min:1', 'max:240'];

                    continue;
                }

                if (($field['key'] ?? null) === 'web_lead.internal_email_smtp_port') {
                    $rules[$attribute] = ['nullable', 'integer', 'min:1', 'max:65535'];

                    continue;
                }

                if (($field['key'] ?? null) === 'web_lead.internal_email_smtp_timeout_seconds') {
                    $rules[$attribute] = ['nullable', 'integer', 'min:3', 'max:120'];

                    continue;
                }

                if (($field['key'] ?? null) === 'web_lead.internal_email_recipient_emails') {
                    $rules[$attribute] = [
                        'nullable',
                        'string',
                        'max:4000',
                        function (string $attribute, mixed $value, \Closure $fail): void {
                            $emails = preg_split('/[\r\n,;]+/', (string) $value) ?: [];

                            foreach ($emails as $email) {
                                $trimmed = trim((string) $email);

                                if ($trimmed === '') {
                                    continue;
                                }

                                if (filter_var($trimmed, FILTER_VALIDATE_EMAIL) === false) {
                                    $fail('Danh sách mailbox nội bộ chứa email không hợp lệ: '.$trimmed);

                                    return;
                                }
                            }
                        },
                    ];

                    continue;
                }

                if (($field['type'] ?? null) === 'roles') {
                    $rules[$attribute] = ['array'];
                    $rules["{$attribute}.*"] = ['string', Rule::exists('roles', 'name')];

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
                    'textarea' => ['nullable', 'string', 'max:4000'],
                    default => ['nullable', 'string', 'max:3000'],
                };
            }
        }

        $validated = $this->validate($rules);
        $this->validateIntegrationProviderDependencies($validated);

        try {
            Cache::lock('integration-settings:save', 15)->block(5, function () use ($validated, &$rotationNotifications, &$changedFieldLabels, &$sensitiveFieldLabels): void {
                DB::transaction(function () use ($validated, &$rotationNotifications, &$changedFieldLabels, &$sensitiveFieldLabels): void {
                    $fieldDefinitions = $this->settingFieldDefinitions();
                    $currentRevision = $this->currentSettingsRevision();
                    $rotationService = app(IntegrationSecretRotationService::class);

                    if (! hash_equals($currentRevision, $this->settingsRevision)) {
                        throw ValidationException::withMessages([
                            'settingsRevision' => 'Cài đặt tích hợp đã được cập nhật bởi phiên khác. Tải lại trang rồi lưu lại để tránh ghi đè dữ liệu mới hơn.',
                        ]);
                    }

                    $currentValues = ClinicSetting::resolveValuesForDefinitions(
                        collect($fieldDefinitions)
                            ->map(static fn (array $field): array => [
                                'key' => $field['key'],
                                'default' => $field['default'] ?? null,
                            ])
                            ->all(),
                    );

                    foreach ($fieldDefinitions as $field) {
                        $statePath = "settings.{$field['state']}";
                        $valueType = $this->valueTypeForField($field);
                        $value = data_get($validated, $statePath, $field['default'] ?? null);

                        if (($field['type'] ?? null) === 'json') {
                            $value = $this->decodeJsonFieldValue($value);
                        }

                        if (($field['type'] ?? null) === 'roles') {
                            $value = collect(is_array($value) ? $value : [])
                                ->filter(static fn (mixed $item): bool => is_string($item) && trim($item) !== '')
                                ->map(static fn (string $item): string => trim($item))
                                ->unique()
                                ->values()
                                ->all();
                        }

                        $normalizedNewValue = $this->normalizeValueForCompare($value, $valueType);
                        $oldValue = $currentValues[$field['key']] ?? ($field['default'] ?? null);
                        $normalizedOldValue = $this->normalizeValueForCompare($oldValue, $valueType);

                        if (($field['is_secret'] ?? false) && $normalizedOldValue !== $normalizedNewValue) {
                            $this->authorizeManageSecrets();
                        }

                        if (
                            $rotationService->isRotatable((string) $field['key'])
                            && $normalizedOldValue !== $normalizedNewValue
                        ) {
                            $rotationNotifications[] = $rotationService->rotate(
                                settingKey: (string) $field['key'],
                                newSecret: filled($value) ? (string) $value : '',
                                actorId: auth()->id(),
                                reason: 'Secret rotated from IntegrationSettings.',
                            );

                            continue;
                        }

                        $record = ClinicSetting::setValue(
                            key: $field['key'],
                            value: $value,
                            meta: [
                                'group' => $field['group'],
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

                        $changedFieldLabels[] = (string) ($field['label'] ?? $field['key']);

                        if (($field['is_secret'] ?? false) || $this->isOperationallySensitiveField((string) ($field['key'] ?? ''))) {
                            $sensitiveFieldLabels[] = (string) ($field['label'] ?? $field['key']);
                        }

                        $this->logSettingChange(
                            setting: $record,
                            oldValue: $normalizedOldValue,
                            newValue: $normalizedNewValue,
                            isSecret: (bool) ($field['is_secret'] ?? false),
                        );
                    }
                }, attempts: 5);
            });
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'settingsRevision' => 'Một phiên khác đang cập nhật cài đặt tích hợp. Chờ vài giây rồi lưu lại.',
            ]);
        }

        $this->loadSettingsState();

        $notification = Notification::make()
            ->title($changedFieldLabels === []
                ? 'Không có thay đổi để lưu'
                : 'Đã lưu cài đặt tích hợp');

        $rotationLines = collect($rotationNotifications)
            ->filter(static fn (array $summary): bool => (bool) ($summary['rotated'] ?? false))
            ->map(fn (array $summary): string => $this->formatRotationNotificationLine($summary))
            ->filter()
            ->values()
            ->all();

        if ($rotationLines !== []) {
            $notification
                ->warning()
                ->body(implode("\n", array_merge(
                    $rotationLines,
                    $changedFieldLabels === [] ? [] : ['Đã cập nhật: '.implode(', ', array_slice(array_values(array_unique($changedFieldLabels)), 0, 6))],
                )))
                ->send();

            return;
        }

        if ($changedFieldLabels !== []) {
            $bodyLines = [
                'Đã cập nhật: '.implode(', ', array_slice(array_values(array_unique($changedFieldLabels)), 0, 6)),
            ];

            if ($sensitiveFieldLabels !== []) {
                $bodyLines[] = 'Cấu hình nhạy cảm vừa đổi: '.implode(', ', array_slice(array_values(array_unique($sensitiveFieldLabels)), 0, 4)).'.';
            }

            $notification->body(implode("\n", $bodyLines));
        }

        ($changedFieldLabels === [] ? $notification->warning() : $notification->success())
            ->send();
    }

    public function testEmrConnection(): void
    {
        $this->authorizeManageSecrets();

        $this->sendNotificationPayload(
            app(IntegrationProviderActionService::class)->emrConnectionNotification(),
        );
    }

    public function testZaloReadiness(): void
    {
        $this->sendProviderReadinessNotification('zalo_oa');
    }

    public function testZnsReadiness(): void
    {
        $this->sendProviderReadinessNotification('zns');
    }

    public function testDicomReadiness(): void
    {
        $this->sendProviderReadinessNotification('dicom');
    }

    public function testWebLeadReadiness(): void
    {
        $this->sendProviderReadinessNotification('web_lead');
    }

    public function testGoogleCalendarConnection(): void
    {
        $this->authorizeManageSecrets();

        $report = app(IntegrationProviderActionService::class)->googleCalendarConnectionNotification();

        if ($report['account_email'] !== null) {
            $this->settings['google_calendar_account_email'] = $report['account_email'];
        }

        $this->sendNotificationPayload($report);
    }

    public function openEmrConfigUrl(): void
    {
        $this->authorizeManageSecrets();

        $report = app(IntegrationProviderActionService::class)->emrConfigUrlReport();

        if (! $report['success'] || ! filled($report['url'])) {
            $this->sendNotificationPayload([
                'title' => 'Không thể mở trang cấu hình EMR',
                'body' => $report['message'],
                'status' => 'warning',
            ]);

            return;
        }

        $this->redirect((string) $report['url'], navigate: false);
    }

    public function generateWebLeadApiToken(): void
    {
        $this->authorizeManageSecrets();

        $this->settings['web_lead_api_token'] = 'wla_'.Str::random(48);

        $this->sendNotificationPayload([
            'title' => 'Đã tạo API token mới',
            'body' => 'Token mới đã được điền vào form. Nhấn "Lưu cài đặt tích hợp" để áp dụng.',
            'status' => 'success',
        ]);
    }

    protected function sendProviderReadinessNotification(string $providerKey): void
    {
        $this->sendNotificationPayload(
            app(IntegrationProviderActionService::class)->readinessNotification($providerKey),
        );
    }

    /**
     * @param  array{title:string,body:string,status:string}  $payload
     */
    protected function sendNotificationPayload(array $payload): void
    {
        $notification = Notification::make()
            ->title($payload['title'])
            ->body($payload['body']);

        $notification = match ($payload['status']) {
            'success' => $notification->success(),
            'danger' => $notification->danger(),
            default => $notification->warning(),
        };

        $notification->send();
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
        $this->settingsRevision = $this->currentSettingsRevision();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function settingFieldDefinitions(): array
    {
        return collect($this->getProviders())
            ->flatMap(static fn (array $provider): Collection => collect($provider['fields'] ?? [])
                ->map(static fn (array $field): array => [
                    ...$field,
                    'group' => $provider['group'],
                ]))
            ->values()
            ->all();
    }

    protected function currentSettingsRevision(): string
    {
        $definitions = collect($this->settingFieldDefinitions())
            ->map(static fn (array $field): array => [
                'key' => $field['key'],
                'default' => $field['default'] ?? null,
            ])
            ->all();

        $currentValues = ClinicSetting::resolveValuesForDefinitions($definitions);
        $snapshot = [];

        foreach ($this->settingFieldDefinitions() as $field) {
            $key = (string) $field['key'];
            $snapshot[$key] = $this->normalizeValueForCompare(
                $currentValues[$key] ?? ($field['default'] ?? null),
                $this->valueTypeForField($field),
            );
        }

        ksort($snapshot);

        return hash(
            'sha256',
            json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        );
    }

    /**
     * @return Collection<int, array{
     *     changed_at_label:string,
     *     changed_by_name:string,
     *     setting_label:string,
     *     setting_key:string,
     *     change_reason:?string,
     *     grace_expires_at_label:?string,
     *     old_value:?string,
     *     new_value:?string
     * }>
     */
    protected function renderedRecentLogs(): Collection
    {
        if (! $this->canViewAuditLogs() || ! Schema::hasTable('clinic_setting_logs')) {
            return collect();
        }

        return app(IntegrationSettingsAuditReadModelService::class)->renderedRecentLogs();
    }

    /**
     * @return Collection<int, array{
     *     key:string,
     *     display_name:string,
     *     grace_expires_at_label:string,
     *     remaining_minutes_label:string,
     *     rotation_reason:?string
     * }>
     */
    protected function renderedActiveSecretRotations(): Collection
    {
        if (! $this->canManageSecrets()) {
            return collect();
        }

        return app(IntegrationOperationalReadModelService::class)->renderedActiveGraceRotations();
    }

    /**
     * @return array<int, array{
     *     key:string,
     *     label:string,
     *     description:string,
     *     status_badge:array{label:string, classes:string},
     *     summary_badge:array{label:string, classes:string},
     *     issue_badge:?array{label:string, classes:string},
     *     meta_preview:array<int, array{label:string, value:int|string}>,
     *     status_message:?string,
     *     status_message_classes:?string
     * }>
     */
    protected function renderedProviderHealthCards(): array
    {
        return app(IntegrationProviderHealthReadModelService::class)->snapshotCards();
    }

    /**
     * @return array{
     *     form_panel:array{
     *         notice_panels:array<int, array{classes:string,message:string}>,
     *         revision_conflict_notice:array{is_visible:bool,classes:string,message:string},
     *         pre_sections:array<int, array{
     *             heading:string,
     *             description:string,
     *             partial:string,
     *             include_data:array<string, mixed>
     *         }>,
     *         provider_sections:array<int, array{
     *             heading:string,
     *             description:string,
     *             partial:string,
     *             include_data:array{provider:array<string, mixed>}
     *         }>,
     *         submit_action:array{is_visible:bool,label:string,icon:string}
     *     },
     *     post_form_sections:array<int, array{
     *         heading:string,
     *         description:string,
     *         partial:string,
     *         include_data:array<string, mixed>,
     *         section_classes:?string
     *     }>
     * }
     */
    #[Computed]
    public function pageViewState(): array
    {
        return [
            'form_panel' => [
                'notice_panels' => $this->noticePanels(),
                'revision_conflict_notice' => $this->revisionConflictNoticePanel(),
                'pre_sections' => $this->preFormSections(),
                'provider_sections' => $this->providerSections(),
                'submit_action' => $this->submitActionPanel(),
            ],
            'post_form_sections' => $this->postFormSections(),
        ];
    }

    /**
     * @return array<int, array{classes:string,message:string}>
     */
    protected function noticePanels(): array
    {
        return array_values(array_filter([
            $this->readOnlyNoticePanel()['is_visible']
                ? [
                    'classes' => 'rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800 dark:border-warning-900/60 dark:bg-warning-950/40 dark:text-warning-200',
                    'message' => $this->readOnlyNoticePanel()['message'],
                ]
                : null,
            $this->secretRotationNoticePanel()['is_visible']
                ? [
                    'classes' => 'rounded-lg border border-info-200 bg-info-50 px-4 py-3 text-sm text-info-800 dark:border-info-900/60 dark:bg-info-950/40 dark:text-info-200',
                    'message' => $this->secretRotationNoticePanel()['message'],
                ]
                : null,
        ]));
    }

    /**
     * @return array<int, array{
     *     heading:string,
     *     description:string,
     *     partial:string,
     *     include_data:array<string, mixed>
     * }>
     */
    protected function preFormSections(): array
    {
        $sections = [];

        $secretRotationPanel = $this->secretRotationPanel();

        if ($secretRotationPanel['items']->isNotEmpty()) {
            $sections[] = [
                'heading' => $secretRotationPanel['heading'],
                'description' => $secretRotationPanel['description'],
                'partial' => 'filament.pages.partials.integration-settings-secret-rotation-list',
                'include_data' => [
                    'panel' => $secretRotationPanel,
                ],
            ];
        }

        $providerHealthPanel = $this->providerHealthPanel();

        $sections[] = [
            'heading' => $providerHealthPanel['heading'],
            'description' => $providerHealthPanel['description'],
            'partial' => 'filament.pages.partials.provider-health-panel',
            'include_data' => [
                'panel' => $providerHealthPanel,
                'showHeader' => false,
                'showContainer' => false,
                'gridClasses' => 'grid gap-4 md:grid-cols-2',
                'cardContainerClasses' => 'rounded-xl border border-gray-200 bg-white px-4 py-4 dark:border-gray-800 dark:bg-gray-900',
            ],
        ];

        return $sections;
    }

    /**
     * @return array<int, array{
     *     heading:string,
     *     description:string,
     *     partial:string,
     *     include_data:array<string, mixed>,
     *     section_classes:?string
     * }>
     */
    protected function postFormSections(): array
    {
        $auditLogPanel = $this->auditLogPanel();

        if (! $auditLogPanel['is_visible']) {
            return [];
        }

        return [[
            'heading' => $auditLogPanel['heading'],
            'description' => $auditLogPanel['description'],
            'partial' => 'filament.pages.partials.integration-settings-audit-log-table',
            'include_data' => [
                'auditLog' => $auditLogPanel,
            ],
            'section_classes' => 'mt-6',
        ]];
    }

    /**
     * @return array<int, array{
     *     heading:string,
     *     description:string,
     *     partial:string,
     *     include_data:array{provider:array<string, mixed>}
     * }>
     */
    protected function providerSections(): array
    {
        return array_map(fn (array $provider): array => [
            'heading' => $provider['title'],
            'description' => $provider['description'],
            'partial' => 'filament.pages.partials.integration-settings-provider-panel',
            'include_data' => [
                'provider' => $provider,
            ],
        ], $this->providerPanels());
    }

    /**
     * @return array{is_visible:bool,message:string}
     */
    protected function readOnlyNoticePanel(): array
    {
        return [
            'is_visible' => ! $this->canManageRuntimeSettings(),
            'message' => 'Bạn đang ở chế độ chỉ xem. Chỉ người có quyền quản lý runtime settings hoặc integration secrets mới được lưu thay đổi.',
        ];
    }

    /**
     * @return array{is_visible:bool,message:string}
     */
    protected function secretRotationNoticePanel(): array
    {
        $secretRotationPanel = $this->secretRotationPanel();

        return [
            'is_visible' => (bool) $secretRotationPanel['show_notice'],
            'message' => 'Khi đổi `Web Lead API Token`, `Zalo Webhook Verify Token` hoặc `EMR API Key`, hệ thống sẽ giữ token cũ trong grace window để client bên ngoài có thời gian rollover. Sau khi grace window hết hạn, command `integrations:revoke-rotated-secrets` sẽ tự thu hồi token cũ.',
        ];
    }

    /**
     * @return array{is_visible:bool,classes:string,message:string}
     */
    protected function revisionConflictNoticePanel(): array
    {
        $message = $this->getErrorBag()->first('settingsRevision');

        return [
            'is_visible' => filled($message),
            'classes' => 'rounded-lg border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-800 dark:border-danger-900/60 dark:bg-danger-950/40 dark:text-danger-200',
            'message' => (string) $message,
        ];
    }

    /**
     * @return array{is_visible:bool,label:string,icon:string}
     */
    protected function submitActionPanel(): array
    {
        return [
            'is_visible' => $this->canManageRuntimeSettings(),
            'label' => 'Lưu cài đặt tích hợp',
            'icon' => 'heroicon-o-check-circle',
        ];
    }

    /**
     * @return array<string, array<int, array{
     *     wire_click:string,
     *     label:string,
     *     icon:string,
     *     color:string
     * }>>
     */
    protected function providerActionGroups(): array
    {
        return [
            'zalo' => [
                [
                    'wire_click' => 'testZaloReadiness',
                    'label' => 'Đánh giá sẵn sàng Zalo OA',
                    'icon' => 'heroicon-o-shield-check',
                    'color' => 'gray',
                ],
            ],
            'zns' => [
                [
                    'wire_click' => 'testZnsReadiness',
                    'label' => 'Đánh giá sẵn sàng ZNS',
                    'icon' => 'heroicon-o-shield-check',
                    'color' => 'gray',
                ],
            ],
            'google_calendar' => $this->canManageSecrets()
                ? [
                    [
                        'wire_click' => 'testGoogleCalendarConnection',
                        'label' => 'Test Google Calendar',
                        'icon' => 'heroicon-o-signal',
                        'color' => 'gray',
                    ],
                ]
                : [],
            'emr' => array_values(array_filter([
                [
                    'wire_click' => 'testDicomReadiness',
                    'label' => 'Đánh giá sẵn sàng DICOM / PACS',
                    'icon' => 'heroicon-o-shield-check',
                    'color' => 'gray',
                ],
                $this->canManageSecrets()
                    ? [
                        'wire_click' => 'testEmrConnection',
                        'label' => 'Test EMR',
                        'icon' => 'heroicon-o-signal',
                        'color' => 'gray',
                    ]
                    : null,
                $this->canManageSecrets()
                    ? [
                        'wire_click' => 'openEmrConfigUrl',
                        'label' => 'Mở config EMR',
                        'icon' => 'heroicon-o-arrow-top-right-on-square',
                        'color' => 'info',
                    ]
                    : null,
            ])),
            'web_lead' => array_values(array_filter([
                [
                    'wire_click' => 'testWebLeadReadiness',
                    'label' => 'Đánh giá sẵn sàng Web Lead API',
                    'icon' => 'heroicon-o-shield-check',
                    'color' => 'gray',
                ],
                $this->canManageSecrets()
                    ? [
                        'wire_click' => 'generateWebLeadApiToken',
                        'label' => 'Tạo API Token',
                        'icon' => 'heroicon-o-key',
                        'color' => 'gray',
                    ]
                    : null,
            ])),
        ];
    }

    /**
     * @return array<string, array{
     *     actions:array<int, array{
     *         wire_click:string,
     *         label:string,
     *         icon:string,
     *         color:string
     *     }>,
     *     guide_partial:?string
     * }>
     */
    protected function providerSupportPanels(): array
    {
        return [
            'zalo' => [
                'actions' => $this->providerActionGroups()['zalo'] ?? [],
                'guide_partial' => 'filament.pages.partials.zalo-oa-guide',
            ],
            'zns' => [
                'actions' => $this->providerActionGroups()['zns'] ?? [],
                'guide_partial' => null,
            ],
            'google_calendar' => [
                'actions' => $this->providerActionGroups()['google_calendar'] ?? [],
                'guide_partial' => null,
            ],
            'emr' => [
                'actions' => $this->providerActionGroups()['emr'] ?? [],
                'guide_partial' => null,
            ],
            'web_lead' => [
                'actions' => $this->providerActionGroups()['web_lead'] ?? [],
                'guide_partial' => 'filament.pages.partials.web-lead-api-guide',
            ],
            'popup' => [
                'actions' => [],
                'guide_partial' => 'filament.pages.partials.popup-announcement-guide',
            ],
        ];
    }

    /**
     * @return array<int, array{
     *     group:string,
     *     title:string,
     *     description:string,
     *     fields:array<int, array<string, mixed>>,
     *     rendered_fields:array<int, array{
     *         field:array<string, mixed>,
     *         state_path:string,
     *         partial:string
     *     }>,
     *     support_sections:array<int, array{
     *         partial:string,
     *         include_data:array<string, mixed>
     *     }
     * }>
     */
    protected function providerPanels(): array
    {
        return array_map(function (array $provider): array {
            $support = $this->providerSupportPanels()[$provider['group']] ?? ['actions' => [], 'guide_partial' => null];

            return [
                ...$provider,
                'rendered_fields' => array_values(array_map(function (array $field): array {
                    return [
                        'field' => $field,
                        'state_path' => 'settings.'.$field['state'],
                        'partial' => $this->fieldPartialView($field),
                    ];
                }, array_values(array_filter(
                    $provider['fields'],
                    fn (array $field): bool => ! ((bool) ($field['hidden'] ?? false)),
                )))),
                'support_sections' => array_values(array_filter([
                    $support['actions'] !== []
                        ? [
                            'partial' => 'filament.pages.partials.provider-action-buttons',
                            'include_data' => [
                                'actions' => $support['actions'],
                            ],
                        ]
                        : null,
                    $support['guide_partial'] !== null
                        ? [
                            'partial' => $support['guide_partial'],
                            'include_data' => [],
                        ]
                        : null,
                ])),
            ];
        }, $this->getProviders());
    }

    /**
     * @param  array{type?:string}  $field
     */
    protected function fieldPartialView(array $field): string
    {
        return match ($field['type'] ?? 'text') {
            'boolean' => 'filament.pages.partials.integration-setting-boolean-field',
            'select' => 'filament.pages.partials.integration-setting-select-field',
            'roles' => 'filament.pages.partials.integration-setting-roles-field',
            'textarea' => 'filament.pages.partials.integration-setting-textarea-field',
            'json' => 'filament.pages.partials.integration-setting-json-field',
            default => 'filament.pages.partials.integration-setting-input-field',
        };
    }

    /**
     * @return array{
     *     show_notice:bool,
     *     heading:string,
     *     description:string,
     *     items:Collection<int, array{
     *         key:string,
     *         display_name:string,
     *         grace_expires_at_label:string,
     *         remaining_minutes_label:string,
     *         rotation_reason:?string
     *     }>
     * }
     */
    protected function secretRotationPanel(): array
    {
        return [
            'show_notice' => $this->canManageSecrets(),
            'heading' => 'Grace window đang hoạt động',
            'description' => 'Các token cũ dưới đây vẫn còn hiệu lực tạm thời để tránh outage trong lúc rollout.',
            'items' => $this->renderedActiveSecretRotations(),
        ];
    }

    /**
     * @return array{
     *     heading:string,
     *     description:string,
     *     items:array<int, array{
     *         key:string,
     *         label:string,
     *         description:string,
     *         status_badge:array{label:string, classes:string},
     *         summary_badge:array{label:string, classes:string},
     *         issue_badge:?array{label:string, classes:string},
     *         meta_preview:array<int, array{label:string, value:int|string}>,
     *         status_message:?string,
     *         status_message_classes:?string
     *     }>
     * }
     */
    protected function providerHealthPanel(): array
    {
        return [
            'heading' => 'Provider health snapshot',
            'description' => 'Contract chung cho readiness, runtime drift va provider-specific hint.',
            'items' => $this->renderedProviderHealthCards(),
        ];
    }

    /**
     * @return array{
     *     is_visible:bool,
     *     heading:string,
     *     description:string,
     *     empty_state_text:string,
     *     items:Collection<int, array{
     *         changed_at_label:string,
     *         changed_by_name:string,
     *         setting_label:string,
     *         setting_key:string,
     *         change_reason:?string,
     *         grace_expires_at_label:?string,
     *         old_value:?string,
     *         new_value:?string
     *     }>
     * }
     */
    protected function auditLogPanel(): array
    {
        return [
            'is_visible' => $this->canViewAuditLogs(),
            'heading' => 'Nhật ký thay đổi cài đặt',
            'description' => 'Theo dõi ai sửa, sửa gì và thời điểm cập nhật gần nhất.',
            'empty_state_text' => 'Chưa có lịch sử thay đổi.',
            'items' => $this->renderedRecentLogs(),
        ];
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

    /**
     * @param  array<string, mixed>  $field
     */
    protected function valueTypeForField(array $field): string
    {
        return match ($field['type']) {
            'url', 'email', 'select', 'color', 'textarea' => 'text',
            'roles' => 'json',
            default => $field['type'],
        };
    }

    protected function logSettingChange(
        ClinicSetting $setting,
        mixed $oldValue,
        mixed $newValue,
        bool $isSecret = false,
        ?string $reason = null,
        array $context = [],
    ): void {
        if (! Schema::hasTable('clinic_setting_logs')) {
            return;
        }

        $payload = [
            'clinic_setting_id' => $setting->id,
            'setting_group' => $setting->group ?? 'integration',
            'setting_key' => $setting->key,
            'setting_label' => $setting->label,
            'old_value' => $this->valueForAuditLog($oldValue, $isSecret),
            'new_value' => $this->valueForAuditLog($newValue, $isSecret),
            'is_secret' => $isSecret,
            'changed_by' => auth()->id(),
            'changed_at' => now(),
        ];

        if (Schema::hasColumn('clinic_setting_logs', 'change_reason')) {
            $payload['change_reason'] = $reason;
        }

        if (Schema::hasColumn('clinic_setting_logs', 'context')) {
            $payload['context'] = $context;
        }

        ClinicSettingLog::query()->create($payload);
    }

    public function canViewAuditLogs(): bool
    {
        return (bool) auth()->user()?->can(static::AUDIT_LOG_PERMISSION);
    }

    public function canManageRuntimeSettings(): bool
    {
        return (bool) auth()->user()?->can(static::MANAGE_RUNTIME_PERMISSION);
    }

    public function canManageSecrets(): bool
    {
        return (bool) auth()->user()?->can(static::MANAGE_SECRETS_PERMISSION);
    }

    /**
     * @throws AuthorizationException
     */
    protected function authorizeManageRuntimeSettings(): void
    {
        if ($this->canManageRuntimeSettings()) {
            return;
        }

        throw new AuthorizationException('Bạn không có quyền cập nhật runtime integration settings.');
    }

    /**
     * @throws AuthorizationException
     */
    protected function authorizeManageSecrets(): void
    {
        if ($this->canManageSecrets()) {
            return;
        }

        throw new AuthorizationException('Bạn không có quyền cập nhật integration secrets.');
    }

    protected function isOperationallySensitiveField(string $key): bool
    {
        return in_array($key, [
            'web_lead.enabled',
            'zalo.enabled',
            'zns.enabled',
            'google_calendar.enabled',
            'emr.enabled',
        ], true);
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

    /**
     * @param  array<string, mixed>  $summary
     */
    protected function formatRotationNotificationLine(array $summary): string
    {
        $displayName = (string) ($summary['display_name'] ?? 'Integration secret');

        if (($summary['grace_applied'] ?? false) && filled($summary['grace_expires_at'] ?? null)) {
            return sprintf(
                '%s: token cũ còn hiệu lực tới %s để tránh cắt kết nối đột ngột.',
                $displayName,
                \Illuminate\Support\Carbon::parse((string) $summary['grace_expires_at'])->format('d/m/Y H:i'),
            );
        }

        if (($summary['initialized'] ?? false) === true) {
            return $displayName.': secret mới đã được lưu.';
        }

        if (($summary['rotated'] ?? false) === true) {
            return $displayName.': secret đã được cập nhật và không còn grace token cũ.';
        }

        return '';
    }

    protected function formatFieldStateValue(array $field, mixed $value): mixed
    {
        if (($field['type'] ?? null) === 'roles') {
            return collect(is_array($value) ? $value : [])
                ->filter(static fn (mixed $item): bool => is_string($item) && trim($item) !== '')
                ->map(static fn (string $item): string => trim($item))
                ->values()
                ->all();
        }

        if (($field['type'] ?? null) === 'select') {
            $options = is_array($field['options'] ?? null)
                ? $field['options']
                : [];

            if ($options === []) {
                return $value;
            }

            $optionKeys = array_map(static fn (mixed $key): string => (string) $key, array_keys($options));
            $normalizedValue = filled($value) ? (string) $value : '';

            if ($normalizedValue !== '' && in_array($normalizedValue, $optionKeys, true)) {
                return $normalizedValue;
            }

            $defaultValue = filled($field['default'] ?? null) ? (string) $field['default'] : '';
            if ($defaultValue !== '' && in_array($defaultValue, $optionKeys, true)) {
                return $defaultValue;
            }

            return $optionKeys[0] ?? null;
        }

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

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function validateIntegrationProviderDependencies(array $validated): void
    {
        $errors = [];

        $zaloEnabled = filter_var(data_get($validated, 'settings.zalo_enabled', false), FILTER_VALIDATE_BOOLEAN);

        if ($zaloEnabled) {
            if (trim((string) data_get($validated, 'settings.zalo_oa_id', '')) === '') {
                $errors['settings.zalo_oa_id'] = 'OA ID là bắt buộc khi bật Zalo OA.';
            }

            if (trim((string) data_get($validated, 'settings.zalo_app_id', '')) === '') {
                $errors['settings.zalo_app_id'] = 'App ID là bắt buộc khi bật Zalo OA.';
            }

            if (trim((string) data_get($validated, 'settings.zalo_app_secret', '')) === '') {
                $errors['settings.zalo_app_secret'] = 'App Secret là bắt buộc khi bật Zalo OA.';
            }

            $webhookToken = trim((string) data_get($validated, 'settings.zalo_webhook_token', ''));
            $accessToken = trim((string) data_get($validated, 'settings.zalo_access_token', ''));
            $sendEndpoint = trim((string) data_get($validated, 'settings.zalo_send_endpoint', ''));
            $defaultBranchCode = trim((string) data_get($validated, 'settings.zalo_inbox_default_branch_code', ''));
            $pollingSeconds = data_get($validated, 'settings.zalo_inbox_polling_seconds');

            if ($webhookToken === '') {
                $errors['settings.zalo_webhook_token'] = 'Webhook Verify Token là bắt buộc khi bật Zalo OA.';
            } elseif (mb_strlen($webhookToken) < 24) {
                $errors['settings.zalo_webhook_token'] = 'Webhook Verify Token cần tối thiểu 24 ký tự.';
            }

            if ($accessToken === '') {
                $errors['settings.zalo_access_token'] = 'Access Token là bắt buộc khi bật Zalo OA inbox.';
            }

            if ($sendEndpoint === '') {
                $errors['settings.zalo_send_endpoint'] = 'Zalo OA send endpoint là bắt buộc khi bật Zalo OA inbox.';
            }

            if ($defaultBranchCode === '') {
                $errors['settings.zalo_inbox_default_branch_code'] = 'Chi nhánh mặc định cho inbox Zalo là bắt buộc.';
            } elseif (! Branch::query()->where('code', $defaultBranchCode)->where('active', true)->exists()) {
                $errors['settings.zalo_inbox_default_branch_code'] = 'Chi nhánh mặc định của inbox Zalo không hợp lệ hoặc không còn hoạt động.';
            }

            if (! is_numeric($pollingSeconds) || (int) $pollingSeconds < 1 || (int) $pollingSeconds > 30) {
                $errors['settings.zalo_inbox_polling_seconds'] = 'Chu kỳ polling inbox phải nằm trong khoảng 1-30 giây.';
            }
        }

        $facebookEnabled = filter_var(data_get($validated, 'settings.facebook_enabled', false), FILTER_VALIDATE_BOOLEAN);

        if ($facebookEnabled) {
            if (trim((string) data_get($validated, 'settings.facebook_page_id', '')) === '') {
                $errors['settings.facebook_page_id'] = 'Page ID là bắt buộc khi bật Facebook Messenger.';
            }

            if (trim((string) data_get($validated, 'settings.facebook_app_id', '')) === '') {
                $errors['settings.facebook_app_id'] = 'App ID là bắt buộc khi bật Facebook Messenger.';
            }

            if (trim((string) data_get($validated, 'settings.facebook_app_secret', '')) === '') {
                $errors['settings.facebook_app_secret'] = 'App Secret là bắt buộc khi bật Facebook Messenger.';
            }

            $verifyToken = trim((string) data_get($validated, 'settings.facebook_webhook_verify_token', ''));
            $pageAccessToken = trim((string) data_get($validated, 'settings.facebook_page_access_token', ''));
            $sendEndpoint = trim((string) data_get($validated, 'settings.facebook_send_endpoint', ''));
            $defaultBranchCode = trim((string) data_get($validated, 'settings.facebook_inbox_default_branch_code', ''));

            if ($verifyToken === '') {
                $errors['settings.facebook_webhook_verify_token'] = 'Webhook Verify Token là bắt buộc khi bật Facebook Messenger.';
            } elseif (mb_strlen($verifyToken) < 24) {
                $errors['settings.facebook_webhook_verify_token'] = 'Webhook Verify Token cần tối thiểu 24 ký tự.';
            }

            if ($pageAccessToken === '') {
                $errors['settings.facebook_page_access_token'] = 'Page Access Token là bắt buộc khi bật Facebook Messenger.';
            }

            if ($sendEndpoint === '') {
                $errors['settings.facebook_send_endpoint'] = 'Messenger send endpoint là bắt buộc khi bật Facebook Messenger.';
            }

            if ($defaultBranchCode === '') {
                $errors['settings.facebook_inbox_default_branch_code'] = 'Chi nhánh mặc định cho inbox Facebook là bắt buộc.';
            } elseif (! Branch::query()->where('code', $defaultBranchCode)->where('active', true)->exists()) {
                $errors['settings.facebook_inbox_default_branch_code'] = 'Chi nhánh mặc định của inbox Facebook không hợp lệ hoặc không còn hoạt động.';
            }
        }

        $znsEnabled = filter_var(data_get($validated, 'settings.zns_enabled', false), FILTER_VALIDATE_BOOLEAN);

        if ($znsEnabled) {
            if (trim((string) data_get($validated, 'settings.zns_access_token', '')) === '') {
                $errors['settings.zns_access_token'] = 'Access Token là bắt buộc khi bật ZNS.';
            }

            if (trim((string) data_get($validated, 'settings.zns_refresh_token', '')) === '') {
                $errors['settings.zns_refresh_token'] = 'Refresh Token là bắt buộc khi bật ZNS.';
            }

            $templateLeadWelcome = trim((string) data_get($validated, 'settings.zns_template_lead_welcome', ''));
            $templateAppointment = trim((string) data_get($validated, 'settings.zns_template_appointment', ''));
            $templatePayment = trim((string) data_get($validated, 'settings.zns_template_payment', ''));
            $templateBirthday = trim((string) data_get($validated, 'settings.zns_template_birthday', ''));
            $autoLeadWelcome = filter_var(data_get($validated, 'settings.zns_auto_send_lead_welcome', false), FILTER_VALIDATE_BOOLEAN);
            $autoAppointmentReminder = filter_var(data_get($validated, 'settings.zns_auto_send_appointment_reminder', false), FILTER_VALIDATE_BOOLEAN);
            $autoBirthday = filter_var(data_get($validated, 'settings.zns_auto_send_birthday', false), FILTER_VALIDATE_BOOLEAN);
            $sendEndpoint = trim((string) data_get($validated, 'settings.zns_send_endpoint', ''));

            if (
                $templateLeadWelcome === ''
                && $templateAppointment === ''
                && $templatePayment === ''
                && $templateBirthday === ''
            ) {
                $errors['settings.zns_template_appointment'] = 'Cần ít nhất một template ZNS (lead welcome/nhắc lịch/nhắc thanh toán/sinh nhật).';
            }

            if ($autoLeadWelcome && $templateLeadWelcome === '') {
                $errors['settings.zns_template_lead_welcome'] = 'Cần template lead welcome khi bật tự động gửi lead mới.';
            }

            if ($autoAppointmentReminder && $templateAppointment === '') {
                $errors['settings.zns_template_appointment'] = 'Cần template nhắc lịch hẹn khi bật tự động gửi nhắc hẹn.';
            }

            if ($autoBirthday && $templateBirthday === '') {
                $errors['settings.zns_template_birthday'] = 'Cần template sinh nhật khi bật tự động gửi chúc mừng sinh nhật.';
            }

            if ($sendEndpoint === '') {
                $errors['settings.zns_send_endpoint'] = 'ZNS send endpoint là bắt buộc khi bật ZNS.';
            }
        }

        $googleEnabled = filter_var(data_get($validated, 'settings.google_calendar_enabled', false), FILTER_VALIDATE_BOOLEAN);

        if ($googleEnabled) {
            if (trim((string) data_get($validated, 'settings.google_calendar_client_id', '')) === '') {
                $errors['settings.google_calendar_client_id'] = 'Client ID là bắt buộc khi bật Google Calendar.';
            }

            if (trim((string) data_get($validated, 'settings.google_calendar_client_secret', '')) === '') {
                $errors['settings.google_calendar_client_secret'] = 'Client Secret là bắt buộc khi bật Google Calendar.';
            }

            if (trim((string) data_get($validated, 'settings.google_calendar_refresh_token', '')) === '') {
                $errors['settings.google_calendar_refresh_token'] = 'Refresh Token là bắt buộc khi bật Google Calendar.';
            }

            if (trim((string) data_get($validated, 'settings.google_calendar_calendar_id', '')) === '') {
                $errors['settings.google_calendar_calendar_id'] = 'Calendar ID là bắt buộc khi bật Google Calendar.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
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

    /**
     * @return array<string, string>
     */
    protected function mediaDiskOptions(): array
    {
        $disks = array_keys((array) config('filesystems.disks', []));
        $selectedDisks = collect($disks)
            ->filter(static fn (mixed $disk): bool => is_string($disk) && trim($disk) !== '')
            ->mapWithKeys(static fn (string $disk): array => [$disk => strtoupper($disk)])
            ->all();

        if ($selectedDisks === []) {
            return [
                'local' => 'LOCAL',
                'public' => 'PUBLIC',
            ];
        }

        return $selectedDisks;
    }

    /**
     * @return array<string, string>
     */
    protected function roleOptions(): array
    {
        return Role::query()
            ->orderBy('name')
            ->pluck('name', 'name')
            ->all();
    }
}
