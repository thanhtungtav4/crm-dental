<?php

namespace App\Models;

use App\Services\ClinicalEvidenceGateService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TreatmentSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'treatment_plan_id',
        'exam_session_id',
        'plan_item_id',
        'doctor_id',
        'assistant_id',
        'start_at',
        'end_at',
        'performed_at',
        'diagnosis',
        'procedure',
        'images',
        'notes',
        'status',
        'evidence_override_reason',
        'evidence_override_by',
        'evidence_override_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'exam_session_id' => 'integer',
        'images' => 'array',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'performed_at' => 'datetime',
        'evidence_override_by' => 'integer',
        'evidence_override_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $session): void {
            $normalizedStatus = strtolower(trim((string) ($session->status ?: 'scheduled')));
            $session->status = $normalizedStatus !== '' ? $normalizedStatus : 'scheduled';

            $isCompleting = in_array($session->status, ['done', 'completed'], true)
                && (! $session->exists || $session->isDirty('status'));

            if (in_array($session->status, ['done', 'completed'], true) === false && $session->isDirty('status')) {
                $session->evidence_override_reason = null;
                $session->evidence_override_by = null;
                $session->evidence_override_at = null;
            }

            if (! $isCompleting) {
                return;
            }

            $decision = app(ClinicalEvidenceGateService::class)->assertCanCompleteTreatmentSession(
                session: $session,
                overrideReason: $session->evidence_override_reason,
            );

            if (($decision['override_used'] ?? false) === true) {
                $session->evidence_override_reason = trim((string) $session->evidence_override_reason);
                $session->evidence_override_by = $session->evidence_override_by ?? auth()->id();
                $session->evidence_override_at = $session->evidence_override_at ?? now();

                return;
            }

            $session->evidence_override_reason = null;
            $session->evidence_override_by = null;
            $session->evidence_override_at = null;
        });
    }

    public function treatmentPlan()
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    public function examSession()
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    public function planItem()
    {
        return $this->belongsTo(PlanItem::class, 'plan_item_id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function assistant()
    {
        return $this->belongsTo(User::class, 'assistant_id');
    }

    public function evidenceOverrideBy()
    {
        return $this->belongsTo(User::class, 'evidence_override_by');
    }

    public function materials()
    {
        return $this->hasMany(TreatmentMaterial::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function progressItem()
    {
        return $this->hasOne(TreatmentProgressItem::class, 'treatment_session_id');
    }

    public function mediaAssets()
    {
        return $this->hasMany(ClinicalMediaAsset::class);
    }

    public function resolveBranchId(): ?int
    {
        $branchId = $this->treatmentPlan?->branch_id
            ?? $this->planItem?->resolveBranchId();

        return $branchId !== null ? (int) $branchId : null;
    }
}
