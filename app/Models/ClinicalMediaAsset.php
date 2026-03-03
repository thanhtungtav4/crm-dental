<?php

namespace App\Models;

use App\Support\BranchAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class ClinicalMediaAsset extends Model
{
    /** @use HasFactory<\Database\Factories\ClinicalMediaAssetFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const MODALITY_PHOTO = 'photo';

    public const MODALITY_XRAY = 'xray';

    public const MODALITY_DOCUMENT = 'document';

    public const RETENTION_CLINICAL_LEGAL = 'clinical_legal';

    public const RETENTION_CLINICAL_OPERATIONAL = 'clinical_operational';

    public const RETENTION_TEMPORARY = 'temporary';

    protected $fillable = [
        'patient_id',
        'visit_episode_id',
        'exam_session_id',
        'plan_item_id',
        'treatment_session_id',
        'clinical_order_id',
        'clinical_result_id',
        'prescription_id',
        'branch_id',
        'captured_by',
        'consent_id',
        'captured_at',
        'modality',
        'anatomy_scope',
        'phase',
        'mime_type',
        'file_size_bytes',
        'checksum_sha256',
        'storage_disk',
        'storage_path',
        'status',
        'retention_class',
        'legal_hold',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'patient_id' => 'integer',
            'visit_episode_id' => 'integer',
            'exam_session_id' => 'integer',
            'plan_item_id' => 'integer',
            'treatment_session_id' => 'integer',
            'clinical_order_id' => 'integer',
            'clinical_result_id' => 'integer',
            'prescription_id' => 'integer',
            'branch_id' => 'integer',
            'captured_by' => 'integer',
            'consent_id' => 'integer',
            'captured_at' => 'datetime',
            'file_size_bytes' => 'integer',
            'legal_hold' => 'boolean',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $asset): void {
            if (blank($asset->patient_id)) {
                $asset->patient_id = static::inferPatientId($asset);
            }

            if (blank($asset->branch_id)) {
                $asset->branch_id = static::inferBranchId($asset);
            }

            if (blank($asset->captured_at)) {
                $asset->captured_at = now();
            }

            if (blank($asset->status)) {
                $asset->status = self::STATUS_ACTIVE;
            }

            if (blank($asset->retention_class)) {
                $asset->retention_class = self::RETENTION_CLINICAL_OPERATIONAL;
            }

            if (blank($asset->modality)) {
                $asset->modality = self::MODALITY_PHOTO;
            }

            if (blank($asset->phase)) {
                $asset->phase = 'unspecified';
            }

            if (blank($asset->storage_disk)) {
                $asset->storage_disk = 'public';
            }

            if (blank($asset->storage_path)) {
                throw ValidationException::withMessages([
                    'storage_path' => 'Thiếu đường dẫn lưu trữ ảnh lâm sàng.',
                ]);
            }

            if ($asset->branch_id !== null) {
                BranchAccess::assertCanAccessBranch(
                    branchId: (int) $asset->branch_id,
                    field: 'branch_id',
                    message: 'Bạn không có quyền thao tác hồ ảnh ở chi nhánh này.',
                );
            }
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visitEpisode(): BelongsTo
    {
        return $this->belongsTo(VisitEpisode::class, 'visit_episode_id');
    }

    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    public function planItem(): BelongsTo
    {
        return $this->belongsTo(PlanItem::class);
    }

    public function treatmentSession(): BelongsTo
    {
        return $this->belongsTo(TreatmentSession::class);
    }

    public function clinicalOrder(): BelongsTo
    {
        return $this->belongsTo(ClinicalOrder::class);
    }

    public function clinicalResult(): BelongsTo
    {
        return $this->belongsTo(ClinicalResult::class);
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }

    public function consent(): BelongsTo
    {
        return $this->belongsTo(Consent::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ClinicalMediaVersion::class);
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(ClinicalMediaAccessLog::class);
    }

    public function resolveBranchId(): ?int
    {
        $branchId = $this->branch_id
            ?? $this->examSession?->branch_id
            ?? $this->visitEpisode?->branch_id
            ?? $this->clinicalOrder?->branch_id
            ?? $this->clinicalResult?->branch_id
            ?? $this->prescription?->branch_id
            ?? $this->patient?->first_branch_id
            ?? $this->planItem?->resolveBranchId()
            ?? $this->treatmentSession?->resolveBranchId();

        return $branchId !== null ? (int) $branchId : null;
    }

    public function resolvePatientId(): ?int
    {
        $patientId = $this->patient_id
            ?? $this->examSession?->patient_id
            ?? $this->visitEpisode?->patient_id
            ?? $this->clinicalOrder?->patient_id
            ?? $this->clinicalResult?->patient_id
            ?? $this->prescription?->patient_id
            ?? $this->planItem?->treatmentPlan?->patient_id
            ?? $this->treatmentSession?->treatmentPlan?->patient_id;

        return $patientId !== null ? (int) $patientId : null;
    }

    protected static function inferPatientId(self $asset): ?int
    {
        if ($asset->exam_session_id) {
            $patientId = ExamSession::query()
                ->whereKey((int) $asset->exam_session_id)
                ->value('patient_id');

            if ($patientId !== null) {
                return (int) $patientId;
            }
        }

        if ($asset->visit_episode_id) {
            $patientId = VisitEpisode::query()
                ->whereKey((int) $asset->visit_episode_id)
                ->value('patient_id');

            if ($patientId !== null) {
                return (int) $patientId;
            }
        }

        if ($asset->plan_item_id) {
            $patientId = PlanItem::query()
                ->join('treatment_plans', 'treatment_plans.id', '=', 'plan_items.treatment_plan_id')
                ->where('plan_items.id', (int) $asset->plan_item_id)
                ->value('treatment_plans.patient_id');

            if ($patientId !== null) {
                return (int) $patientId;
            }
        }

        if ($asset->treatment_session_id) {
            $patientId = TreatmentSession::query()
                ->join('treatment_plans', 'treatment_plans.id', '=', 'treatment_sessions.treatment_plan_id')
                ->where('treatment_sessions.id', (int) $asset->treatment_session_id)
                ->value('treatment_plans.patient_id');

            if ($patientId !== null) {
                return (int) $patientId;
            }
        }

        if ($asset->clinical_order_id) {
            $patientId = ClinicalOrder::query()
                ->whereKey((int) $asset->clinical_order_id)
                ->value('patient_id');

            if ($patientId !== null) {
                return (int) $patientId;
            }
        }

        if ($asset->clinical_result_id) {
            $patientId = ClinicalResult::query()
                ->whereKey((int) $asset->clinical_result_id)
                ->value('patient_id');

            if ($patientId !== null) {
                return (int) $patientId;
            }
        }

        if (! $asset->prescription_id) {
            return null;
        }

        $patientId = Prescription::query()
            ->whereKey((int) $asset->prescription_id)
            ->value('patient_id');

        return $patientId !== null ? (int) $patientId : null;
    }

    protected static function inferBranchId(self $asset): ?int
    {
        if ($asset->exam_session_id) {
            $branchId = ExamSession::query()
                ->whereKey((int) $asset->exam_session_id)
                ->value('branch_id');

            if ($branchId !== null) {
                return (int) $branchId;
            }
        }

        if ($asset->visit_episode_id) {
            $branchId = VisitEpisode::query()
                ->whereKey((int) $asset->visit_episode_id)
                ->value('branch_id');

            if ($branchId !== null) {
                return (int) $branchId;
            }
        }

        if ($asset->plan_item_id) {
            $branchId = PlanItem::query()
                ->join('treatment_plans', 'treatment_plans.id', '=', 'plan_items.treatment_plan_id')
                ->where('plan_items.id', (int) $asset->plan_item_id)
                ->value('treatment_plans.branch_id');

            if ($branchId !== null) {
                return (int) $branchId;
            }
        }

        if ($asset->treatment_session_id) {
            $branchId = TreatmentSession::query()
                ->join('treatment_plans', 'treatment_plans.id', '=', 'treatment_sessions.treatment_plan_id')
                ->where('treatment_sessions.id', (int) $asset->treatment_session_id)
                ->value('treatment_plans.branch_id');

            if ($branchId !== null) {
                return (int) $branchId;
            }
        }

        if ($asset->clinical_order_id) {
            $branchId = ClinicalOrder::query()
                ->whereKey((int) $asset->clinical_order_id)
                ->value('branch_id');

            if ($branchId !== null) {
                return (int) $branchId;
            }
        }

        if ($asset->clinical_result_id) {
            $branchId = ClinicalResult::query()
                ->whereKey((int) $asset->clinical_result_id)
                ->value('branch_id');

            if ($branchId !== null) {
                return (int) $branchId;
            }
        }

        if ($asset->prescription_id) {
            $branchId = Prescription::query()
                ->whereKey((int) $asset->prescription_id)
                ->value('branch_id');

            if ($branchId !== null) {
                return (int) $branchId;
            }
        }

        if (! $asset->patient_id) {
            return null;
        }

        $branchId = Patient::query()
            ->whereKey((int) $asset->patient_id)
            ->value('first_branch_id');

        return $branchId !== null ? (int) $branchId : null;
    }
}
