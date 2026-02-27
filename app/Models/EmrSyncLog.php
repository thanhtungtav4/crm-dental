<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmrSyncLog extends Model
{
    protected $fillable = [
        'emr_sync_event_id',
        'attempt',
        'status',
        'http_status',
        'request_payload',
        'response_payload',
        'error_message',
        'attempted_at',
    ];

    protected $casts = [
        'attempt' => 'integer',
        'http_status' => 'integer',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'attempted_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(EmrSyncEvent::class, 'emr_sync_event_id');
    }
}
