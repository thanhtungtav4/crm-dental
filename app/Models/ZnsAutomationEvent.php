<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ZnsAutomationEvent extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DEAD = 'dead';

    public const EVENT_LEAD_WELCOME = 'lead_welcome';

    public const EVENT_APPOINTMENT_REMINDER = 'appointment_reminder';

    public const EVENT_BIRTHDAY_GREETING = 'birthday_greeting';

    public const STALE_PROCESSING_TTL_MINUTES = 15;

    protected $fillable = [
        'event_key',
        'event_type',
        'template_key',
        'template_id_snapshot',
        'appointment_id',
        'patient_id',
        'customer_id',
        'branch_id',
        'phone',
        'normalized_phone',
        'payload',
        'payload_checksum',
        'status',
        'attempts',
        'max_attempts',
        'next_retry_at',
        'locked_at',
        'processing_token',
        'processed_at',
        'last_http_status',
        'last_error',
        'provider_message_id',
        'provider_status_code',
        'provider_response',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'appointment_id' => 'integer',
            'patient_id' => 'integer',
            'customer_id' => 'integer',
            'branch_id' => 'integer',
            'payload' => 'array',
            'provider_response' => 'array',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'next_retry_at' => 'datetime',
            'locked_at' => 'datetime',
            'processed_at' => 'datetime',
            'last_http_status' => 'integer',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ZnsAutomationLog::class);
    }

    public function scopeReady(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_FAILED])
            ->where(function (Builder $retryQuery): void {
                $retryQuery
                    ->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            });
    }

    public function markProcessing(string $processingToken): void
    {
        $this->forceFill([
            'status' => self::STATUS_PROCESSING,
            'attempts' => (int) $this->attempts + 1,
            'locked_at' => now(),
            'processing_token' => $processingToken,
            'last_error' => null,
        ])->save();
    }

    /**
     * @param  array<string, mixed>|null  $providerResponse
     */
    public function markSent(
        ?string $providerMessageId,
        ?string $providerStatusCode,
        ?int $httpStatus,
        ?array $providerResponse,
    ): void {
        $this->forceFill([
            'status' => self::STATUS_SENT,
            'processed_at' => now(),
            'next_retry_at' => null,
            'locked_at' => null,
            'processing_token' => null,
            'last_http_status' => $httpStatus,
            'last_error' => null,
            'provider_message_id' => $providerMessageId,
            'provider_status_code' => $providerStatusCode,
            'provider_response' => $providerResponse,
        ])->save();
    }

    /**
     * @param  array<string, mixed>|null  $providerResponse
     */
    public function markFailure(
        ?int $httpStatus,
        string $message,
        bool $retryable = true,
        ?string $providerStatusCode = null,
        ?array $providerResponse = null,
    ): void {
        $shouldDeadLetter = ! $retryable || (int) $this->attempts >= (int) $this->max_attempts;

        $this->forceFill([
            'status' => $shouldDeadLetter ? self::STATUS_DEAD : self::STATUS_FAILED,
            'next_retry_at' => $shouldDeadLetter ? null : $this->resolveNextRetryAt((int) $this->attempts),
            'locked_at' => null,
            'processing_token' => null,
            'last_http_status' => $httpStatus,
            'last_error' => mb_substr($message, 0, 1000),
            'provider_status_code' => $providerStatusCode,
            'provider_response' => $providerResponse,
        ])->save();
    }

    public static function reclaimStaleProcessing(int $ttlMinutes = self::STALE_PROCESSING_TTL_MINUTES): int
    {
        $ttlMinutes = max(1, $ttlMinutes);
        $lockedBefore = now()->subMinutes($ttlMinutes);
        $now = now();
        $staleQuery = static::query()
            ->where('status', self::STATUS_PROCESSING)
            ->whereNotNull('locked_at')
            ->where('locked_at', '<=', $lockedBefore);

        $movedToDead = (clone $staleQuery)
            ->whereColumn('attempts', '>=', 'max_attempts')
            ->update([
                'status' => self::STATUS_DEAD,
                'next_retry_at' => null,
                'locked_at' => null,
                'processing_token' => null,
                'last_error' => 'Stale processing lock reclaimed after max attempts reached.',
                'updated_at' => $now,
            ]);

        $movedToFailed = (clone $staleQuery)
            ->whereColumn('attempts', '<', 'max_attempts')
            ->update([
                'status' => self::STATUS_FAILED,
                'next_retry_at' => $now,
                'locked_at' => null,
                'processing_token' => null,
                'last_error' => 'Stale processing lock reclaimed for retry.',
                'updated_at' => $now,
            ]);

        return $movedToDead + $movedToFailed;
    }

    protected function resolveNextRetryAt(int $attempt): Carbon
    {
        $delayMinutes = min(360, 2 ** max(1, $attempt));

        return now()->addMinutes($delayMinutes);
    }
}
