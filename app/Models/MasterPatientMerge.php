<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterPatientMerge extends Model
{
    use HasFactory;

    public const STATUS_APPLIED = 'applied';

    public const STATUS_ROLLED_BACK = 'rolled_back';

    protected $fillable = [
        'canonical_patient_id',
        'merged_patient_id',
        'duplicate_case_id',
        'status',
        'merge_reason',
        'canonical_before',
        'canonical_after',
        'merged_before',
        'merged_after',
        'rewired_record_ids',
        'rewire_summary',
        'metadata',
        'merged_by',
        'merged_at',
        'rolled_back_by',
        'rolled_back_at',
        'rollback_note',
    ];

    protected function casts(): array
    {
        return [
            'canonical_before' => 'array',
            'canonical_after' => 'array',
            'merged_before' => 'array',
            'merged_after' => 'array',
            'rewired_record_ids' => 'array',
            'rewire_summary' => 'array',
            'metadata' => 'array',
            'merged_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }

    public function canonicalPatient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'canonical_patient_id');
    }

    public function mergedPatient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'merged_patient_id');
    }

    public function duplicateCase(): BelongsTo
    {
        return $this->belongsTo(MasterPatientDuplicate::class, 'duplicate_case_id');
    }

    public function mergedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merged_by');
    }

    public function rolledBackByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rolled_back_by');
    }
}
