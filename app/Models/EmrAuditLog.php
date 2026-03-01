<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class EmrAuditLog extends Model
{
    /** @use HasFactory<\Database\Factories\EmrAuditLogFactory> */
    use HasFactory;

    public const ENTITY_SYNC_EVENT = 'sync_event';

    public const ENTITY_CLINICAL_ORDER = 'clinical_order';

    public const ENTITY_CLINICAL_RESULT = 'clinical_result';

    public const ENTITY_CLINICAL_NOTE = 'clinical_note';

    public const ENTITY_PHI_ACCESS = 'phi_access';

    public const ACTION_CREATE = 'create';

    public const ACTION_UPDATE = 'update';

    public const ACTION_COMPLETE = 'complete';

    public const ACTION_CANCEL = 'cancel';

    public const ACTION_PUBLISH = 'publish';

    public const ACTION_SYNC = 'sync';

    public const ACTION_FAIL = 'fail';

    public const ACTION_DEDUPE = 'dedupe';

    public const ACTION_FINALIZE = 'finalize';

    public const ACTION_AMEND = 'amend';

    public const ACTION_READ = 'read';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'action',
        'patient_id',
        'visit_episode_id',
        'branch_id',
        'actor_id',
        'context',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'patient_id' => 'integer',
            'visit_episode_id' => 'integer',
            'branch_id' => 'integer',
            'actor_id' => 'integer',
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            $log->occurred_at = $log->occurred_at ?? now();
        });

        static::updating(function (): void {
            throw ValidationException::withMessages([
                'emr_audit_log' => 'EMR audit log là immutable, không cho phép cập nhật.',
            ]);
        });

        static::deleting(function (): void {
            throw ValidationException::withMessages([
                'emr_audit_log' => 'EMR audit log là immutable, không cho phép xóa.',
            ]);
        });
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

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function scopeForPatient(Builder $query, int $patientId): Builder
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeForEncounter(Builder $query, int $visitEpisodeId): Builder
    {
        return $query->where('visit_episode_id', $visitEpisodeId);
    }
}
