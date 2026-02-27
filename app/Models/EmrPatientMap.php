<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmrPatientMap extends Model
{
    protected $fillable = [
        'patient_id',
        'emr_patient_id',
        'payload_checksum',
        'last_event_id',
        'last_synced_at',
        'sync_meta',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'sync_meta' => 'array',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function lastEvent(): BelongsTo
    {
        return $this->belongsTo(EmrSyncEvent::class, 'last_event_id');
    }
}
