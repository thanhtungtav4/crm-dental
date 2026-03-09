<?php

namespace App\Models;

use App\Casts\NullableEncrypted;
use App\Casts\NullableEncryptedArray;
use App\Support\BranchAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebLeadEmailDelivery extends Model
{
    /** @use HasFactory<\Database\Factories\WebLeadEmailDeliveryFactory> */
    use HasFactory;

    public const RECIPIENT_TYPE_USER = 'user';

    public const RECIPIENT_TYPE_MAILBOX = 'mailbox';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENT = 'sent';

    public const STATUS_RETRYABLE = 'retryable';

    public const STATUS_DEAD = 'dead';

    public const STATUS_SKIPPED = 'skipped';

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
}
