<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class EmrSyncEvent extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SYNCED = 'synced';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DEAD = 'dead';

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
        'payload' => 'array',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'next_retry_at' => 'datetime',
        'locked_at' => 'datetime',
        'processed_at' => 'datetime',
        'last_http_status' => 'integer',
    ];

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

    public function markProcessing(): void
    {
        $this->forceFill([
            'status' => self::STATUS_PROCESSING,
            'attempts' => (int) $this->attempts + 1,
            'locked_at' => now(),
            'last_error' => null,
        ])->save();
    }

    public function markSynced(?string $externalPatientId, ?int $httpStatus): void
    {
        $this->forceFill([
            'status' => self::STATUS_SYNCED,
            'processed_at' => now(),
            'next_retry_at' => null,
            'last_http_status' => $httpStatus,
            'external_patient_id' => $externalPatientId,
            'last_error' => null,
            'locked_at' => null,
        ])->save();
    }

    public function markFailure(?int $httpStatus, string $message): void
    {
        $shouldDeadLetter = (int) $this->attempts >= (int) $this->max_attempts;
        $nextRetryAt = $shouldDeadLetter
            ? null
            : $this->resolveNextRetryAt((int) $this->attempts);

        $this->forceFill([
            'status' => $shouldDeadLetter ? self::STATUS_DEAD : self::STATUS_FAILED,
            'next_retry_at' => $nextRetryAt,
            'last_http_status' => $httpStatus,
            'last_error' => mb_substr($message, 0, 1000),
            'locked_at' => null,
        ])->save();
    }

    protected function resolveNextRetryAt(int $attempt): Carbon
    {
        $delayMinutes = min(360, 2 ** max(1, $attempt));

        return now()->addMinutes($delayMinutes);
    }
}
