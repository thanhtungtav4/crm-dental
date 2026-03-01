<?php

namespace App\Models;

use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\BranchAccess;
use App\Support\ClinicRuntimeSettings;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Nháp',
        self::STATUS_ISSUED => 'Đã xuất',
        self::STATUS_PARTIAL => 'Thanh toán một phần',
        self::STATUS_PAID => 'Đã thanh toán',
        self::STATUS_OVERDUE => 'Quá hạn',
        self::STATUS_CANCELLED => 'Đã hủy',
    ];

    public const EDITABLE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ISSUED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'treatment_session_id',
        'treatment_plan_id',
        'patient_id',
        'branch_id',
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
        'branch_id' => 'integer',
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

    protected static function booted(): void
    {
        static::saving(function (self $invoice): void {
            if (blank($invoice->invoice_no)) {
                $invoice->invoice_no = static::generateInvoiceNo();
            }

            $invoice->status = static::normalizeStatus((string) $invoice->status);

            static::syncRelationalContext($invoice);

            if (is_numeric($invoice->branch_id)) {
                BranchAccess::assertCanAccessBranch(
                    branchId: (int) $invoice->branch_id,
                    field: 'branch_id',
                    message: 'Bạn không có quyền thao tác hóa đơn ở chi nhánh này.',
                );
            }

            $shouldRecalculateTotal = $invoice->isDirty([
                'subtotal',
                'discount_amount',
                'tax_amount',
            ]);

            $subtotal = static::normalizeMonetaryValue($invoice->subtotal);
            $discountAmount = static::normalizeMonetaryValue($invoice->discount_amount);
            $taxAmount = static::normalizeMonetaryValue($invoice->tax_amount);

            $invoice->subtotal = $subtotal;
            $invoice->discount_amount = $discountAmount;
            $invoice->tax_amount = $taxAmount;

            if ($shouldRecalculateTotal || blank($invoice->total_amount)) {
                $invoice->total_amount = static::calculateTotalAmount($subtotal, $discountAmount, $taxAmount);
            } else {
                $invoice->total_amount = static::normalizeMonetaryValue($invoice->total_amount);
            }

            $invoice->paid_amount = static::normalizeMonetaryValue($invoice->paid_amount);

            if ($invoice->status === self::STATUS_DRAFT) {
                $invoice->issued_at = null;

                return;
            }

            if (blank($invoice->issued_at)) {
                $invoice->issued_at = now();
            }
        });

        static::updated(function (self $invoice): void {
            $actorId = auth()->id();

            if (! $actorId) {
                return;
            }

            if ($invoice->wasChanged('status') && $invoice->status === self::STATUS_CANCELLED) {
                AuditLog::record(
                    entityType: AuditLog::ENTITY_INVOICE,
                    entityId: $invoice->id,
                    action: AuditLog::ACTION_CANCEL,
                    actorId: $actorId,
                    metadata: [
                        'patient_id' => $invoice->patient_id,
                        'invoice_no' => $invoice->invoice_no,
                        'previous_status' => $invoice->getOriginal('status'),
                    ]
                );

                return;
            }

            $changes = $invoice->getChanges();
            $ignored = ['paid_amount', 'status', 'paid_at', 'updated_at'];
            $auditable = array_diff_key($changes, array_flip($ignored));

            if ($auditable === []) {
                return;
            }

            $fields = [];
            foreach ($auditable as $field => $newValue) {
                $fields[$field] = [
                    'from' => $invoice->getOriginal($field),
                    'to' => $newValue,
                ];
            }

            AuditLog::record(
                entityType: AuditLog::ENTITY_INVOICE,
                entityId: $invoice->id,
                action: AuditLog::ACTION_UPDATE,
                actorId: $actorId,
                metadata: [
                    'patient_id' => $invoice->patient_id,
                    'invoice_no' => $invoice->invoice_no,
                    'changes' => $fields,
                ]
            );
        });
    }

    public static function statusOptions(): array
    {
        return self::STATUS_LABELS;
    }

    public static function formStatusOptions(): array
    {
        return array_intersect_key(self::STATUS_LABELS, array_flip(self::EDITABLE_STATUSES));
    }

    public static function generateInvoiceNo(): string
    {
        $prefix = 'INV-'.now()->format('Ymd').'-';

        return Cache::lock("invoice_no:{$prefix}", 5)->block(5, function () use ($prefix): string {
            $latestInvoiceNo = static::withTrashed()
                ->where('invoice_no', 'like', "{$prefix}%")
                ->orderByDesc('invoice_no')
                ->value('invoice_no');

            $nextSequence = 1;
            if (is_string($latestInvoiceNo) && preg_match('/(\d{4})$/', $latestInvoiceNo, $matches) === 1) {
                $nextSequence = ((int) $matches[1]) + 1;
            }

            return $prefix.str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
        });
    }

    public static function calculateTotalAmount(
        float $subtotal,
        float $discountAmount = 0,
        float $taxAmount = 0
    ): float {
        return round(max(0, $subtotal - $discountAmount + $taxAmount), 2);
    }

    private static function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        return array_key_exists($normalized, self::STATUS_LABELS)
            ? $normalized
            : self::STATUS_DRAFT;
    }

    private static function syncRelationalContext(self $invoice): void
    {
        if ($invoice->treatment_session_id && blank($invoice->treatment_plan_id)) {
            $invoice->treatment_plan_id = TreatmentSession::query()
                ->whereKey((int) $invoice->treatment_session_id)
                ->value('treatment_plan_id');
        }

        if ($invoice->treatment_plan_id && blank($invoice->patient_id)) {
            $invoice->patient_id = TreatmentPlan::query()
                ->whereKey((int) $invoice->treatment_plan_id)
                ->value('patient_id');
        }

        if ($invoice->treatment_plan_id && blank($invoice->branch_id)) {
            $invoice->branch_id = TreatmentPlan::query()
                ->whereKey((int) $invoice->treatment_plan_id)
                ->value('branch_id');
        }

        if ($invoice->patient_id && blank($invoice->branch_id)) {
            $invoice->branch_id = Patient::query()
                ->whereKey((int) $invoice->patient_id)
                ->value('first_branch_id');
        }
    }

    private static function normalizeMonetaryValue(mixed $value): float
    {
        return max(0, round((float) $value, 2));
    }

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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function installmentPlan(): HasOne
    {
        return $this->hasOne(InstallmentPlan::class);
    }

    public function insuranceClaims(): HasMany
    {
        return $this->hasMany(InsuranceClaim::class);
    }

    public function resolveBranchId(): ?int
    {
        $branchId = $this->branch_id ?? $this->plan?->branch_id ?? $this->patient?->first_branch_id;

        return $branchId !== null ? (int) $branchId : null;
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
        ?int $receivedBy = null,
        ?int $reversalOfId = null,
        bool $isDeposit = false
    ): Payment {
        $amount = $direction === 'refund' ? -abs($amount) : abs($amount);
        $transactionRef = filled($transactionRef) ? trim($transactionRef) : null;

        if ($direction === 'refund' || $reversalOfId !== null) {
            ActionGate::authorize(
                ActionPermission::PAYMENT_REVERSAL,
                'Bạn không có quyền thực hiện hoàn tiền hoặc đảo phiếu thu.',
            );
        }

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
            $insuranceClaimNumber,
            $reversalOfId,
            $isDeposit
        ): Payment {
            $lockedInvoice = self::query()
                ->whereKey($this->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($direction === 'receipt') {
                if ($lockedInvoice->status === self::STATUS_DRAFT && ! ClinicRuntimeSettings::allowDraftPrepay()) {
                    throw ValidationException::withMessages([
                        'invoice_id' => 'Chính sách hiện tại không cho phép thu trước trên hóa đơn nháp.',
                    ]);
                }

                if ($isDeposit && ! ClinicRuntimeSettings::allowDeposit()) {
                    throw ValidationException::withMessages([
                        'is_deposit' => 'Chính sách hiện tại không cho phép ghi nhận tiền cọc.',
                    ]);
                }
            }

            if ($transactionRef) {
                $existingPayment = $lockedInvoice->payments()
                    ->where('transaction_ref', $transactionRef)
                    ->lockForUpdate()
                    ->first();

                if ($existingPayment) {
                    $lockedInvoice->updatePaidAmount();
                    $this->refresh();

                    return $existingPayment;
                }
            }

            if ($direction === 'receipt' && ! ClinicRuntimeSettings::allowOverpay()) {
                $currentPaid = (float) $lockedInvoice->payments()
                    ->select(['id', 'amount'])
                    ->lockForUpdate()
                    ->get()
                    ->sum('amount');

                $balance = round(max(0, (float) $lockedInvoice->total_amount - $currentPaid), 2);

                if ($amount > $balance) {
                    throw ValidationException::withMessages([
                        'amount' => 'Số tiền thu vượt công nợ hiện tại theo chính sách chống overpay.',
                    ]);
                }
            }

            $payload = [
                'amount' => $amount,
                'direction' => $direction,
                'method' => $method,
                'paid_at' => $paidAt ?: now(),
                'received_by' => $receivedBy ?: auth()->id(),
                'branch_id' => $lockedInvoice->resolveBranchId(),
                'note' => $notes,
                'refund_reason' => $refundReason,
                'payment_source' => $paymentSource,
                'insurance_claim_number' => $insuranceClaimNumber,
                'transaction_ref' => $transactionRef,
                'reversal_of_id' => $reversalOfId,
                'is_deposit' => $isDeposit,
            ];

            try {
                $payment = $lockedInvoice->payments()->create($payload);
            } catch (QueryException $exception) {
                $isDuplicateTransaction = str_contains((string) $exception->getCode(), '23000');

                if (! $isDuplicateTransaction || ! $transactionRef) {
                    throw $exception;
                }

                $payment = $lockedInvoice->payments()
                    ->where('transaction_ref', $transactionRef)
                    ->firstOrFail();
            }

            $lockedInvoice->updatePaidAmount();
            $this->refresh();

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
        if (! $this->due_date) {
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
        return match ($this->status) {
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
            self::STATUS_ISSUED => 'Chưa thanh toán',
            self::STATUS_PARTIAL => 'TT một phần',
            default => self::STATUS_LABELS[$this->status] ?? 'Không xác định',
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
        return number_format($this->calculateBalance(), 0, ',', '.').'đ';
    }

    /**
     * Format total paid as VNĐ
     */
    public function formatTotalPaid(): string
    {
        return number_format($this->getTotalPaid(), 0, ',', '.').'đ';
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
        return $query->where(function ($q) {
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
