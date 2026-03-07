<?php

namespace App\Models;

use App\Casts\NullableEncryptedArray;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZnsAutomationLog extends Model
{
    protected $fillable = [
        'zns_automation_event_id',
        'attempt',
        'status',
        'http_status',
        'request_payload',
        'response_payload',
        'error_message',
        'attempted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'zns_automation_event_id' => 'integer',
            'attempt' => 'integer',
            'http_status' => 'integer',
            'request_payload' => NullableEncryptedArray::class,
            'response_payload' => NullableEncryptedArray::class,
            'attempted_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ZnsAutomationEvent::class, 'zns_automation_event_id');
    }
}
