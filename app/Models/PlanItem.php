<?php

namespace App\Models;

use App\Support\ActionGate;
use App\Support\ActionPermission;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class PlanItem extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const APPROVAL_DRAFT = 'draft';

    public const APPROVAL_PROPOSED = 'proposed';

    public const APPROVAL_APPROVED = 'approved';

    public const APPROVAL_DECLINED = 'declined';

    public const DEFAULT_STATUS = self::STATUS_PENDING;

    public const DEFAULT_APPROVAL_STATUS = self::APPROVAL_PROPOSED;

    protected const STATUS_OPTIONS = [
        self::STATUS_PENDING => 'Chờ thực hiện',
        self::STATUS_IN_PROGRESS => 'Đang thực hiện',
        self::STATUS_COMPLETED => 'Hoàn thành',
        self::STATUS_CANCELLED => 'Đã hủy',
    ];

    protected const APPROVAL_STATUS_OPTIONS = [
        self::APPROVAL_DRAFT => 'Nháp',
        self::APPROVAL_PROPOSED => 'Đã đề xuất',
        self::APPROVAL_APPROVED => 'Đã duyệt',
        self::APPROVAL_DECLINED => 'Từ chối',
    ];

    protected const APPROVAL_STATUS_COLORS = [
        self::APPROVAL_DRAFT => 'gray',
        self::APPROVAL_PROPOSED => 'warning',
        self::APPROVAL_APPROVED => 'success',
        self::APPROVAL_DECLINED => 'danger',
    ];

    protected const APPROVAL_STATUS_TRANSITIONS = [
        self::APPROVAL_DRAFT => [
            self::APPROVAL_PROPOSED,
            self::APPROVAL_DECLINED,
        ],
        self::APPROVAL_PROPOSED => [
            self::APPROVAL_DRAFT,
            self::APPROVAL_APPROVED,
            self::APPROVAL_DECLINED,
        ],
        self::APPROVAL_APPROVED => [
            self::APPROVAL_APPROVED,
        ],
        self::APPROVAL_DECLINED => [
            self::APPROVAL_DRAFT,
            self::APPROVAL_PROPOSED,
            self::APPROVAL_APPROVED,
        ],
    ];

    protected const LEGACY_STATUS_MAP = [
        'pending' => self::STATUS_PENDING,
        'in_progress' => self::STATUS_IN_PROGRESS,
        'in progress' => self::STATUS_IN_PROGRESS,
        'in-progress' => self::STATUS_IN_PROGRESS,
        'done' => self::STATUS_COMPLETED,
        'completed' => self::STATUS_COMPLETED,
        'cancelled' => self::STATUS_CANCELLED,
        'canceled' => self::STATUS_CANCELLED,
    ];

    protected const LEGACY_APPROVAL_STATUS_MAP = [
        'draft' => self::APPROVAL_DRAFT,
        'proposed' => self::APPROVAL_PROPOSED,
        'pending' => self::APPROVAL_PROPOSED,
        'approved' => self::APPROVAL_APPROVED,
        'accepted' => self::APPROVAL_APPROVED,
        'agreed' => self::APPROVAL_APPROVED,
        'declined' => self::APPROVAL_DECLINED,
        'rejected' => self::APPROVAL_DECLINED,
        'true' => self::APPROVAL_APPROVED,
        '1' => self::APPROVAL_APPROVED,
        'false' => self::APPROVAL_PROPOSED,
        '0' => self::APPROVAL_PROPOSED,
    ];

    protected $fillable = [
        'treatment_plan_id',
        'name',
        'service_id',
        'tooth_ids',
        'diagnosis_ids',
        'tooth_number',
        'tooth_notation',
        'quantity',
        'price', // Keep for backward compatibility
        'discount_amount',
        'discount_percent',
        'vat_amount',
        'final_amount',
        'estimated_cost',
        'actual_cost',
        'required_visits',
        'completed_visits',
        'estimated_supplies',
        'status',
        'patient_approved',
        'approval_status',
        'approval_decline_reason',
        'is_completed',
        'priority',
        'notes',
        'before_photo',
        'after_photo',
        'progress_percentage',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'tooth_ids' => 'array',
        'diagnosis_ids' => 'array',
        'estimated_supplies' => 'array',
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'required_visits' => 'integer',
        'completed_visits' => 'integer',
        'progress_percentage' => 'integer',
        'started_at' => 'date',
        'completed_at' => 'date',
        'is_completed' => 'boolean',
        'patient_approved' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            $item->status = static::normalizeStatus($item->status) ?? static::DEFAULT_STATUS;

            $normalizedApproval = static::normalizeApprovalStatus($item->approval_status);
            if ($normalizedApproval === null) {
                $normalizedApproval = static::approvalStatusFromLegacyFlag($item->patient_approved);
            }

            $item->approval_status = $normalizedApproval ?? static::DEFAULT_APPROVAL_STATUS;

            if ($item->exists && $item->isDirty('approval_status')) {
                ActionGate::authorize(
                    ActionPermission::PLAN_APPROVAL,
                    'Bạn không có quyền thay đổi trạng thái duyệt kế hoạch điều trị.',
                );

                $fromStatus = static::normalizeApprovalStatus((string) $item->getOriginal('approval_status'));
                if ($fromStatus === null && array_key_exists('patient_approved', $item->getOriginal())) {
                    $fromStatus = static::approvalStatusFromLegacyFlag($item->getOriginal('patient_approved'));
                }

                $fromStatus ??= static::DEFAULT_APPROVAL_STATUS;

                if (! static::canTransitionApprovalStatus($fromStatus, $item->approval_status)) {
                    throw ValidationException::withMessages([
                        'approval_status' => sprintf(
                            'PLAN_ITEM_APPROVAL_STATE_INVALID: Không thể chuyển từ "%s" sang "%s".',
                            static::approvalStatusLabel($fromStatus),
                            static::approvalStatusLabel($item->approval_status),
                        ),
                    ]);
                }

                AuditLog::record(
                    entityType: AuditLog::ENTITY_PLAN_ITEM,
                    entityId: (int) ($item->id ?? 0),
                    action: $item->approval_status === self::APPROVAL_APPROVED
                        ? AuditLog::ACTION_APPROVE
                        : AuditLog::ACTION_UPDATE,
                    actorId: auth()->id(),
                    metadata: [
                        'plan_item_id' => $item->id,
                        'treatment_plan_id' => $item->treatment_plan_id,
                        'patient_id' => $item->treatmentPlan?->patient_id,
                        'approval_status_from' => $fromStatus,
                        'approval_status_to' => $item->approval_status,
                        'approval_decline_reason' => $item->approval_decline_reason,
                    ],
                );
            }

            static::assertDeclineReasonRequirement($item);
            static::assertTreatmentPhaseGate($item);

            $item->patient_approved = $item->approval_status === static::APPROVAL_APPROVED;
        });
    }

    public function treatmentPlan()
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    public function sessions()
    {
        return $this->hasMany(TreatmentSession::class, 'plan_item_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function consents()
    {
        return $this->hasMany(Consent::class);
    }

    public function resolveBranchId(): ?int
    {
        $branchId = $this->treatmentPlan?->branch_id
            ?? $this->treatmentPlan?->patient?->first_branch_id;

        return $branchId !== null ? (int) $branchId : null;
    }

    // Helper Methods

    /**
     * Update progress based on completed visits
     */
    public function updateProgress(): void
    {
        static::assertTreatmentPhaseGate($this);

        $requiredVisits = max(1, (int) $this->required_visits);
        $this->completed_visits = min((int) $this->completed_visits, $requiredVisits);

        if ($this->required_visits > 0) {
            $this->progress_percentage = (int) (($this->completed_visits / $requiredVisits) * 100);
        } else {
            // If no visits required, base on status
            $this->progress_percentage = match ($this->status) {
                self::STATUS_PENDING => 0,
                self::STATUS_IN_PROGRESS => 50,
                self::STATUS_COMPLETED => 100,
                self::STATUS_CANCELLED => 0,
                default => 0,
            };
        }

        // Auto-update status based on progress
        if ($this->progress_percentage === 0 && $this->status === self::STATUS_PENDING) {
            // Keep as pending
        } elseif ($this->progress_percentage > 0 && $this->progress_percentage < 100) {
            $this->status = self::STATUS_IN_PROGRESS;
            if (! $this->started_at) {
                $this->started_at = now()->toDateString();
            }
        } elseif ($this->progress_percentage === 100) {
            $this->status = self::STATUS_COMPLETED;
            $this->completed_at = now()->toDateString();
        }

        $this->save();

        // Update parent treatment plan
        $this->treatmentPlan?->updateProgress();
    }

    /**
     * Mark a visit as completed
     */
    public function completeVisit(): void
    {
        if (! $this->canStartTreatment()) {
            throw ValidationException::withMessages([
                'approval_status' => 'Hạng mục chưa được bệnh nhân duyệt. Không thể cập nhật tiến độ điều trị.',
            ]);
        }

        if ($this->completed_visits < $this->required_visits) {
            $this->completed_visits++;
            $this->updateProgress();
        }
    }

    /**
     * Get status label in Vietnamese
     */
    public function getStatusLabel(): string
    {
        return static::STATUS_OPTIONS[$this->status] ?? $this->status;
    }

    /**
     * Get approval status label in Vietnamese
     */
    public function getApprovalStatusLabel(): string
    {
        return static::approvalStatusLabel($this->approval_status);
    }

    /**
     * Get approval badge color
     */
    public function getApprovalStatusBadgeColor(): string
    {
        return static::approvalStatusColor($this->approval_status);
    }

    /**
     * Get priority label in Vietnamese
     */
    public function getPriorityLabel(): string
    {
        return match ($this->priority) {
            'low' => 'Thấp',
            'normal' => 'Bình thường',
            'high' => 'Cao',
            'urgent' => 'Khẩn cấp',
            default => $this->priority,
        };
    }

    /**
     * Get progress badge color
     */
    public function getProgressBadgeColor(): string
    {
        return match (true) {
            $this->progress_percentage === 0 => 'gray',
            $this->progress_percentage < 50 => 'warning',
            $this->progress_percentage < 100 => 'info',
            $this->progress_percentage === 100 => 'success',
            default => 'gray',
        };
    }

    /**
     * Get tooth notation display
     */
    public function getToothNotationDisplay(): ?string
    {
        if (! $this->tooth_number) {
            return null;
        }

        $notation = $this->tooth_notation === 'universal' ? 'Universal' : 'FDI';

        return "🦷 {$this->tooth_number} ({$notation})";
    }

    /**
     * Parse tooth number (supports ranges like "11-14")
     */
    public function getToothNumbers(): array
    {
        if (! $this->tooth_number) {
            return [];
        }

        // Check if it's a range (e.g., "11-14")
        if (str_contains($this->tooth_number, '-')) {
            [$start, $end] = explode('-', $this->tooth_number);

            return range((int) $start, (int) $end);
        }

        // Check if it's a comma-separated list (e.g., "11,12,13")
        if (str_contains($this->tooth_number, ',')) {
            return array_map('intval', explode(',', $this->tooth_number));
        }

        // Single tooth
        return [(int) $this->tooth_number];
    }

    /**
     * Check if has before photo
     */
    public function hasBeforePhoto(): bool
    {
        return ! empty($this->before_photo);
    }

    /**
     * Check if has after photo
     */
    public function hasAfterPhoto(): bool
    {
        return ! empty($this->after_photo);
    }

    /**
     * Get cost variance (actual vs estimated)
     */
    public function getCostVariance(): float
    {
        return (float) ($this->actual_cost - $this->estimated_cost);
    }

    /**
     * Check if is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if is in progress
     */
    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Check if item has approved treatment consent
     */
    public function isApproved(): bool
    {
        return static::normalizeApprovalStatus($this->approval_status) === self::APPROVAL_APPROVED;
    }

    /**
     * Check if item has declined approval
     */
    public function isDeclined(): bool
    {
        return static::normalizeApprovalStatus($this->approval_status) === self::APPROVAL_DECLINED;
    }

    /**
     * Check if treatment can start for current item
     */
    public function canStartTreatment(): bool
    {
        return $this->isApproved();
    }

    /**
     * Normalize legacy status aliases.
     */
    public static function normalizeStatus(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        $normalized = strtolower(trim($status));

        return static::LEGACY_STATUS_MAP[$normalized] ?? $normalized;
    }

    /**
     * Normalize legacy approval aliases.
     */
    public static function normalizeApprovalStatus(mixed $status): ?string
    {
        if (is_bool($status) || is_int($status)) {
            return static::approvalStatusFromLegacyFlag($status);
        }

        if ($status === null) {
            return null;
        }

        $normalized = strtolower(trim((string) $status));

        return static::LEGACY_APPROVAL_STATUS_MAP[$normalized] ?? null;
    }

    /**
     * Map legacy boolean patient_approved into workflow status.
     */
    public static function approvalStatusFromLegacyFlag(mixed $flag): string
    {
        return filter_var($flag, FILTER_VALIDATE_BOOLEAN)
            ? self::APPROVAL_APPROVED
            : self::APPROVAL_PROPOSED;
    }

    public static function approvalStatusOptions(): array
    {
        return static::APPROVAL_STATUS_OPTIONS;
    }

    public static function approvalStatusLabel(?string $status): string
    {
        $normalized = static::normalizeApprovalStatus($status) ?? static::DEFAULT_APPROVAL_STATUS;

        return static::APPROVAL_STATUS_OPTIONS[$normalized] ?? 'Chưa xác định';
    }

    public static function approvalStatusColor(?string $status): string
    {
        $normalized = static::normalizeApprovalStatus($status) ?? static::DEFAULT_APPROVAL_STATUS;

        return static::APPROVAL_STATUS_COLORS[$normalized] ?? 'gray';
    }

    public static function canTransitionApprovalStatus(?string $fromStatus, ?string $toStatus): bool
    {
        $from = static::normalizeApprovalStatus($fromStatus) ?? static::DEFAULT_APPROVAL_STATUS;
        $to = static::normalizeApprovalStatus($toStatus);

        if ($to === null) {
            return false;
        }

        if ($from === $to) {
            return true;
        }

        $allowed = static::APPROVAL_STATUS_TRANSITIONS[$from] ?? [];

        return in_array($to, $allowed, true);
    }

    protected static function assertDeclineReasonRequirement(self $item): void
    {
        if ($item->approval_status === self::APPROVAL_DECLINED && blank($item->approval_decline_reason)) {
            throw ValidationException::withMessages([
                'approval_decline_reason' => 'Vui lòng nhập lý do bệnh nhân từ chối điều trị.',
            ]);
        }

        if ($item->approval_status !== self::APPROVAL_DECLINED) {
            $item->approval_decline_reason = null;
        }
    }

    protected static function assertTreatmentPhaseGate(self $item): void
    {
        $approvalStatus = static::normalizeApprovalStatus($item->approval_status) ?? static::DEFAULT_APPROVAL_STATUS;
        $status = static::normalizeStatus($item->status) ?? static::DEFAULT_STATUS;
        $hasProgress = ((int) $item->completed_visits) > 0 || ((int) $item->progress_percentage) > 0;
        $isAdvancedPhase = in_array($status, [self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED], true);

        if ($approvalStatus !== self::APPROVAL_APPROVED && ($hasProgress || $isAdvancedPhase)) {
            throw ValidationException::withMessages([
                'approval_status' => 'Hạng mục chưa được bệnh nhân duyệt nên không thể chuyển sang giai đoạn điều trị.',
            ]);
        }

        if ($hasProgress || $isAdvancedPhase) {
            $planStatus = $item->treatmentPlan?->status;

            if ($planStatus === TreatmentPlan::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Kế hoạch điều trị chưa được duyệt nên không thể chuyển sang giai đoạn tiếp theo.',
                ]);
            }

            $serviceRequiresConsent = (bool) optional($item->service)->requires_consent;

            if ($serviceRequiresConsent) {
                $patientId = $item->treatmentPlan?->patient_id;

                $hasValidConsent = Consent::query()
                    ->where('patient_id', $patientId)
                    ->where(function ($query) use ($item): void {
                        $query->where('plan_item_id', $item->id);

                        if ($item->service_id) {
                            $query->orWhere('service_id', $item->service_id);
                        }
                    })
                    ->validAt(now())
                    ->exists();

                if (! $hasValidConsent) {
                    throw ValidationException::withMessages([
                        'approval_status' => 'Thiếu consent hợp lệ cho thủ thuật rủi ro cao. Không thể chuyển giai đoạn điều trị.',
                    ]);
                }
            }
        }
    }

    /**
     * Scopes
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('approval_status', self::APPROVAL_APPROVED);
    }

    public function scopePendingApproval($query)
    {
        return $query->whereIn('approval_status', [
            self::APPROVAL_DRAFT,
            self::APPROVAL_PROPOSED,
            self::APPROVAL_DECLINED,
        ]);
    }

    public function scopeForTooth($query, $toothNumber)
    {
        return $query->where('tooth_number', 'like', "%{$toothNumber}%");
    }
}
