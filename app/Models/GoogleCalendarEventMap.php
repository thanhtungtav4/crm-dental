<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleCalendarEventMap extends Model
{
    protected $fillable = [
        'appointment_id',
        'branch_id',
        'calendar_id',
        'google_event_id',
        'payload_checksum',
        'last_event_id',
        'external_updated_at',
        'last_synced_at',
        'sync_meta',
    ];

    protected function casts(): array
    {
        return [
            'external_updated_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'sync_meta' => 'array',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function lastEvent(): BelongsTo
    {
        return $this->belongsTo(GoogleCalendarSyncEvent::class, 'last_event_id');
    }
}
