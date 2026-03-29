<?php

namespace App\Models;

use App\Casts\NullableEncryptedArray;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookWebhookEvent extends Model
{
    protected $fillable = [
        'event_fingerprint',
        'event_name',
        'page_id',
        'sender_id',
        'recipient_id',
        'payload',
        'received_at',
        'processed_at',
        'normalize_status',
        'conversation_id',
        'message_id',
        'normalized_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'payload' => NullableEncryptedArray::class,
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'conversation_id' => 'integer',
            'message_id' => 'integer',
            'normalized_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'message_id');
    }
}
