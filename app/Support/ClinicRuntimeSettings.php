<?php

namespace App\Support;

use App\Models\ClinicSetting;

class ClinicRuntimeSettings
{
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
}
