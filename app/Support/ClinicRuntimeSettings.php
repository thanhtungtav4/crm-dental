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
}
