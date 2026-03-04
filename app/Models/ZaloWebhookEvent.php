<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZaloWebhookEvent extends Model
{
    protected $fillable = [
        'event_fingerprint',
        'event_name',
        'event_id',
        'oa_id',
        'payload',
        'received_at',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
