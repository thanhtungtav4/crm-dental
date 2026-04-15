<?php

namespace App\Models;

use App\Casts\NullableEncrypted;
use App\Services\ExamSessionLifecycleService;
use App\Services\ExamSessionProvisioningService;
use App\Services\ExamSessionWorkflowService;
use App\Services\PatientAssignmentAuthorizer;
use App\Support\BranchAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ClinicalNote extends Model
{
    /**
     * @var array<string, mixed>|null
     */
    public ?array $revisionPreviousPayload = null;

    public ?string $revisionOperation = null;

    public ?string $revisionReason = null;

    protected $fillable = [
        'patient_id',
        'exam_session_id',
        'visit_episode_id',
        'doctor_id',
        'examining_doctor_id',
        'treating_doctor_id',
        'branch_id',
        'date',
        'examination_note',
        'general_exam_notes',
        'recommendation_notes',
        'treatment_plan_note',
        'indications',
        'indication_images',
        'diagnoses',
        'tooth_diagnosis_data',
        'other_diagnosis',
        'lock_version',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'exam_session_id' => 'integer',
        'visit_episode_id' => 'integer',
        'lock_version' => 'integer',
        'date' => 'date',
        'examination_note' => NullableEncrypted::class,
        'general_exam_notes' => NullableEncrypted::class,
        'recommendation_notes' => NullableEncrypted::class,
        'treatment_plan_note' => NullableEncrypted::class,
        'other_diagnosis' => NullableEncrypted::class,
        'indications' => 'array',
        'indication_images' => 'array',
        'diagnoses' => 'array',
        'tooth_diagnosis_data' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $clinicalNote): void {
            $authUser = BranchAccess::currentUser();

            if (! $authUser) {
                return;
            }

            $branchId = $clinicalNote->branch_id ? (int) $clinicalNote->branch_id : null;

            if ($branchId !== null) {
                BranchAccess::assertCanAccessBranch(
                    branchId: $branchId,
                    field: 'branch_id',
                    message: 'Bạn không có quyền ghi phiếu khám ở chi nhánh này.',
                );
            }

            $authorizer = app(PatientAssignmentAuthorizer::class);

            if (! $clinicalNote->exists || $clinicalNote->isDirty('doctor_id')) {
                $clinicalNote->doctor_id = $authorizer->assertAssignableDoctorId(
                    actor: $authUser,
                    doctorId: $clinicalNote->doctor_id ? (int) $clinicalNote->doctor_id : null,
                    branchId: $branchId,
                    field: 'doctor_id',
                );
            }

            if (! $clinicalNote->exists || $clinicalNote->isDirty('examining_doctor_id')) {
                $clinicalNote->examining_doctor_id = $authorizer->assertAssignableDoctorId(
                    actor: $authUser,
                    doctorId: $clinicalNote->examining_doctor_id ? (int) $clinicalNote->examining_doctor_id : null,
                    branchId: $branchId,
                    field: 'examining_doctor_id',
                );
            }

            if (! $clinicalNote->exists || $clinicalNote->isDirty('treating_doctor_id')) {
                $clinicalNote->treating_doctor_id = $authorizer->assertAssignableDoctorId(
                    actor: $authUser,
                    doctorId: $clinicalNote->treating_doctor_id ? (int) $clinicalNote->treating_doctor_id : null,
                    branchId: $branchId,
                    field: 'treating_doctor_id',
                );
            }
        });

        static::creating(function (self $clinicalNote): void {
            if (! $clinicalNote->lock_version || (int) $clinicalNote->lock_version < 1) {
                $clinicalNote->lock_version = 1;
            }

            if (! $clinicalNote->exam_session_id && $clinicalNote->patient_id) {
                $clinicalNote->exam_session_id = self::provisionExamSessionId($clinicalNote);
            }
        });

        static::updating(function (self $clinicalNote): void {
            if ($clinicalNote->hasTrackedRevisionChanges() && $clinicalNote->isRevisionLocked()) {
                throw ValidationException::withMessages([
                    'clinical_note' => 'EXAM_SESSION_LOCKED: Phiếu khám đã bị khóa và không thể chỉnh sửa nội dung lâm sàng.',
                ]);
            }

            if (! $clinicalNote->hasTrackedRevisionChanges()) {
                return;
            }

            if ($clinicalNote->revisionPreviousPayload === null) {
                $clinicalNote->revisionPreviousPayload = self::query()
                    ->whereKey($clinicalNote->id)
                    ->first()?->revisionPayload();
            }

            $clinicalNote->lock_version = ((int) $clinicalNote->getOriginal('lock_version')) + 1;
        });

        static::saved(function (self $clinicalNote): void {
            $clinicalNote->syncExamSessionSnapshot();
        });
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function visitEpisode()
    {
        return $this->belongsTo(VisitEpisode::class, 'visit_episode_id');
    }

    public function examSession()
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class, 'visit_episode_id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function examiningDoctor()
    {
        return $this->belongsTo(User::class, 'examining_doctor_id');
    }

    public function treatingDoctor()
    {
        return $this->belongsTo(User::class, 'treating_doctor_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function clinicalOrders(): HasMany
    {
        return $this->hasMany(ClinicalOrder::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ClinicalNoteRevision::class);
    }

    /**
     * @return array<int, string>
     */
    public static function trackedRevisionFields(): array
    {
        return [
            'doctor_id',
            'date',
            'branch_id',
            'exam_session_id',
            'visit_episode_id',
            'examination_note',
            'examining_doctor_id',
            'treating_doctor_id',
            'general_exam_notes',
            'recommendation_notes',
            'treatment_plan_note',
            'indications',
            'indication_images',
            'diagnoses',
            'tooth_diagnosis_data',
            'other_diagnosis',
        ];
    }

    public function hasTrackedRevisionChanges(): bool
    {
        return $this->isDirty(self::trackedRevisionFields());
    }

    /**
     * @return array<string, mixed>
     */
    public function revisionPayload(): array
    {
        return [
            'doctor_id' => $this->doctor_id ? (int) $this->doctor_id : null,
            'date' => $this->date?->toDateString(),
            'branch_id' => $this->branch_id ? (int) $this->branch_id : null,
            'exam_session_id' => $this->exam_session_id ? (int) $this->exam_session_id : null,
            'visit_episode_id' => $this->visit_episode_id ? (int) $this->visit_episode_id : null,
            'examination_note' => $this->examination_note,
            'examining_doctor_id' => $this->examining_doctor_id ? (int) $this->examining_doctor_id : null,
            'treating_doctor_id' => $this->treating_doctor_id ? (int) $this->treating_doctor_id : null,
            'general_exam_notes' => $this->general_exam_notes,
            'recommendation_notes' => $this->recommendation_notes,
            'treatment_plan_note' => $this->treatment_plan_note,
            'indications' => array_values((array) ($this->indications ?? [])),
            'indication_images' => (array) ($this->indication_images ?? []),
            'diagnoses' => array_values((array) ($this->diagnoses ?? [])),
            'tooth_diagnosis_data' => (array) ($this->tooth_diagnosis_data ?? []),
            'other_diagnosis' => $this->other_diagnosis,
        ];
    }

    public function scopeCurrentVersion(Builder $query): Builder
    {
        return $query->where('lock_version', '>=', 1);
    }

    protected static function provisionExamSessionId(self $clinicalNote): ?int
    {
        $doctorId = $clinicalNote->examining_doctor_id
            ?: $clinicalNote->treating_doctor_id
            ?: $clinicalNote->doctor_id
            ?: null;

        $session = app(ExamSessionProvisioningService::class)->resolveForPatientOnDate(
            patientId: (int) $clinicalNote->patient_id,
            branchId: $clinicalNote->branch_id ? (int) $clinicalNote->branch_id : null,
            date: $clinicalNote->date?->toDateString() ?: now()->toDateString(),
            doctorId: $doctorId ? (int) $doctorId : null,
            visitEpisodeId: $clinicalNote->visit_episode_id ? (int) $clinicalNote->visit_episode_id : null,
            createIfMissing: true,
        );

        if (! $session) {
            return null;
        }

        if ($session->status === ExamSession::STATUS_DRAFT) {
            $session = app(ExamSessionWorkflowService::class)->synchronizeSnapshot(
                examSession: $session,
                attributes: [
                    'created_by' => $session->created_by ?: ($clinicalNote->created_by ? (int) $clinicalNote->created_by : auth()->id()),
                    'updated_by' => $clinicalNote->updated_by ? (int) $clinicalNote->updated_by : auth()->id(),
                ],
                targetStatus: $clinicalNote->resolveExamSessionStatus(),
                context: [
                    'trigger' => 'clinical_note_provision',
                    'clinical_note_id' => $clinicalNote->id,
                    'patient_id' => (int) $clinicalNote->patient_id,
                ],
            );
        }

        return $session->id ? (int) $session->id : null;
    }

    public function syncExamSessionSnapshot(): self
    {
        if (! $this->patient_id) {
            return $this;
        }

        if (! $this->exam_session_id) {
            $sessionId = self::provisionExamSessionId($this);

            if (! $sessionId) {
                return $this;
            }

            $this->forceFill([
                'exam_session_id' => $sessionId,
            ])->saveQuietly();

            $this->exam_session_id = $sessionId;
        }

        $session = ExamSession::query()->find((int) $this->exam_session_id);
        if (! $session) {
            return $this;
        }

        $doctorId = $this->examining_doctor_id
            ?: $this->treating_doctor_id
            ?: $this->doctor_id
            ?: null;

        $statusPriority = [
            ExamSession::STATUS_DRAFT => 0,
            ExamSession::STATUS_PLANNED => 1,
            ExamSession::STATUS_IN_PROGRESS => 2,
            ExamSession::STATUS_COMPLETED => 3,
            ExamSession::STATUS_LOCKED => 4,
        ];

        $currentStatus = (string) ($session->status ?: ExamSession::STATUS_DRAFT);
        $targetStatus = $this->resolveExamSessionStatus();

        if (($statusPriority[$targetStatus] ?? 0) < ($statusPriority[$currentStatus] ?? 0)) {
            $targetStatus = $currentStatus;
        }

        $payload = [
            'patient_id' => (int) $this->patient_id,
            'visit_episode_id' => $this->visit_episode_id ? (int) $this->visit_episode_id : null,
            'branch_id' => $this->branch_id ? (int) $this->branch_id : null,
            'doctor_id' => $doctorId ? (int) $doctorId : null,
            'session_date' => $this->date?->toDateString() ?: now()->toDateString(),
            'updated_by' => $this->updated_by ? (int) $this->updated_by : auth()->id(),
        ];

        $session = app(ExamSessionWorkflowService::class)->synchronizeSnapshot(
            examSession: $session,
            attributes: $payload,
            targetStatus: $targetStatus,
            context: [
                'trigger' => 'clinical_note_sync',
                'clinical_note_id' => $this->id,
                'patient_id' => (int) $this->patient_id,
            ],
        );

        app(ExamSessionLifecycleService::class)->refresh((int) $session->id);

        return $this;
    }

    protected function resolveExamSessionStatus(): string
    {
        if ($this->hasClinicalPayloadContent()) {
            return ExamSession::STATUS_IN_PROGRESS;
        }

        if ($this->visit_episode_id || $this->examining_doctor_id || $this->treating_doctor_id || $this->doctor_id) {
            return ExamSession::STATUS_PLANNED;
        }

        return ExamSession::STATUS_DRAFT;
    }

    protected function hasClinicalPayloadContent(): bool
    {
        $indications = array_filter((array) ($this->indications ?? []), fn ($item) => filled($item));
        $diagnoses = array_filter((array) ($this->diagnoses ?? []), fn ($item) => filled($item));
        $diagnosis = array_filter((array) ($this->tooth_diagnosis_data ?? []), fn ($item) => filled($item));

        return filled($this->examination_note)
            || filled($this->general_exam_notes)
            || filled($this->recommendation_notes)
            || filled($this->treatment_plan_note)
            || filled($this->other_diagnosis)
            || ! empty($indications)
            || ! empty($diagnoses)
            || ! empty($diagnosis);
    }

    protected function isRevisionLocked(): bool
    {
        $status = $this->examSession?->status;

        if (! $status && $this->exam_session_id) {
            $status = ExamSession::query()
                ->whereKey((int) $this->exam_session_id)
                ->value('status');
        }

        return $status === ExamSession::STATUS_LOCKED;
    }
}
