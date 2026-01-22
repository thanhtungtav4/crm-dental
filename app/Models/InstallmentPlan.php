<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InstallmentPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'number_of_installments',
        'installment_amount',
        'interest_rate',
        'start_date',
        'end_date',
        'payment_frequency',
        'schedule',
        'status',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'schedule' => 'array',
    ];

    // ==================== RELATIONSHIPS ====================

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Auto-generate installment schedule based on frequency
     */
    public function calculateSchedule(): array
    {
        $schedule = [];
        $currentDate = Carbon::parse($this->start_date);
        
        for ($i = 1; $i <= $this->number_of_installments; $i++) {
            $schedule[] = [
                'installment_number' => $i,
                'due_date' => $currentDate->format('Y-m-d'),
                'amount' => $this->installment_amount,
                'status' => 'pending', // pending, paid, overdue
            ];
            
            // Increment date based on frequency
            if ($this->payment_frequency === 'monthly') {
                $currentDate->addMonth();
            } elseif ($this->payment_frequency === 'weekly') {
                $currentDate->addWeek();
            }
        }
        
        return $schedule;
    }

    /**
     * Get the next unpaid installment
     */
    public function getNextDueInstallment(): ?array
    {
        if (!$this->schedule) {
            return null;
        }

        foreach ($this->schedule as $installment) {
            if ($installment['status'] === 'pending' || $installment['status'] === 'overdue') {
                return $installment;
            }
        }

        return null;
    }

    /**
     * Record a payment for this installment plan
     */
    public function recordInstallmentPayment(float $amount): void
    {
        $this->paid_amount += $amount;
        $this->remaining_amount = $this->total_amount - $this->paid_amount;
        
        // Update status if fully paid
        if ($this->remaining_amount <= 0) {
            $this->status = 'completed';
        }
        
        // Update schedule to mark installments as paid
        $schedule = $this->schedule ?? [];
        $remainingAmount = $amount;
        
        foreach ($schedule as &$installment) {
            if ($installment['status'] === 'pending' && $remainingAmount > 0) {
                if ($remainingAmount >= $installment['amount']) {
                    $installment['status'] = 'paid';
                    $remainingAmount -= $installment['amount'];
                } else {
                    // Partial payment of this installment
                    break;
                }
            }
        }
        
        $this->schedule = $schedule;
        $this->save();
    }

    /**
     * Get completion percentage (0-100)
     */
    public function getCompletionPercentage(): float
    {
        if ($this->total_amount <= 0) {
            return 0;
        }
        
        return round(($this->paid_amount / $this->total_amount) * 100, 2);
    }

    /**
     * Check if plan is defaulted (overdue > 30 days)
     */
    public function isDefaulted(): bool
    {
        if ($this->status === 'defaulted') {
            return true;
        }

        $nextDue = $this->getNextDueInstallment();
        if (!$nextDue) {
            return false;
        }

        $dueDate = Carbon::parse($nextDue['due_date']);
        return now()->diffInDays($dueDate, false) < -30; // More than 30 days overdue
    }

    /**
     * Get status label in Vietnamese
     */
    public function getStatusLabel(): string
    {
        return match($this->status) {
            'active' => 'Đang hoạt động',
            'completed' => 'Hoàn thành',
            'defaulted' => 'Nợ quá hạn',
            'cancelled' => 'Đã hủy',
            default => 'Không xác định',
        };
    }

    /**
     * Get status badge color
     */
    public function getStatusBadgeColor(): string
    {
        return match($this->status) {
            'active' => 'info',
            'completed' => 'success',
            'defaulted' => 'danger',
            'cancelled' => 'gray',
            default => 'warning',
        };
    }

    /**
     * Get payment frequency label in Vietnamese
     */
    public function getFrequencyLabel(): string
    {
        return match($this->payment_frequency) {
            'monthly' => 'Hàng tháng',
            'weekly' => 'Hàng tuần',
            'custom' => 'Tùy chỉnh',
            default => 'Không xác định',
        };
    }

    // ==================== SCOPES ====================

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeOverdue($query)
    {
        return $query->whereHas('invoice', function ($q) {
            $q->where('due_date', '<', now())
              ->whereColumn('paid_amount', '<', 'total_amount');
        });
    }

    public function scopeByPatient($query, int $patientId)
    {
        return $query->whereHas('invoice', function ($q) use ($patientId) {
            $q->where('patient_id', $patientId);
        });
    }
}
