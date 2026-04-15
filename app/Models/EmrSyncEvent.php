<?php

namespace App\Models;

use App\Casts\NullableEncryptedArray;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmrSyncEvent extends Model
{
    protected static bool $allowsManagedWorkflowMutation = false;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SYNCED = 'synced';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DEAD = 'dead';

    public const STALE_PROCESSING_TTL_MINUTES = 15;

    protected const STATUS_TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_PENDING, self::STATUS_PROCESSING],
        self::STATUS_PROCESSING => [self::STATUS_PROCESSING, self::STATUS_SYNCED, self::STATUS_FAILED, self::STATUS_DEAD],
        self::STATUS_SYNCED => [self::STATUS_SYNCED, self::STATUS_PENDING],
        self::STATUS_FAILED => [self::STATUS_FAILED, self::STATUS_PENDING, self::STATUS_PROCESSING, self::STATUS_DEAD],
        self::STATUS_DEAD => [self::STATUS_DEAD, self::STATUS_PENDING],
    ];

    protected $fillable = [
        'event_key',
        'patient_id',
        'branch_id',
        'event_type',
        'payload',
        'payload_checksum',
        'status',
        'attempts',
        'max_attempts',
        'next_retry_at',
        'locked_at',
        'processed_at',
        'last_http_status',
        'last_error',
        'external_patient_id',
    ];

    protected $casts = [
        'payload' => NullableEncryptedArray::class,
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'next_retry_at' => 'datetime',
        'locked_at' => 'datetime',
        'processed_at' => 'datetime',
        'last_http_status' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $event): void {
            $event->status = strtolower(trim((string) ($event->status ?: self::STATUS_PENDING)));

            if (! $event->exists || ! $event->isDirty('status')) {
                return;
            }

            if (! static::$allowsManagedWorkflowMutation) {
                throw ValidationException::withMessages([
                    'status' => 'Trang thai sync EMR chi duoc thay doi qua workflow noi bo cua EmrSyncEvent.',
                ]);
            }

            $fromStatus = strtolower(trim((string) ($event->getOriginal('status') ?: self::STATUS_PENDING)));

            if (! static::canTransition($fromStatus, $event->status)) {
                throw ValidationException::withMessages([
                    'status' => 'EMR_SYNC_EVENT_STATE_INVALID: Không thể chuyển trạng thái sync EMR.',
                ]);
            }
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(EmrSyncLog::class);
    }

    public function patientMap(): HasOne
    {
        return $this->hasOne(EmrPatientMap::class, 'last_event_id');
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

    public function markProcessing(): self
    {
        static::runWithinManagedWorkflow(function (): void {
            $this->forceFill([
                'status' => self::STATUS_PROCESSING,
                'attempts' => (int) $this->attempts + 1,
                'locked_at' => now(),
                'last_error' => null,
            ])->save();
        });

        return $this;
    }

    public function markSynced(?string $externalPatientId, ?int $httpStatus): self
    {
        static::runWithinManagedWorkflow(function () use ($externalPatientId, $httpStatus): void {
            $this->forceFill([
                'status' => self::STATUS_SYNCED,
                'processed_at' => now(),
                'next_retry_at' => null,
                'last_http_status' => $httpStatus,
                'external_patient_id' => $externalPatientId,
                'last_error' => null,
                'locked_at' => null,
            ])->save();
        });

        return $this;
    }

    public function markFailure(?int $httpStatus, string $message): self
    {
        $shouldDeadLetter = (int) $this->attempts >= (int) $this->max_attempts;
        $nextRetryAt = $shouldDeadLetter
            ? null
            : $this->resolveNextRetryAt((int) $this->attempts);

        static::runWithinManagedWorkflow(function () use ($shouldDeadLetter, $nextRetryAt, $httpStatus, $message): void {
            $this->forceFill([
                'status' => $shouldDeadLetter ? self::STATUS_DEAD : self::STATUS_FAILED,
                'next_retry_at' => $nextRetryAt,
                'last_http_status' => $httpStatus,
                'last_error' => mb_substr($message, 0, 1000),
                'locked_at' => null,
            ])->save();
        });

        return $this;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function resetForReplay(array $attributes): self
    {
        static::runWithinManagedWorkflow(function () use ($attributes): void {
            $this->forceFill(array_merge($attributes, [
                'status' => self::STATUS_PENDING,
                'attempts' => 0,
                'next_retry_at' => now(),
                'locked_at' => null,
                'processed_at' => null,
                'last_http_status' => null,
                'last_error' => null,
                'external_patient_id' => null,
            ]))->save();
        });

        return $this;
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
