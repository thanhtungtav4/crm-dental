<?php

namespace App\Models;

use App\Support\BranchAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class TreatmentPlan extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const DEFAULT_STATUS = self::STATUS_DRAFT;

    protected const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Nháp',
        self::STATUS_APPROVED => 'Đã duyệt',
        self::STATUS_IN_PROGRESS => 'Đang thực hiện',
        self::STATUS_COMPLETED => 'Hoàn thành',
        self::STATUS_CANCELLED => 'Đã hủy',
    ];

    protected const STATUS_TRANSITIONS = [
        self::STATUS_DRAFT => [
            self::STATUS_DRAFT,
            self::STATUS_APPROVED,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_APPROVED => [
            self::STATUS_APPROVED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_IN_PROGRESS => [
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_COMPLETED => [
            self::STATUS_COMPLETED,
        ],
        self::STATUS_CANCELLED => [
            self::STATUS_CANCELLED,
        ],
    ];

    protected static int $managedWorkflowDepth = 0;

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'branch_id',
        'title',
        'notes',
        'total_cost',
        'total_estimated_cost',
        'total_visits',
        'completed_visits',
        'progress_percentage',
        'before_photo',
        'after_photo',
        'status',
        'priority',
        'expected_start_date',
        'expected_end_date',
        'actual_start_date',
        'actual_end_date',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
        'general_exam_data',
        'tooth_diagnosis_data',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    protected $casts = [
        'approved_at' => 'datetime',
        'expected_start_date' => 'date',
        'expected_end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
        'total_cost' => 'decimal:2',
        'total_estimated_cost' => 'decimal:2',
        'total_visits' => 'integer',
        'completed_visits' => 'integer',
        'progress_percentage' => 'integer',
        'general_exam_data' => 'array',
        'tooth_diagnosis_data' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $plan): void {
            $patient = is_numeric($plan->patient_id)
                ? Patient::query()
                    ->select(['id', 'first_branch_id'])
                    ->find((int) $plan->patient_id)
                : null;

            if (! $patient instanceof Patient) {
                throw ValidationException::withMessages([
                    'patient_id' => 'Vui lòng chọn bệnh nhân hợp lệ cho kế hoạch điều trị.',
                ]);
            }

            if (is_numeric($patient->first_branch_id)) {
                BranchAccess::assertCanAccessBranch(
                    branchId: (int) $patient->first_branch_id,
                    field: 'patient_id',
                    message: 'Bạn không thể tạo hoặc cập nhật kế hoạch điều trị cho bệnh nhân ngoài phạm vi được phân quyền.',
                );
            }

            if (is_numeric($plan->branch_id)) {
                BranchAccess::assertCanAccessBranch(
                    branchId: (int) $plan->branch_id,
                    field: 'branch_id',
                    message: 'Bạn không có quyền thao tác kế hoạch điều trị ở chi nhánh này.',
                );
            }

            $plan->status = static::normalizeStatusValue($plan->status) ?? static::DEFAULT_STATUS;

            if (! $plan->exists || ! $plan->isDirty('status')) {
                static::assertWorkflowControlledFields($plan);

                return;
            }

            if (! static::isManagedWorkflow()) {
                throw ValidationException::withMessages([
                    'status' => 'Trang thai ke hoach chi duoc thay doi qua TreatmentPlanWorkflowService.',
                ]);
            }

            $fromStatus = static::normalizeStatusValue($plan->getOriginal('status')) ?? static::DEFAULT_STATUS;
            $toStatus = static::normalizeStatusValue($plan->status) ?? static::DEFAULT_STATUS;

            if (! static::canTransitionStatus($fromStatus, $toStatus)) {
                throw ValidationException::withMessages([
                    'status' => sprintf(
                        'Khong the chuyen ke hoach dieu tri tu "%s" sang "%s".',
                        static::statusLabel($fromStatus),
                        static::statusLabel($toStatus),
                    ),
                ]);
            }

            static::assertWorkflowControlledFields($plan);
        });

        static::deleting(function (): void {
            throw ValidationException::withMessages([
                'treatment_plan' => 'Kế hoạch điều trị không hỗ trợ xóa trực tiếp. Vui lòng hủy kế hoạch qua workflow.',
            ]);
        });
    }

    public static function runWithinManagedWorkflow(callable $callback): mixed
    {
        static::$managedWorkflowDepth++;

        try {
            return $callback();
        } finally {
            static::$managedWorkflowDepth = max(0, static::$managedWorkflowDepth - 1);
        }
    }

    public static function normalizeStatusValue(mixed $status): ?string
    {
        $normalized = strtolower(trim((string) $status));

        return array_key_exists($normalized, static::STATUS_LABELS) ? $normalized : null;
    }

    public static function canTransitionStatus(?string $fromStatus, ?string $toStatus): bool
    {
        $resolvedFrom = static::normalizeStatusValue($fromStatus) ?? static::DEFAULT_STATUS;
        $resolvedTo = static::normalizeStatusValue($toStatus);

        if ($resolvedTo === null) {
            return false;
        }

        return in_array($resolvedTo, static::STATUS_TRANSITIONS[$resolvedFrom] ?? [], true);
    }

    public static function statusLabel(?string $status): string
    {
        $normalizedStatus = static::normalizeStatusValue($status);

        return static::STATUS_LABELS[$normalizedStatus] ?? (string) $status;
    }

    protected static function isManagedWorkflow(): bool
    {
        return static::$managedWorkflowDepth > 0;
    }

    protected static function assertWorkflowControlledFields(self $plan): void
    {
        if (static::isManagedWorkflow()) {
            return;
        }

        foreach (['approved_by', 'approved_at', 'actual_start_date', 'actual_end_date'] as $field) {
            if ($plan->exists && $plan->isDirty($field)) {
                throw ValidationException::withMessages([
                    $field => 'Workflow ke hoach dieu tri chi duoc cap nhat qua TreatmentPlanWorkflowService.',
                ]);
            }
        }
    }

    public function sessions()
    {
        return $this->hasMany(TreatmentSession::class);
    }

    public function progressDays()
    {
        return $this->hasMany(TreatmentProgressDay::class);
    }

    public function progressItems()
    {
        return $this->hasMany(TreatmentProgressItem::class);
    }

    public function planItems()
    {
        return $this->hasMany(PlanItem::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function calculateProgress(): int
    {
        $items = $this->planItems;

        if ($items->isEmpty()) {
            return 0;
        }

        $totalProgress = $items->sum('progress_percentage');

        return (int) ($totalProgress / $items->count());
    }

    public function updateProgress(): void
    {
        $this->progress_percentage = $this->calculateProgress();
        $this->completed_visits = $this->planItems->sum('completed_visits');
        $this->total_visits = $this->planItems->sum('required_visits');
        $this->total_cost = $this->planItems->sum('actual_cost');
        $this->total_estimated_cost = $this->planItems->sum('estimated_cost');

        if ($this->progress_percentage === 0 && $this->status === self::STATUS_DRAFT) {
            // Keep as draft.
        } elseif ($this->progress_percentage > 0 && $this->progress_percentage < 100) {
            if (in_array($this->status, [self::STATUS_APPROVED, self::STATUS_IN_PROGRESS], true)) {
                $this->status = self::STATUS_IN_PROGRESS;
            }
        } elseif ($this->progress_percentage === 100) {
            if (in_array($this->status, [self::STATUS_APPROVED, self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED], true)) {
                $this->status = self::STATUS_COMPLETED;
                $this->actual_end_date = now()->toDateString();
            }
        }

        static::runWithinManagedWorkflow(function (): void {
            $this->save();
        });
    }

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

    public function getStatusLabel(): string
    {
        return static::STATUS_LABELS[$this->status] ?? $this->status;
    }

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

    public function isOverdue(): bool
    {
        if (! $this->expected_end_date) {
            return false;
        }

        return $this->expected_end_date->isPast() && $this->status !== self::STATUS_COMPLETED;
    }

    public function getCostVariance(): float
    {
        return (float) ($this->total_cost - $this->total_estimated_cost);
    }

    public function getCostVariancePercentage(): float
    {
        if ($this->total_estimated_cost == 0) {
            return 0;
        }

        return (($this->total_cost - $this->total_estimated_cost) / $this->total_estimated_cost) * 100;
    }

    public function hasBeforePhoto(): bool
    {
        return ! empty($this->before_photo);
    }

    public function hasAfterPhoto(): bool
    {
        return ! empty($this->after_photo);
    }

    public function getDurationInDays(): ?int
    {
        if ($this->actual_start_date && $this->actual_end_date) {
            return $this->actual_start_date->diffInDays($this->actual_end_date);
        }

        if ($this->expected_start_date && $this->expected_end_date) {
            return $this->expected_start_date->diffInDays($this->expected_end_date);
        }

        return null;
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeOverdue($query)
    {
        return $query->where('expected_end_date', '<', now())
            ->where('status', '!=', self::STATUS_COMPLETED);
    }

    public function scopeForPatient($query, $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeByDoctor($query, $doctorId)
    {
        return $query->where('doctor_id', $doctorId);
    }
}
