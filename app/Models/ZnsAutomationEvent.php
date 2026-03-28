<?php

namespace App\Models;

use App\Casts\NullableEncryptedArray;
use App\Casts\NullableEncryptedWithPlaintextFallback;
use App\Support\IdentitySearchHash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ZnsAutomationEvent extends Model
{
    protected static bool $allowsManagedWorkflowMutation = false;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DEAD = 'dead';

    public const EVENT_LEAD_WELCOME = 'lead_welcome';

    public const EVENT_APPOINTMENT_REMINDER = 'appointment_reminder';

    public const EVENT_BIRTHDAY_GREETING = 'birthday_greeting';

    public const STALE_PROCESSING_TTL_MINUTES = 15;

    protected const STATUS_TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_PENDING, self::STATUS_PROCESSING, self::STATUS_DEAD],
        self::STATUS_PROCESSING => [self::STATUS_PROCESSING, self::STATUS_SENT, self::STATUS_FAILED, self::STATUS_DEAD],
        self::STATUS_SENT => [self::STATUS_SENT, self::STATUS_PENDING],
        self::STATUS_FAILED => [self::STATUS_FAILED, self::STATUS_PENDING, self::STATUS_PROCESSING, self::STATUS_DEAD],
        self::STATUS_DEAD => [self::STATUS_DEAD, self::STATUS_PENDING],
    ];

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
        'phone_search_hash',
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
            'phone' => NullableEncryptedWithPlaintextFallback::class,
            'normalized_phone' => NullableEncryptedWithPlaintextFallback::class,
            'payload' => NullableEncryptedArray::class,
            'provider_response' => NullableEncryptedArray::class,
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'next_retry_at' => 'datetime',
            'locked_at' => 'datetime',
            'processed_at' => 'datetime',
            'last_http_status' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $event): void {
            $event->status = strtolower(trim((string) ($event->status ?: self::STATUS_PENDING)));
            $event->phone_search_hash = self::phoneSearchHash(
                $event->normalized_phone ?: $event->phone,
            );

            if (! $event->exists || ! $event->isDirty('status')) {
                return;
            }

            if (! static::$allowsManagedWorkflowMutation) {
                throw ValidationException::withMessages([
                    'status' => 'Trang thai su kien ZNS chi duoc thay doi qua workflow noi bo cua ZnsAutomationEvent.',
                ]);
            }

            $fromStatus = strtolower(trim((string) ($event->getOriginal('status') ?: self::STATUS_PENDING)));

            if (! static::canTransition($fromStatus, $event->status)) {
                throw ValidationException::withMessages([
                    'status' => 'ZNS_AUTOMATION_EVENT_STATE_INVALID: Không thể chuyển trạng thái sự kiện ZNS.',
                ]);
            }
        });
    }

    public static function phoneSearchHash(?string $phone): ?string
    {
        return IdentitySearchHash::phone('zns', $phone);
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
        static::runWithinManagedWorkflow(function () use ($processingToken): void {
            $this->forceFill([
                'status' => self::STATUS_PROCESSING,
                'attempts' => (int) $this->attempts + 1,
                'locked_at' => now(),
                'processing_token' => $processingToken,
                'last_error' => null,
            ])->save();
        });
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
        static::runWithinManagedWorkflow(function () use ($providerMessageId, $providerStatusCode, $httpStatus, $providerResponse): void {
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
        });
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

        static::runWithinManagedWorkflow(function () use ($shouldDeadLetter, $httpStatus, $message, $providerStatusCode, $providerResponse): void {
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
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function resetForReplay(array $attributes): void
    {
        static::runWithinManagedWorkflow(function () use ($attributes): void {
            $this->forceFill(array_merge($attributes, [
                'status' => self::STATUS_PENDING,
                'attempts' => 0,
                'next_retry_at' => now(),
                'locked_at' => null,
                'processing_token' => null,
                'processed_at' => null,
                'last_http_status' => null,
                'last_error' => null,
                'provider_message_id' => null,
                'provider_status_code' => null,
                'provider_response' => null,
            ]))->save();
        });
    }

    public function markSuperseded(string $message): void
    {
        static::runWithinManagedWorkflow(function () use ($message): void {
            $this->forceFill([
                'status' => self::STATUS_DEAD,
                'next_retry_at' => null,
                'locked_at' => null,
                'processing_token' => null,
                'processed_at' => now(),
                'last_error' => $message,
            ])->save();
        });
    }

    public static function reclaimStaleProcessing(int $ttlMinutes = self::STALE_PROCESSING_TTL_MINUTES): int
    {
        $ttlMinutes = max(1, $ttlMinutes);
        $lockedBefore = now()->subMinutes($ttlMinutes);
        $eventIds = static::query()
            ->where('status', self::STATUS_PROCESSING)
            ->whereNotNull('locked_at')
            ->where('locked_at', '<=', $lockedBefore)
            ->pluck('id');
        $reclaimed = 0;

        foreach ($eventIds as $eventId) {
            $wasReclaimed = DB::transaction(function () use ($eventId, $lockedBefore): bool {
                $event = static::query()
                    ->lockForUpdate()
                    ->find($eventId);

                if (
                    ! $event instanceof self
                    || $event->status !== self::STATUS_PROCESSING
                    || ! $event->locked_at instanceof Carbon
                    || $event->locked_at->gt($lockedBefore)
                ) {
                    return false;
                }

                static::runWithinManagedWorkflow(function () use ($event): void {
                    $event->forceFill([
                        'status' => (int) $event->attempts >= (int) $event->max_attempts
                            ? self::STATUS_DEAD
                            : self::STATUS_FAILED,
                        'next_retry_at' => (int) $event->attempts >= (int) $event->max_attempts ? null : now(),
                        'locked_at' => null,
                        'processing_token' => null,
                        'last_error' => (int) $event->attempts >= (int) $event->max_attempts
                            ? 'Stale processing lock reclaimed after max attempts reached.'
                            : 'Stale processing lock reclaimed for retry.',
                    ])->save();
                });

                return true;
            }, 3);

            if ($wasReclaimed) {
                $reclaimed++;
            }
        }

        return $reclaimed;
    }

    public static function runWithinManagedWorkflow(callable $callback): mixed
    {
        $previousState = static::$allowsManagedWorkflowMutation;
        static::$allowsManagedWorkflowMutation = true;

        try {
            return $callback();
        } finally {
            static::$allowsManagedWorkflowMutation = $previousState;
        }
    }

    public static function canTransition(string $fromStatus, string $toStatus): bool
    {
        return in_array($toStatus, static::STATUS_TRANSITIONS[$fromStatus] ?? [], true);
    }

    protected function resolveNextRetryAt(int $attempt): Carbon
    {
        $delayMinutes = min(360, 2 ** max(1, $attempt));

        return now()->addMinutes($delayMinutes);
    }
}
