<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_CANCELLED = 'cancelled';

    public const TERMINAL_STATUSES = [
        self::STATUS_PAID,
        self::STATUS_CANCELLED,
    ];

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
        'invoice_exported',
        'exported_at',
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
        'invoice_exported' => 'boolean',
        'exported_at' => 'datetime',
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

        if ($this->isDirty(['paid_amount', 'status', 'paid_at'])) {
            $this->save();
        }
    }

    /**
     * Auto-update status based on payment progress
     */
    public function updatePaymentStatus(): void
    {
        $totalPaid = round((float) ($this->paid_amount ?? $this->getTotalPaid()), 2);
        $totalAmount = round((float) $this->total_amount, 2);

        if ($this->status === self::STATUS_CANCELLED) {
            return;
        }

        if ($totalAmount <= 0 || $totalPaid >= $totalAmount) {
            $this->status = self::STATUS_PAID;
            $this->paid_at = $this->paid_at ?? now();

            return;
        }

        $isPastDue = false;
        if ($this->due_date) {
            $dueDate = $this->due_date instanceof Carbon
                ? $this->due_date->copy()->startOfDay()
                : Carbon::parse($this->due_date)->startOfDay();

            $isPastDue = today()->gt($dueDate);
        }

        if ($totalPaid > 0) {
            $this->status = $isPastDue
                ? self::STATUS_OVERDUE
                : self::STATUS_PARTIAL;
            $this->paid_at = null;

            return;
        }

        if ($this->status !== self::STATUS_DRAFT) {
            $this->status = $isPastDue
                ? self::STATUS_OVERDUE
                : self::STATUS_ISSUED;
        }

        $this->paid_at = null;
    }

    /**
     * Record a payment and update status
     */
    public function recordPayment(
        float $amount,
        string $method = 'cash',
        ?string $notes = null,
        mixed $paidAt = null,
        string $direction = 'receipt',
        ?string $refundReason = null,
        ?string $transactionRef = null,
        string $paymentSource = 'patient',
        ?string $insuranceClaimNumber = null,
        ?int $receivedBy = null
    ): Payment
    {
        $amount = $direction === 'refund' ? -abs($amount) : abs($amount);
        $transactionRef = filled($transactionRef) ? trim($transactionRef) : null;

        return DB::transaction(function () use (
            $amount,
            $direction,
            $method,
            $paidAt,
            $receivedBy,
            $notes,
            $refundReason,
            $transactionRef,
            $paymentSource,
            $insuranceClaimNumber
        ): Payment {
            if ($transactionRef) {
                $existingPayment = $this->payments()
                    ->where('transaction_ref', $transactionRef)
                    ->first();

                if ($existingPayment) {
                    $this->updatePaidAmount();

                    return $existingPayment;
                }
            }

            $payload = [
                'amount' => $amount,
                'direction' => $direction,
                'method' => $method,
                'paid_at' => $paidAt ?: now(),
                'received_by' => $receivedBy ?: auth()->id(),
                'note' => $notes,
                'refund_reason' => $refundReason,
                'payment_source' => $paymentSource,
                'insurance_claim_number' => $insuranceClaimNumber,
                'transaction_ref' => $transactionRef,
            ];

            try {
                $payment = $this->payments()->create($payload);
            } catch (QueryException $exception) {
                $isDuplicateTransaction = str_contains((string) $exception->getCode(), '23000');

                if (! $isDuplicateTransaction || ! $transactionRef) {
                    throw $exception;
                }

                $payment = $this->payments()
                    ->where('transaction_ref', $transactionRef)
                    ->firstOrFail();
            }

            $this->updatePaidAmount();

            return $payment;
        });
    }

    /**
     * Check if invoice is overdue
     */
    public function isOverdue(): bool
    {
        if ($this->status === self::STATUS_OVERDUE) {
            return true;
        }

        if (
            in_array($this->status, [self::STATUS_DRAFT, self::STATUS_CANCELLED], true)
            || ! $this->due_date
        ) {
            return false;
        }

        $dueDate = $this->due_date instanceof Carbon
            ? $this->due_date->copy()->startOfDay()
            : Carbon::parse($this->due_date)->startOfDay();

        return today()->gt($dueDate) && ! $this->isPaid();
    }

    /**
     * Check if invoice is fully paid
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID || $this->calculateBalance() <= 0;
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

        $dueDate = $this->due_date instanceof Carbon
            ? $this->due_date->copy()->startOfDay()
            : Carbon::parse($this->due_date)->startOfDay();

        if (today()->gt($dueDate)) {
            return (int) $dueDate->diffInDays(today());
        }

        return -1 * (int) today()->diffInDays($dueDate);
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
        return match($this->status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_ISSUED => 'warning',
            self::STATUS_PARTIAL => 'info',
            self::STATUS_PAID => 'success',
            self::STATUS_OVERDUE => 'danger',
            self::STATUS_CANCELLED => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get payment status label in Vietnamese
     */
    public function getPaymentStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Nháp',
            self::STATUS_ISSUED => 'Chưa thanh toán',
            self::STATUS_PARTIAL => 'TT một phần',
            self::STATUS_PAID => 'Đã thanh toán',
            self::STATUS_OVERDUE => 'Quá hạn',
            self::STATUS_CANCELLED => 'Đã hủy',
            default => 'Không xác định',
        };
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
        return $query->where('status', self::STATUS_OVERDUE);
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

}
