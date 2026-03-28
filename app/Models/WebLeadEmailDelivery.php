<?php

namespace App\Models;

use App\Casts\NullableEncrypted;
use App\Casts\NullableEncryptedArray;
use App\Support\BranchAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class WebLeadEmailDelivery extends Model
{
    /** @use HasFactory<\Database\Factories\WebLeadEmailDeliveryFactory> */
    use HasFactory;

    protected static bool $allowsManagedWorkflowMutation = false;

    public const RECIPIENT_TYPE_USER = 'user';

    public const RECIPIENT_TYPE_MAILBOX = 'mailbox';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENT = 'sent';

    public const STATUS_RETRYABLE = 'retryable';

    public const STATUS_DEAD = 'dead';

    public const STATUS_SKIPPED = 'skipped';

    protected const STATUS_TRANSITIONS = [
        self::STATUS_QUEUED => [
            self::STATUS_QUEUED,
            self::STATUS_PROCESSING,
            self::STATUS_SKIPPED,
            self::STATUS_DEAD,
        ],
        self::STATUS_PROCESSING => [
            self::STATUS_PROCESSING,
            self::STATUS_SENT,
            self::STATUS_RETRYABLE,
            self::STATUS_DEAD,
            self::STATUS_SKIPPED,
        ],
        self::STATUS_RETRYABLE => [
            self::STATUS_RETRYABLE,
            self::STATUS_PROCESSING,
            self::STATUS_QUEUED,
            self::STATUS_DEAD,
            self::STATUS_SKIPPED,
        ],
        self::STATUS_SENT => [
            self::STATUS_SENT,
            self::STATUS_QUEUED,
        ],
        self::STATUS_DEAD => [
            self::STATUS_DEAD,
            self::STATUS_QUEUED,
        ],
        self::STATUS_SKIPPED => [
            self::STATUS_SKIPPED,
            self::STATUS_QUEUED,
        ],
    ];

    protected $fillable = [
        'web_lead_ingestion_id',
        'customer_id',
        'branch_id',
        'recipient_user_id',
        'dedupe_key',
        'recipient_type',
        'recipient_email',
        'recipient_name',
        'status',
        'processing_token',
        'locked_at',
        'attempt_count',
        'manual_resend_count',
        'last_attempt_at',
        'next_retry_at',
        'sent_at',
        'transport_message_id',
        'last_error_message',
        'payload',
        'mailer_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'web_lead_ingestion_id' => 'integer',
            'customer_id' => 'integer',
            'branch_id' => 'integer',
            'recipient_user_id' => 'integer',
            'locked_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'sent_at' => 'datetime',
            'attempt_count' => 'integer',
            'manual_resend_count' => 'integer',
            'recipient_email' => NullableEncrypted::class,
            'payload' => NullableEncryptedArray::class,
            'mailer_snapshot' => NullableEncryptedArray::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $delivery): void {
            $delivery->status = static::normalizeStatus($delivery->status) ?? self::STATUS_QUEUED;

            if (! $delivery->exists || ! $delivery->isDirty('status')) {
                return;
            }

            if (! static::$allowsManagedWorkflowMutation) {
                throw ValidationException::withMessages([
                    'status' => 'Trang thai gui mail web lead chi duoc thay doi qua workflow noi bo cua WebLeadEmailDelivery.',
                ]);
            }

            $fromStatus = static::normalizeStatus((string) ($delivery->getOriginal('status') ?: self::STATUS_QUEUED))
                ?? self::STATUS_QUEUED;

            if (! static::canTransition($fromStatus, $delivery->status)) {
                throw ValidationException::withMessages([
                    'status' => 'WEB_LEAD_EMAIL_DELIVERY_STATE_INVALID: Không thể chuyển trạng thái mail web lead.',
                ]);
            }
        });
    }

    public static function canAccessModule(User $authUser): bool
    {
        return $authUser->hasRole('Admin') || $authUser->hasRole('Manager');
    }

    public function scopeVisibleTo(Builder $query, ?User $authUser): Builder
    {
        if (! $authUser instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($authUser->hasRole('Admin')) {
            return $query;
        }

        if (! $authUser->can('ViewAny:WebLeadEmailDelivery')) {
            return $query->whereRaw('1 = 0');
        }

        $branchIds = BranchAccess::accessibleBranchIds($authUser);

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('branch_id', $branchIds);
    }

    public function isVisibleTo(User $authUser): bool
    {
        if ($authUser->hasRole('Admin')) {
            return true;
        }

        if (! $authUser->can('View:WebLeadEmailDelivery')) {
            return false;
        }

        if ($this->branch_id === null) {
            return false;
        }

        return $authUser->canAccessBranch((int) $this->branch_id);
    }

    public function webLeadIngestion(): BelongsTo
    {
        return $this->belongsTo(WebLeadIngestion::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function resolvedRecipientLabel(): string
    {
        return $this->recipient_name
            ?: $this->recipientUser?->name
            ?: $this->recipient_email
            ?: 'Người nhận nội bộ';
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_SENT, self::STATUS_DEAD, self::STATUS_SKIPPED], true);
    }

    public function markProcessing(?string $processingToken = null): void
    {
        static::runWithinManagedWorkflow(function () use ($processingToken): void {
            $this->forceFill([
                'status' => self::STATUS_PROCESSING,
                'processing_token' => $processingToken,
                'locked_at' => now(),
                'attempt_count' => (int) $this->attempt_count + 1,
                'last_attempt_at' => now(),
                'last_error_message' => null,
            ])->save();
        });
    }

    public function markSent(?string $transportMessageId = null): void
    {
        static::runWithinManagedWorkflow(function () use ($transportMessageId): void {
            $this->forceFill([
                'status' => self::STATUS_SENT,
                'processing_token' => null,
                'locked_at' => null,
                'next_retry_at' => null,
                'sent_at' => now(),
                'transport_message_id' => $transportMessageId,
                'last_error_message' => null,
            ])->save();
        });
    }

    public function markFailure(string $message, bool $terminal, int $delaySeconds): void
    {
        static::runWithinManagedWorkflow(function () use ($message, $terminal, $delaySeconds): void {
            $this->forceFill([
                'status' => $terminal ? self::STATUS_DEAD : self::STATUS_RETRYABLE,
                'processing_token' => null,
                'locked_at' => null,
                'next_retry_at' => $terminal ? null : now()->addSeconds(max(0, $delaySeconds)),
                'last_error_message' => mb_substr($message, 0, 2000),
            ])->save();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function resetForReplay(array $attributes = []): void
    {
        static::runWithinManagedWorkflow(function () use ($attributes): void {
            $this->forceFill(array_merge([
                'status' => self::STATUS_QUEUED,
                'processing_token' => null,
                'locked_at' => null,
                'next_retry_at' => null,
                'transport_message_id' => null,
                'last_error_message' => null,
                'sent_at' => null,
            ], $attributes))->save();
        });
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
