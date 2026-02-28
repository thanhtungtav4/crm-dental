<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmrApiMutation extends Model
{
    /** @use HasFactory<\Database\Factories\EmrApiMutationFactory> */
    use HasFactory;

    public const TYPE_CLINICAL_NOTE_AMEND = 'clinical_note_amend';

    protected $fillable = [
        'request_id',
        'endpoint',
        'mutation_type',
        'payload_checksum',
        'patient_id',
        'clinical_note_id',
        'actor_id',
        'status_code',
        'response_payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'patient_id' => 'integer',
            'clinical_note_id' => 'integer',
            'actor_id' => 'integer',
            'status_code' => 'integer',
            'response_payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function clinicalNote(): BelongsTo
    {
        return $this->belongsTo(ClinicalNote::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
