<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TreatmentPlan extends Model
{
    use HasFactory, SoftDeletes;

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

    public function sessions()
    {
        return $this->hasMany(TreatmentSession::class);
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

    // Helper Methods

    /**
     * Calculate overall progress based on plan items
     */
    public function calculateProgress(): int
    {
        $items = $this->planItems;

        if ($items->isEmpty()) {
            return 0;
        }

        $totalProgress = $items->sum('progress_percentage');
        return (int) ($totalProgress / $items->count());
    }

    /**
     * Update progress percentage and sync with items
     */
    public function updateProgress(): void
    {
        $this->progress_percentage = $this->calculateProgress();

        // Count completed visits
        $this->completed_visits = $this->planItems->sum('completed_visits');
        $this->total_visits = $this->planItems->sum('required_visits');

        // Calculate actual costs
        $this->total_cost = $this->planItems->sum('actual_cost');
        $this->total_estimated_cost = $this->planItems->sum('estimated_cost');

        // Auto-update status based on progress
        if ($this->progress_percentage === 0 && $this->status === 'draft') {
            // Keep as draft
        } elseif ($this->progress_percentage > 0 && $this->progress_percentage < 100) {
            $this->status = 'in_progress';
        } elseif ($this->progress_percentage === 100) {
            $this->status = 'completed';
            $this->actual_end_date = now()->toDateString();
        }

        $this->save();
    }

    /**
     * Get progress status badge color
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
     * Get status label in Vietnamese
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Nháp',
            'approved' => 'Đã duyệt',
            'in_progress' => 'Đang thực hiện',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Đã hủy',
            default => $this->status,
        };
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
     * Check if plan is overdue
     */
    public function isOverdue(): bool
    {
        if (!$this->expected_end_date) {
            return false;
        }

        return $this->expected_end_date->isPast() && $this->status !== 'completed';
    }

    /**
     * Get cost variance (actual vs estimated)
     */
    public function getCostVariance(): float
    {
        return (float) ($this->total_cost - $this->total_estimated_cost);
    }

    /**
     * Get cost variance percentage
     */
    public function getCostVariancePercentage(): float
    {
        if ($this->total_estimated_cost == 0) {
            return 0;
        }

        return (($this->total_cost - $this->total_estimated_cost) / $this->total_estimated_cost) * 100;
    }

    /**
     * Check if has before photo
     */
    public function hasBeforePhoto(): bool
    {
        return !empty($this->before_photo);
    }

    /**
     * Check if has after photo
     */
    public function hasAfterPhoto(): bool
    {
        return !empty($this->after_photo);
    }

    /**
     * Get duration in days (actual or expected)
     */
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

    /**
     * Scopes
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeOverdue($query)
    {
        return $query->where('expected_end_date', '<', now())
            ->where('status', '!=', 'completed');
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
