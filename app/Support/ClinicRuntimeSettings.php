<?php

namespace App\Support;

use App\Models\ClinicSetting;
use Illuminate\Support\Arr;

class ClinicRuntimeSettings
{
    public static function defaultExamIndicationOptions(): array
    {
        return [
            'cephalometric' => 'Cephalometric',
            '3d' => '3D',
            'canchiep' => 'Cận chóp',
            'xet_nghiem_huyet_hoc' => 'Xét nghiệm huyết học',
            'panorama' => 'Panorama',
            'ext' => 'Ảnh (ext)',
            'int' => 'Ảnh (int)',
            'xet_nghiem_sinh_hoa' => 'Xét nghiệm sinh hóa',
            '3d5x5' => '3D 5x5',
            'khac' => 'Khác',
        ];
    }

    public static function examIndicationOptions(): array
    {
        $options = static::catalogOptions(
            'catalog.exam_indications',
            static::defaultExamIndicationOptions(),
        );

        $normalizedOptions = [];

        foreach ($options as $rawKey => $rawLabel) {
            $normalizedKey = static::normalizeExamIndicationKey((string) $rawKey);
            $normalizedLabel = trim((string) $rawLabel);

            if ($normalizedKey === '' || $normalizedLabel === '') {
                continue;
            }

            if (! array_key_exists($normalizedKey, $normalizedOptions)) {
                $normalizedOptions[$normalizedKey] = $normalizedLabel;
            }
        }

        if ($normalizedOptions === []) {
            $normalizedOptions = static::defaultExamIndicationOptions();
        }

        return $normalizedOptions;
    }

    public static function normalizeExamIndicationKey(string $key): string
    {
        $normalized = strtolower(trim($key));

        return match ($normalized) {
            'anh_ext', 'image_ext', 'image-ext' => 'ext',
            'anh_int', 'image_int', 'image-int' => 'int',
            '3d_5x5', '3d-5x5' => '3d5x5',
            'can_chop', 'canchop' => 'canchiep',
            default => $normalized,
        };
    }

    public static function defaultCustomerSourceOptions(): array
    {
        return [
            'walkin' => 'Khách vãng lai',
            'facebook' => 'Facebook',
            'zalo' => 'Zalo',
            'referral' => 'Giới thiệu',
            'appointment' => 'Lịch hẹn',
            'other' => 'Khác',
        ];
    }

    public static function customerSourceOptions(): array
    {
        return static::catalogOptions(
            'catalog.customer_sources',
            static::defaultCustomerSourceOptions(),
        );
    }

    public static function customerSourceLabel(?string $source): string
    {
        $normalizedSource = trim((string) $source);

        return static::customerSourceOptions()[$normalizedSource] ?? 'Khác';
    }

    public static function defaultCustomerStatusOptions(): array
    {
        return [
            'lead' => 'Lead',
            'contacted' => 'Đã liên hệ',
            'confirmed' => 'Đã xác nhận',
            'converted' => 'Đã chuyển đổi',
            'lost' => 'Mất lead',
        ];
    }

    public static function customerStatusOptions(): array
    {
        return static::catalogOptions(
            'catalog.customer_statuses',
            static::defaultCustomerStatusOptions(),
        );
    }

    public static function customerStatusLabel(?string $status): string
    {
        $normalizedStatus = trim((string) $status);

        return static::customerStatusOptions()[$normalizedStatus] ?? 'Không xác định';
    }

    public static function defaultCustomerStatus(): string
    {
        $options = static::customerStatusOptions();

        if (array_key_exists('lead', $options)) {
            return 'lead';
        }

        $first = array_key_first($options);

        return $first !== null ? (string) $first : 'lead';
    }

    public static function defaultCustomerSource(): string
    {
        $options = static::customerSourceOptions();

        if (array_key_exists('walkin', $options)) {
            return 'walkin';
        }

        $first = array_key_first($options);

        return $first !== null ? (string) $first : 'other';
    }

    public static function defaultWebLeadCustomerSource(): string
    {
        $options = static::customerSourceOptions();

        if (array_key_exists('other', $options)) {
            return 'other';
        }

        return static::defaultCustomerSource();
    }

    public static function webLeadRealtimeNotificationEnabled(): bool
    {
        return filter_var(
            ClinicSetting::getValue('web_lead.realtime_notification_enabled', false),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    /**
     * @return array<int, string>
     */
    public static function webLeadRealtimeNotificationRoles(): array
    {
        $value = ClinicSetting::getValue('web_lead.realtime_notification_roles', ['CSKH']);

        return collect(is_array($value) ? $value : [])
            ->filter(static fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->map(static fn (string $item): string => trim($item))
            ->unique()
            ->values()
            ->all();
    }

    public static function defaultCareTypeOptions(): array
    {
        return [
            'warranty' => 'Bảo hành',
            'recall_recare' => 'Recall / Re-care',
            'post_treatment_follow_up' => 'Hỏi thăm sau điều trị',
            'treatment_plan_follow_up' => 'Theo dõi chưa chốt kế hoạch',
            'appointment_reminder' => 'Nhắc lịch hẹn',
            'no_show_recovery' => 'Recovery no-show',
            'reactivation_follow_up' => 'Reactivation bệnh nhân',
            'risk_high_follow_up' => 'Can thiệp risk cao',
            'payment_reminder' => 'Nhắc thanh toán',
            'medication_reminder' => 'Nhắc lịch uống thuốc',
            'birthday_care' => 'Chăm sóc sinh nhật',
            'general_care' => 'Chăm sóc chung',
            'other' => 'Khác',
        ];
    }

    public static function careTypeOptions(bool $includeSystemTypes = false): array
    {
        $options = static::catalogOptions(
            'catalog.care_types',
            static::defaultCareTypeOptions(),
        );

        if (! $includeSystemTypes) {
            unset($options['birthday_care'], $options['general_care']);
        }

        if (! array_key_exists('other', $options)) {
            $options['other'] = 'Khác';
        }

        return $options;
    }

    public static function careTypeDisplayOptions(): array
    {
        return static::careTypeOptions(includeSystemTypes: true);
    }

    public static function careTypeLabel(?string $type): string
    {
        $normalizedType = trim((string) $type);

        return static::careTypeDisplayOptions()[$normalizedType] ?? 'Khác';
    }

    public static function defaultPaymentSourceLabels(): array
    {
        return [
            'patient' => 'Bệnh nhân',
            'insurance' => 'Bảo hiểm',
            'other' => 'Khác',
        ];
    }

    public static function paymentSourceLabels(): array
    {
        return static::catalogOptions(
            'catalog.payment_sources',
            static::defaultPaymentSourceLabels(),
        );
    }

    public static function paymentSourceOptions(bool $withEmoji = true): array
    {
        $labels = static::paymentSourceLabels();

        if (! $withEmoji) {
            return $labels;
        }

        $emojiMap = [
            'patient' => '👤',
            'insurance' => '🏥',
            'other' => '📄',
        ];

        $options = [];

        foreach ($labels as $source => $label) {
            $emoji = $emojiMap[$source] ?? '•';
            $options[$source] = "{$emoji} {$label}";
        }

        return $options;
    }

    public static function paymentSourceLabel(?string $source): string
    {
        $normalizedSource = trim((string) $source);

        return static::paymentSourceLabels()[$normalizedSource] ?? 'Không xác định';
    }

    public static function defaultPaymentSource(): string
    {
        $options = static::paymentSourceLabels();

        if (array_key_exists('patient', $options)) {
            return 'patient';
        }

        $first = array_key_first($options);

        return $first !== null ? (string) $first : 'other';
    }

    public static function paymentSourceColor(?string $source): string
    {
        return match (trim((string) $source)) {
            'patient' => 'success',
            'insurance' => 'info',
            'other' => 'gray',
            default => 'gray',
        };
    }

    public static function defaultPaymentDirectionLabels(): array
    {
        return [
            'receipt' => 'Phiếu thu',
            'refund' => 'Phiếu hoàn',
        ];
    }

    public static function paymentDirectionLabels(): array
    {
        return static::catalogOptions(
            'catalog.payment_directions',
            static::defaultPaymentDirectionLabels(),
        );
    }

    public static function paymentDirectionOptions(): array
    {
        return static::paymentDirectionLabels();
    }

    public static function paymentDirectionLabel(?string $direction): string
    {
        $normalizedDirection = trim((string) $direction);

        return static::paymentDirectionLabels()[$normalizedDirection] ?? 'Phiếu thu';
    }

    public static function defaultPaymentDirection(): string
    {
        $options = static::paymentDirectionOptions();

        if (array_key_exists('receipt', $options)) {
            return 'receipt';
        }

        $first = array_key_first($options);

        return $first !== null ? (string) $first : 'receipt';
    }

    public static function defaultGenderOptions(): array
    {
        return [
            'male' => 'Nam',
            'female' => 'Nữ',
            'other' => 'Khác',
        ];
    }

    public static function genderOptions(): array
    {
        return static::catalogOptions(
            'catalog.gender_options',
            static::defaultGenderOptions(),
        );
    }

    public static function genderLabel(?string $gender): string
    {
        $normalized = trim((string) $gender);

        return static::genderOptions()[$normalized] ?? 'Chưa xác định';
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return ClinicSetting::getValue($key, $default);
    }

    public static function boolean(string $key, bool $default = false): bool
    {
        return filter_var(static::get($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

    public static function integer(string $key, int $default = 0): int
    {
        $value = static::get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function isGoogleCalendarEnabled(): bool
    {
        return static::boolean('google_calendar.enabled', false);
    }

    public static function googleCalendarSyncMode(): string
    {
        return (string) static::get('google_calendar.sync_mode', 'two_way');
    }

    public static function isEmrEnabled(): bool
    {
        return static::boolean('emr.enabled', false);
    }

    public static function emrProvider(): string
    {
        return trim((string) static::get('emr.provider', 'internal'));
    }

    public static function emrBaseUrl(): string
    {
        return trim((string) static::get('emr.base_url', ''));
    }

    public static function emrApiKey(): string
    {
        return trim((string) static::get('emr.api_key', ''));
    }

    public static function emrClinicCode(): string
    {
        return trim((string) static::get('emr.clinic_code', ''));
    }

    public static function isVnpayEnabled(): bool
    {
        return static::boolean('vnpay.enabled', false);
    }

    public static function paymentMethodLabels(): array
    {
        $methods = [
            'cash' => 'Tiền mặt',
            'card' => 'Thẻ tín dụng/ghi nợ',
            'transfer' => 'Chuyển khoản',
        ];

        if (static::isVnpayEnabled()) {
            $methods['vnpay'] = 'VNPay (Online)';
        }

        $methods['other'] = 'Khác';

        return $methods;
    }

    public static function paymentMethodOptions(bool $withEmoji = true): array
    {
        $labels = static::paymentMethodLabels();

        if (! $withEmoji) {
            return $labels;
        }

        $emojiMap = [
            'cash' => '💵',
            'card' => '💳',
            'transfer' => '🏦',
            'vnpay' => '🟦',
            'other' => '📝',
        ];

        $options = [];

        foreach ($labels as $method => $label) {
            $emoji = $emojiMap[$method] ?? '•';
            $options[$method] = "{$emoji} {$label}";
        }

        return $options;
    }

    public static function paymentMethodIcon(string $method): string
    {
        return match ($method) {
            'cash' => 'heroicon-o-banknotes',
            'card' => 'heroicon-o-credit-card',
            'transfer' => 'heroicon-o-arrow-path',
            'vnpay' => 'heroicon-o-device-phone-mobile',
            'other' => 'heroicon-o-ellipsis-horizontal-circle',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    public static function paymentMethodColor(string $method): string
    {
        return match ($method) {
            'cash' => 'success',
            'card' => 'info',
            'transfer' => 'warning',
            'vnpay' => 'primary',
            'other' => 'gray',
            default => 'gray',
        };
    }

    public static function medicationReminderOffsetDays(): int
    {
        return max(
            0,
            static::integer(
                'care.medication_reminder_offset_days',
                (int) config('care.medication_reminder_offset_days', 0),
            ),
        );
    }

    public static function postTreatmentFollowUpOffsetDays(): int
    {
        return max(
            0,
            static::integer(
                'care.post_treatment_follow_up_offset_days',
                (int) config('care.post_treatment_follow_up_offset_days', 3),
            ),
        );
    }

    public static function recallDefaultOffsetDays(): int
    {
        return max(
            0,
            static::integer(
                'care.recall_default_offset_days',
                (int) config('care.recall_default_offset_days', 180),
            ),
        );
    }

    public static function noShowRecoveryDelayHours(): int
    {
        return max(
            0,
            static::integer(
                'care.no_show_recovery_delay_hours',
                (int) config('care.no_show_recovery_delay_hours', 2),
            ),
        );
    }

    public static function planFollowUpDelayDays(): int
    {
        return max(
            0,
            static::integer(
                'care.plan_follow_up_delay_days',
                (int) config('care.plan_follow_up_delay_days', 2),
            ),
        );
    }

    public static function invoiceAgingReminderDelayDays(): int
    {
        return max(
            0,
            static::integer(
                'care.invoice_aging_reminder_delay_days',
                (int) config('care.invoice_aging_reminder_delay_days', 1),
            ),
        );
    }

    public static function invoiceAgingReminderMinIntervalHours(): int
    {
        return max(
            1,
            static::integer(
                'care.invoice_aging_reminder_min_interval_hours',
                (int) config('care.invoice_aging_reminder_min_interval_hours', 24),
            ),
        );
    }

    public static function reportSnapshotSlaHours(): int
    {
        return max(
            1,
            static::integer(
                'report.snapshot_sla_hours',
                (int) config('care.report_snapshot_sla_hours', 6),
            ),
        );
    }

    public static function reportSnapshotStaleAfterHours(): int
    {
        return max(
            1,
            static::integer(
                'report.snapshot_stale_after_hours',
                (int) config('care.report_snapshot_stale_after_hours', 24),
            ),
        );
    }

    /**
     * @return array<int, string>
     */
    public static function securityMfaRequiredRoles(): array
    {
        $value = static::get(
            'security.mfa_required_roles',
            (array) config('care.security_mfa_required_roles', ['Admin', 'Manager']),
        );

        $roles = is_array($value)
            ? $value
            : explode(',', (string) $value);

        return collect($roles)
            ->filter(static fn (mixed $role): bool => is_string($role) && trim($role) !== '')
            ->map(static fn (string $role): string => trim($role))
            ->unique()
            ->values()
            ->all();
    }

    public static function securitySessionIdleTimeoutMinutes(): int
    {
        return max(
            5,
            static::integer(
                'security.session_idle_timeout_minutes',
                (int) config('care.security_session_idle_timeout_minutes', 30),
            ),
        );
    }

    public static function securityLoginMaxAttempts(): int
    {
        return max(
            1,
            static::integer(
                'security.login_max_attempts',
                (int) config('care.security_login_max_attempts', 5),
            ),
        );
    }

    public static function securityLoginLockoutMinutes(): int
    {
        return max(
            1,
            static::integer(
                'security.login_lockout_minutes',
                (int) config('care.security_login_lockout_minutes', 15),
            ),
        );
    }

    public static function schedulerCommandTimeoutSeconds(): int
    {
        return max(
            10,
            static::integer(
                'scheduler.command_timeout_seconds',
                (int) config('care.scheduler_command_timeout_seconds', 180),
            ),
        );
    }

    public static function schedulerCommandMaxAttempts(): int
    {
        return max(
            1,
            static::integer(
                'scheduler.command_max_attempts',
                (int) config('care.scheduler_command_max_attempts', 2),
            ),
        );
    }

    public static function schedulerCommandRetryDelaySeconds(): int
    {
        return max(
            0,
            static::integer(
                'scheduler.command_retry_delay_seconds',
                (int) config('care.scheduler_command_retry_delay_seconds', 15),
            ),
        );
    }

    public static function schedulerCommandAlertAfterSeconds(): int
    {
        return max(
            0,
            static::integer(
                'scheduler.command_alert_after_seconds',
                (int) config('care.scheduler_command_alert_after_seconds', 120),
            ),
        );
    }

    public static function schedulerAutomationActorUserId(): ?int
    {
        $value = static::get(
            'scheduler.automation_actor_user_id',
            config('care.scheduler_automation_actor_user_id'),
        );

        if (! is_numeric($value)) {
            return null;
        }

        $actorId = (int) $value;

        return $actorId > 0 ? $actorId : null;
    }

    public static function schedulerAutomationActorRequiredRole(): string
    {
        return trim((string) static::get(
            'scheduler.automation_actor_required_role',
            (string) config('care.scheduler_automation_actor_required_role', 'AutomationService'),
        ));
    }

    public static function schedulerLockExpiresAfterMinutes(): int
    {
        $ttlSeconds = (static::schedulerCommandTimeoutSeconds() * static::schedulerCommandMaxAttempts())
            + (static::schedulerCommandRetryDelaySeconds() * max(0, static::schedulerCommandMaxAttempts() - 1))
            + 120;

        return max(5, (int) ceil($ttlSeconds / 60));
    }

    public static function mpiDedupeMinConfidence(): float
    {
        return max(
            0.0,
            min(
                100.0,
                (float) static::get('mpi.dedupe_min_confidence', 90),
            ),
        );
    }

    public static function kpiNoShowRateMaxThreshold(): float
    {
        return max(
            0.0,
            min(
                100.0,
                (float) static::get('report.kpi_no_show_rate_max', 15.0),
            ),
        );
    }

    public static function kpiChairUtilizationRateMinThreshold(): float
    {
        return max(
            0.0,
            min(
                100.0,
                (float) static::get('report.kpi_chair_utilization_rate_min', 65.0),
            ),
        );
    }

    public static function kpiTreatmentAcceptanceRateMinThreshold(): float
    {
        return max(
            0.0,
            min(
                100.0,
                (float) static::get('report.kpi_treatment_acceptance_rate_min', 55.0),
            ),
        );
    }

    public static function careChannelOptions(): array
    {
        $channels = [
            'message' => 'Gửi tin nhắn',
            'call' => 'Gọi điện',
            'chat' => 'Chat',
            'gift' => 'Tặng quà',
        ];

        if (static::boolean('zalo.enabled', false)) {
            $channels['zalo'] = 'Zalo OA';
        }

        if (static::boolean('zns.enabled', false)) {
            $channels['zns'] = 'ZNS';
        }

        $channels['sms'] = 'SMS';
        $channels['email'] = 'Email';
        $channels['other'] = 'Khác';

        return $channels;
    }

    public static function defaultCareChannel(): string
    {
        $fallbackChannel = static::boolean('zalo.enabled', false)
            ? 'zalo'
            : (static::boolean('zns.enabled', false) ? 'zns' : 'call');

        $default = (string) static::get(
            'care.default_channel',
            $fallbackChannel,
        );

        return array_key_exists($default, static::careChannelOptions())
            ? $default
            : 'call';
    }

    public static function allowOverpay(): bool
    {
        return static::boolean('finance.allow_overpay', true);
    }

    public static function allowDraftPrepay(): bool
    {
        return static::boolean('finance.allow_prepay_draft', false);
    }

    public static function allowDeposit(): bool
    {
        return static::boolean('finance.allow_deposit', true);
    }

    public static function brandingClinicName(): string
    {
        $fallback = (string) config('app.name', 'Dental CRM');
        $value = trim((string) static::get('branding.clinic_name', $fallback));

        return $value !== '' ? $value : $fallback;
    }

    public static function brandingLogoUrl(): string
    {
        $value = trim((string) static::get('branding.logo_url', ''));

        return $value !== '' ? $value : asset('images/logo.svg');
    }

    public static function brandingAddress(): string
    {
        return trim((string) static::get('branding.address', ''));
    }

    public static function brandingPhone(): string
    {
        return trim((string) static::get('branding.phone', ''));
    }

    public static function brandingEmail(): string
    {
        return trim((string) static::get('branding.email', ''));
    }

    public static function brandingButtonBackgroundColor(): string
    {
        return static::normalizeHexColor(
            static::get('branding.button_bg_color', '#2f66f6'),
            '#2f66f6',
        );
    }

    public static function brandingButtonHoverBackgroundColor(): string
    {
        $configuredHover = trim((string) static::get('branding.button_bg_hover_color', ''));

        if ($configuredHover !== '') {
            return static::normalizeHexColor($configuredHover, '#2456dc');
        }

        return static::darkenHexColor(static::brandingButtonBackgroundColor(), 0.12);
    }

    public static function brandingButtonTextColor(): string
    {
        return static::normalizeHexColor(
            static::get('branding.button_text_color', '#ffffff'),
            '#ffffff',
        );
    }

    /**
     * @return array{clinic_name: string, logo_url: string, address: string, phone: string, email: string, button_bg_color: string, button_bg_hover_color: string, button_text_color: string}
     */
    public static function brandingProfile(): array
    {
        return [
            'clinic_name' => static::brandingClinicName(),
            'logo_url' => static::brandingLogoUrl(),
            'address' => static::brandingAddress(),
            'phone' => static::brandingPhone(),
            'email' => static::brandingEmail(),
            'button_bg_color' => static::brandingButtonBackgroundColor(),
            'button_bg_hover_color' => static::brandingButtonHoverBackgroundColor(),
            'button_text_color' => static::brandingButtonTextColor(),
        ];
    }

    public static function loyaltyPointsPerTenThousandVnd(): int
    {
        return max(
            0,
            static::integer('loyalty.points_per_ten_thousand_vnd', 1),
        );
    }

    public static function loyaltyReferralBonusReferrerPoints(): int
    {
        return max(
            0,
            static::integer('loyalty.referral_bonus_referrer_points', 100),
        );
    }

    public static function loyaltyReferralBonusRefereePoints(): int
    {
        return max(
            0,
            static::integer('loyalty.referral_bonus_referee_points', 50),
        );
    }

    public static function loyaltyReactivationBonusPoints(): int
    {
        return max(
            0,
            static::integer('loyalty.reactivation_bonus_points', 80),
        );
    }

    public static function loyaltyTierSilverRevenueThreshold(): float
    {
        return max(
            0.0,
            (float) static::get('loyalty.tier_silver_revenue_threshold', 5000000),
        );
    }

    public static function loyaltyTierGoldRevenueThreshold(): float
    {
        return max(
            static::loyaltyTierSilverRevenueThreshold(),
            (float) static::get('loyalty.tier_gold_revenue_threshold', 20000000),
        );
    }

    public static function loyaltyTierPlatinumRevenueThreshold(): float
    {
        return max(
            static::loyaltyTierGoldRevenueThreshold(),
            (float) static::get('loyalty.tier_platinum_revenue_threshold', 50000000),
        );
    }

    public static function loyaltyReactivationInactiveDays(): int
    {
        return max(
            30,
            static::integer('loyalty.reactivation_inactive_days', 90),
        );
    }

    public static function riskNoShowWindowDays(): int
    {
        return max(
            30,
            static::integer('risk.no_show_window_days', 90),
        );
    }

    public static function riskMediumThreshold(): float
    {
        return max(
            0.0,
            min(
                100.0,
                (float) static::get('risk.medium_threshold', 45.0),
            ),
        );
    }

    public static function riskHighThreshold(): float
    {
        return max(
            static::riskMediumThreshold(),
            min(
                100.0,
                (float) static::get('risk.high_threshold', 70.0),
            ),
        );
    }

    public static function riskAutoCreateHighRiskTicket(): bool
    {
        return static::boolean('risk.auto_create_high_risk_ticket', true);
    }

    /**
     * @return array<string, array{owner_role:string,threshold:string,runbook:string}>
     */
    public static function opsAlertRunbookMap(): array
    {
        $value = static::get(
            'ops.alert_runbook_map',
            config('care.ops_alert_runbook', []),
        );

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(static fn (mixed $item): bool => is_array($item))
            ->mapWithKeys(static function (array $item, string $key): array {
                $ownerRole = trim((string) ($item['owner_role'] ?? ''));
                $threshold = trim((string) ($item['threshold'] ?? ''));
                $runbook = trim((string) ($item['runbook'] ?? ''));

                return [
                    $key => [
                        'owner_role' => $ownerRole,
                        'threshold' => $threshold,
                        'runbook' => $runbook,
                    ],
                ];
            })
            ->all();
    }

    private static function normalizeHexColor(mixed $value, string $fallback): string
    {
        $candidate = strtoupper(trim((string) $value));

        if (preg_match('/^#[0-9A-F]{6}$/', $candidate) === 1) {
            return $candidate;
        }

        if (preg_match('/^#[0-9A-F]{3}$/', $candidate) === 1) {
            return sprintf(
                '#%1$s%1$s%2$s%2$s%3$s%3$s',
                $candidate[1],
                $candidate[2],
                $candidate[3],
            );
        }

        return strtoupper($fallback);
    }

    private static function darkenHexColor(string $hex, float $ratio): string
    {
        $normalizedHex = static::normalizeHexColor($hex, '#2F66F6');
        $ratio = max(0.0, min(1.0, $ratio));

        $red = hexdec(substr($normalizedHex, 1, 2));
        $green = hexdec(substr($normalizedHex, 3, 2));
        $blue = hexdec(substr($normalizedHex, 5, 2));

        $red = (int) max(0, min(255, round($red * (1 - $ratio))));
        $green = (int) max(0, min(255, round($green * (1 - $ratio))));
        $blue = (int) max(0, min(255, round($blue * (1 - $ratio))));

        return sprintf('#%02X%02X%02X', $red, $green, $blue);
    }

    /**
     * @param  array<string, string>  $default
     * @return array<string, string>
     */
    private static function catalogOptions(string $key, array $default): array
    {
        $configured = static::normalizeCatalogOptions(static::get($key, $default));

        if ($configured === []) {
            return $default;
        }

        return $configured;
    }

    /**
     * @return array<string, string>
     */
    private static function normalizeCatalogOptions(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        if (! is_array($value)) {
            return [];
        }

        $options = [];

        foreach ($value as $rawKey => $rawLabel) {
            $normalizedKey = null;
            $normalizedLabel = null;

            if (is_array($rawLabel)) {
                $normalizedKey = Arr::get($rawLabel, 'value', Arr::get($rawLabel, 'key'));
                $normalizedLabel = Arr::get($rawLabel, 'label', Arr::get($rawLabel, 'name'));
            } else {
                if (is_string($rawKey) && $rawKey !== '') {
                    $normalizedKey = $rawKey;
                } elseif (is_scalar($rawLabel)) {
                    $normalizedKey = (string) $rawLabel;
                }

                if (is_scalar($rawLabel)) {
                    $normalizedLabel = (string) $rawLabel;
                }
            }

            if (! is_scalar($normalizedKey) || ! is_scalar($normalizedLabel)) {
                continue;
            }

            $key = trim((string) $normalizedKey);
            $label = trim((string) $normalizedLabel);

            if ($key === '' || $label === '') {
                continue;
            }

            $options[$key] = $label;
        }

        return $options;
    }
}
