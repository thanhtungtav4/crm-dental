<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalNoteRevision extends Model
{
    /** @use HasFactory<\Database\Factories\ClinicalNoteRevisionFactory> */
    use HasFactory;

    public const OPERATION_CREATE = 'create';

    public const OPERATION_UPDATE = 'update';

    public const OPERATION_AMEND = 'amend';

    public $timestamps = false;

    protected $fillable = [
        'clinical_note_id',
        'patient_id',
        'visit_episode_id',
        'branch_id',
        'version',
        'operation',
        'changed_by',
        'previous_payload',
        'current_payload',
        'changed_fields',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'clinical_note_id' => 'integer',
            'patient_id' => 'integer',
            'visit_episode_id' => 'integer',
            'branch_id' => 'integer',
            'version' => 'integer',
            'changed_by' => 'integer',
            'previous_payload' => 'array',
            'current_payload' => 'array',
            'changed_fields' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function clinicalNote(): BelongsTo
    {
        return $this->belongsTo(ClinicalNote::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class, 'visit_episode_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
