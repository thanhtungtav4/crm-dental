<?php

namespace App\Models;

use App\Casts\NullableEncryptedArray;
use App\Casts\NullableEncryptedWithPlaintextFallback;
use App\Services\ZnsPayloadSanitizer;
use App\Support\IdentitySearchHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ZnsCampaignDelivery extends Model
{
    protected static bool $allowsManagedWorkflowMutation = false;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected const STATUS_TRANSITIONS = [
        self::STATUS_QUEUED => [
            self::STATUS_QUEUED,
            self::STATUS_SENT,
            self::STATUS_FAILED,
            self::STATUS_SKIPPED,
        ],
        self::STATUS_FAILED => [
            self::STATUS_FAILED,
            self::STATUS_SENT,
            self::STATUS_SKIPPED,
        ],
        self::STATUS_SENT => [
            self::STATUS_SENT,
        ],
        self::STATUS_SKIPPED => [
            self::STATUS_SKIPPED,
        ],
    ];

    protected $fillable = [
        'zns_campaign_id',
        'patient_id',
        'customer_id',
        'branch_id',
        'phone',
        'normalized_phone',
        'phone_search_hash',
        'idempotency_key',
        'status',
        'processing_token',
        'locked_at',
        'attempt_count',
        'provider_message_id',
        'provider_status_code',
        'provider_response',
        'error_message',
        'sent_at',
        'next_retry_at',
        'payload',
        'template_key_snapshot',
        'template_id_snapshot',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'zns_campaign_id' => 'integer',
            'patient_id' => 'integer',
            'customer_id' => 'integer',
            'branch_id' => 'integer',
            'attempt_count' => 'integer',
            'sent_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'locked_at' => 'datetime',
            'phone' => NullableEncryptedWithPlaintextFallback::class,
            'normalized_phone' => NullableEncryptedWithPlaintextFallback::class,
            'payload' => NullableEncryptedArray::class,
            'provider_response' => NullableEncryptedArray::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $delivery): void {
            $delivery->status = static::normalizeStatus($delivery->status) ?? self::STATUS_QUEUED;
            $delivery->phone_search_hash = self::phoneSearchHash(
                $delivery->normalized_phone ?: $delivery->phone,
            );

            if (! $delivery->exists || ! $delivery->isDirty('status')) {
                return;
            }

            if (! static::$allowsManagedWorkflowMutation) {
                throw ValidationException::withMessages([
                    'status' => 'Trang thai delivery ZNS chi duoc thay doi qua workflow noi bo cua ZnsCampaignDelivery.',
                ]);
            }

            $fromStatus = static::normalizeStatus((string) ($delivery->getOriginal('status') ?: self::STATUS_QUEUED))
                ?? self::STATUS_QUEUED;

            if (! static::canTransition($fromStatus, $delivery->status)) {
                throw ValidationException::withMessages([
                    'status' => 'ZNS_CAMPAIGN_DELIVERY_STATE_INVALID: Không thể chuyển trạng thái delivery ZNS.',
                ]);
            }
        });
    }

    public static function phoneSearchHash(?string $phone): ?string
    {
        return IdentitySearchHash::phone('zns', $phone);
    }

    public function maskedPhone(): ?string
    {
        return app(ZnsPayloadSanitizer::class)->maskPhone($this->normalized_phone ?: $this->phone);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ZnsCampaign::class, 'zns_campaign_id');
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

    public function claimForProcessing(string $processingToken): self
    {
        $this->forceFill([
            'processing_token' => $processingToken,
            'locked_at' => now(),
            'attempt_count' => (int) $this->attempt_count + 1,
        ])->save();

        return $this;
    }

    /**
     * @param  array<string, mixed>|null  $providerResponse
     * @param  array<string, mixed>|null  $providerRequestSummary
     */
    public function markSent(
        ?string $providerMessageId,
        string|int|null $providerStatusCode,
        ?array $providerResponse,
        ?array $providerRequestSummary = null,
    ): self {
        static::runWithinManagedWorkflow(function () use (
            $providerMessageId,
            $providerStatusCode,
            $providerResponse,
            $providerRequestSummary,
        ): void {
            $this->forceFill([
                'status' => self::STATUS_SENT,
                'provider_message_id' => $providerMessageId,
                'provider_status_code' => $providerStatusCode,
                'provider_response' => $providerResponse,
                'error_message' => null,
                'next_retry_at' => null,
                'sent_at' => now(),
                'processing_token' => null,
                'locked_at' => null,
                'payload' => $providerRequestSummary === null
                    ? $this->payload
                    : array_merge(is_array($this->payload) ? $this->payload : [], [
                        'provider_request_summary' => $providerRequestSummary,
                    ]),
            ])->save();
        });

        return $this;
    }

    /**
     * @param  array<string, mixed>|null  $providerResponse
     * @param  array<string, mixed>|null  $providerRequestSummary
     */
    public function markFailure(
        string $message,
        string|int|null $providerStatusCode,
        ?array $providerResponse,
        mixed $nextRetryAt,
        ?array $providerRequestSummary = null,
    ): self {
        static::runWithinManagedWorkflow(function () use (
            $message,
            $providerStatusCode,
            $providerResponse,
            $nextRetryAt,
            $providerRequestSummary,
        ): void {
            $this->forceFill([
                'status' => self::STATUS_FAILED,
                'provider_message_id' => null,
                'provider_status_code' => $providerStatusCode,
                'provider_response' => $providerResponse,
                'error_message' => $message,
                'next_retry_at' => $nextRetryAt,
                'processing_token' => null,
                'locked_at' => null,
                'payload' => $providerRequestSummary === null
                    ? $this->payload
                    : array_merge(is_array($this->payload) ? $this->payload : [], [
                        'provider_request_summary' => $providerRequestSummary,
                    ]),
            ])->save();
        });

        return $this;
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

    public static function normalizeStatus(?string $status): ?string
    {
        $normalized = strtolower(trim((string) $status));

        return array_key_exists($normalized, static::STATUS_TRANSITIONS) ? $normalized : null;
    }
}
