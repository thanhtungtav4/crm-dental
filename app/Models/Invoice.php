<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'treatment_session_id',
        'treatment_plan_id',
        'patient_id',
        'invoice_no',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'qr_code',
        'status',
        'issued_at',
        'due_date',
        'paid_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'issued_at' => 'datetime',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    // ==================== RELATIONSHIPS ====================

    public function session(): BelongsTo
    {
        return $this->belongsTo(TreatmentSession::class, 'treatment_session_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class, 'treatment_plan_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function installmentPlan(): HasOne
    {
        return $this->hasOne(InstallmentPlan::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(PaymentReminder::class);
    }

    // ==================== PAYMENT TRACKING METHODS ====================

    /**
     * Calculate remaining balance after all payments
     */
    public function calculateBalance(): float
    {
        return max(0, $this->total_amount - $this->getTotalPaid());
    }

    /**
     * Get total amount paid from all payments
     */
    public function getTotalPaid(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    /**
     * Update paid_amount field based on payments
     */
    public function updatePaidAmount(): void
    {
        $this->paid_amount = $this->getTotalPaid();
        $this->updatePaymentStatus();
        $this->save();
    }

    /**
     * Auto-update status based on payment progress
     */
    public function updatePaymentStatus(): void
    {
        $totalPaid = $this->paid_amount ?? $this->getTotalPaid();
        
        if ($totalPaid >= $this->total_amount) {
            $this->status = 'paid';
            $this->paid_at = $this->paid_at ?? now();
        } elseif ($totalPaid > 0) {
            $this->status = 'partial';
        } elseif ($this->isOverdue()) {
            // Don't change if already in terminal status
            if (!in_array($this->status, ['paid', 'cancelled'])) {
                // Keep current status but mark as overdue in UI
            }
        }
    }

    /**
     * Record a payment and update status
     */
    public function recordPayment(float $amount, string $method = 'cash', ?string $notes = null): Payment
    {
        $payment = $this->payments()->create([
            'amount' => $amount,
            'method' => $method,
            'paid_at' => now(),
            'received_by' => auth()->id(),
            'note' => $notes,
            'payment_source' => 'patient',
        ]);

        $this->updatePaidAmount();

        return $payment;
    }

    /**
     * Check if invoice is overdue
     */
    public function isOverdue(): bool
    {
        return $this->due_date 
            && now()->isAfter($this->due_date) 
            && !$this->isPaid();
    }

    /**
     * Check if invoice is fully paid
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid' || $this->calculateBalance() <= 0;
    }

    /**
     * Check if invoice is partially paid
     */
    public function isPartiallyPaid(): bool
    {
        $totalPaid = $this->getTotalPaid();
        return $totalPaid > 0 && $totalPaid < $this->total_amount;
    }

    /**
     * Check if invoice has any payments
     */
    public function hasPayments(): bool
    {
        return $this->payments()->exists();
    }

    /**
     * Get days overdue (negative if not due yet)
     */
    public function getDaysOverdue(): int
    {
        if (!$this->due_date) {
            return 0;
        }

        return (int) now()->diffInDays($this->due_date, false);
    }

    /**
     * Get payment progress percentage (0-100)
     */
    public function getPaymentProgress(): float
    {
        if ($this->total_amount <= 0) {
            return 0;
        }

        return round(($this->getTotalPaid() / $this->total_amount) * 100, 2);
    }

    /**
     * Get payment status badge color
     */
    public function getStatusBadgeColor(): string
    {
        if ($this->isOverdue()) {
            return 'danger';
        }

        return match($this->status) {
            'draft' => 'gray',
            'issued' => 'warning',
            'partial' => 'info',
            'paid' => 'success',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get payment status label in Vietnamese
     */
    public function getPaymentStatusLabel(): string
    {
        if ($this->isOverdue()) {
            return 'Quá hạn';
        }

        if ($this->isPaid()) {
            return 'Đã thanh toán';
        }

        if ($this->isPartiallyPaid()) {
            return 'TT một phần';
        }

        return 'Chưa thanh toán';
    }

    /**
     * Get breakdown of payments by method
     */
    public function getPaymentMethodBreakdown(): array
    {
        return $this->payments()
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->get()
            ->mapWithKeys(function ($payment) {
                return [$payment->method => (float) $payment->total];
            })
            ->toArray();
    }

    /**
     * Check if has installment plan
     */
    public function hasInstallmentPlan(): bool
    {
        return $this->installmentPlan()->exists();
    }

    /**
     * Format balance as VNĐ
     */
    public function formatBalance(): string
    {
        return number_format($this->calculateBalance(), 0, ',', '.') . 'đ';
    }

    /**
     * Format total paid as VNĐ
     */
    public function formatTotalPaid(): string
    {
        return number_format($this->getTotalPaid(), 0, ',', '.') . 'đ';
    }

    // ==================== SCOPES ====================

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
                     ->whereNotIn('status', ['paid', 'cancelled'])
                     ->whereColumn('paid_amount', '<', 'total_amount');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('paid_amount', 0)
                     ->whereNotIn('status', ['paid', 'cancelled']);
    }

    public function scopePartiallyPaid($query)
    {
        return $query->where('paid_amount', '>', 0)
                     ->whereColumn('paid_amount', '<', 'total_amount')
                     ->whereNotIn('status', ['paid', 'cancelled']);
    }

    public function scopeFullyPaid($query)
    {
        return $query->where(function($q) {
            $q->where('status', 'paid')
              ->orWhereColumn('paid_amount', '>=', 'total_amount');
        });
    }

    public function scopeByPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeByPlan($query, int $planId)
    {
        return $query->where('treatment_plan_id', $planId);
    }

    public function scopeHasInstallmentPlan($query)
    {
        return $query->whereHas('installmentPlan');
    }
}
