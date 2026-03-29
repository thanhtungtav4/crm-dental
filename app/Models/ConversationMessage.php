<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMessage extends Model
{
    /** @use HasFactory<\Database\Factories\ConversationMessageFactory> */
    use HasFactory;

    public const DIRECTION_INBOUND = 'inbound';

    public const DIRECTION_OUTBOUND = 'outbound';

    public const TYPE_TEXT = 'text';

    public const TYPE_SYSTEM = 'system';

    public const TYPE_UNSUPPORTED = 'unsupported';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_IGNORED = 'ignored';

    protected $fillable = [
        'conversation_id',
        'direction',
        'message_type',
        'provider_message_id',
        'source_event_fingerprint',
        'body',
        'payload_summary',
        'sent_by_user_id',
        'status',
        'attempts',
        'next_retry_at',
        'processing_token',
        'processed_at',
        'provider_status_code',
        'last_error',
        'message_at',
    ];

    protected function casts(): array
    {
        return [
            'conversation_id' => 'integer',
            'sent_by_user_id' => 'integer',
            'payload_summary' => 'array',
            'attempts' => 'integer',
            'next_retry_at' => 'datetime',
            'processed_at' => 'datetime',
            'message_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function isInbound(): bool
    {
        return $this->direction === self::DIRECTION_INBOUND;
    }

    public function isOutbound(): bool
    {
        return $this->direction === self::DIRECTION_OUTBOUND;
    }

    /**
     * @param  array{
     *     provider_message_id?:string|null,
     *     provider_status_code?:string|null,
     *     status?:int|null,
     *     response?:array<string, mixed>|null
     * }  $result
     */
    public function markSent(array $result = []): void
    {
        $this->forceFill([
            'status' => self::STATUS_SENT,
            'provider_message_id' => $result['provider_message_id'] ?? $this->provider_message_id,
            'provider_status_code' => $result['provider_status_code'] ?? $this->provider_status_code,
            'payload_summary' => $result['response'] ?? $this->payload_summary,
            'processing_token' => null,
            'processed_at' => now(),
            'next_retry_at' => null,
            'last_error' => null,
        ])->save();
    }

    /**
     * @param  array{
     *     provider_status_code?:string|null,
     *     error?:string|null
     * }  $result
     */
    public function markFailed(array $result = []): void
    {
        $delayMinutes = min(60, max(5, ((int) $this->attempts) * 5));

        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'provider_status_code' => $result['provider_status_code'] ?? $this->provider_status_code,
            'processing_token' => null,
            'processed_at' => now(),
            'next_retry_at' => now()->addMinutes($delayMinutes),
            'last_error' => $result['error'] ?? 'Channel send failed.',
        ])->save();
    }

    public function markIgnored(?string $error = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_IGNORED,
            'processing_token' => null,
            'processed_at' => now(),
            'next_retry_at' => null,
            'last_error' => $error,
        ])->save();
    }
}
