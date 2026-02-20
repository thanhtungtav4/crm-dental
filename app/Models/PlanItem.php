<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanItem extends Model
{
    use HasFactory, SoftDeletes;

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

    // Helper Methods

    /**
     * Update progress based on completed visits
     */
    public function updateProgress(): void
    {
        if ($this->required_visits > 0) {
            $this->progress_percentage = (int) (($this->completed_visits / $this->required_visits) * 100);
        } else {
            // If no visits required, base on status
            $this->progress_percentage = match ($this->status) {
                'pending' => 0,
                'in_progress' => 50,
                'completed' => 100,
                'cancelled' => 0,
                default => 0,
            };
        }

        // Auto-update status based on progress
        if ($this->progress_percentage === 0 && $this->status === 'pending') {
            // Keep as pending
        } elseif ($this->progress_percentage > 0 && $this->progress_percentage < 100) {
            $this->status = 'in_progress';
            if (!$this->started_at) {
                $this->started_at = now()->toDateString();
            }
        } elseif ($this->progress_percentage === 100) {
            $this->status = 'completed';
            $this->completed_at = now()->toDateString();
        }

        $this->save();

        // Update parent treatment plan
        $this->treatmentPlan->updateProgress();
    }

    /**
     * Mark a visit as completed
     */
    public function completeVisit(): void
    {
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
        return match ($this->status) {
            'pending' => 'Chờ thực hiện',
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
        if (!$this->tooth_number) {
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
        if (!$this->tooth_number) {
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
        return $this->status === 'completed';
    }

    /**
     * Check if is in progress
     */
    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Scopes
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForTooth($query, $toothNumber)
    {
        return $query->where('tooth_number', 'like', "%{$toothNumber}%");
    }
}
