<?php

namespace App\Models;

use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'reversal_of_id',
        'amount',
        'direction',
        'is_deposit',
        'method',
        'transaction_ref',
        'payment_source',
        'insurance_claim_number',
        'paid_at',
        'received_by',
        'note',
        'refund_reason',
        'reversed_at',
        'reversed_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_deposit' => 'boolean',
        'paid_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $payment) {
            if ($payment->direction === 'refund') {
                ActionGate::authorize(
                    ActionPermission::PAYMENT_REVERSAL,
                    'Bạn không có quyền thực hiện hoàn tiền hoặc đảo phiếu thu.',
                );

                $payment->amount = -abs((float) $payment->amount);
            } else {
                $payment->amount = abs((float) $payment->amount);
            }

            if ($payment->reversal_of_id !== null) {
                ActionGate::authorize(
                    ActionPermission::PAYMENT_REVERSAL,
                    'Bạn không có quyền thực hiện hoàn tiền hoặc đảo phiếu thu.',
                );
            }

            if ($payment->transaction_ref !== null) {
                $payment->transaction_ref = trim((string) $payment->transaction_ref);

                if ($payment->transaction_ref === '') {
                    $payment->transaction_ref = null;
                }
            }
        });

        static::updating(function (): void {
            throw ValidationException::withMessages([
                'payment' => 'Phiếu thu/hoàn đã ghi sổ. Vui lòng tạo phiếu hoàn thay vì chỉnh sửa trực tiếp.',
            ]);
        });

        static::deleting(function (): void {
            throw ValidationException::withMessages([
                'payment' => 'Phiếu thu/hoàn đã ghi sổ. Vui lòng tạo phiếu hoàn thay vì xóa trực tiếp.',
            ]);
        });
    }

    // ==================== RELATIONSHIPS ====================

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversalEntries(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_id');
    }

    // ==================== HELPER METHODS ====================

    /**
     * Get payment method label in Vietnamese
     */
    public function getMethodLabel(): string
    {
        $labels = ClinicRuntimeSettings::paymentMethodLabels();

        return $labels[$this->method] ?? 'Không xác định';
    }

    /**
     * Get payment method icon
     */
    public function getMethodIcon(): string
    {
        return ClinicRuntimeSettings::paymentMethodIcon($this->method);
    }

    /**
     * Get payment source label in Vietnamese
     */
    public function getSourceLabel(): string
    {
        return match ($this->payment_source) {
            'patient' => 'Bệnh nhân',
            'insurance' => 'Bảo hiểm',
            'other' => 'Khác',
            default => 'Không xác định',
        };
    }

    /**
     * Format amount as VNĐ
     */
    public function formatAmount(): string
    {
        return number_format($this->amount, 0, ',', '.').'đ';
    }

    public function getDirectionLabel(): string
    {
        return match ($this->direction) {
            'refund' => 'Hoàn',
            default => 'Thu',
        };
    }

    public function getSignedAmountAttribute(): float
    {
        return (float) $this->amount;
    }

    public function isRefund(): bool
    {
        return $this->direction === 'refund' || (float) $this->amount < 0;
    }

    public function isDeposit(): bool
    {
        return (bool) $this->is_deposit;
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null || $this->reversed_by !== null;
    }

    public function isReversal(): bool
    {
        return $this->reversal_of_id !== null;
    }

    public function canReverse(): bool
    {
        return ! $this->isRefund() && ! $this->isReversal() && ! $this->isReversed();
    }

    public function markReversed(?int $userId = null): void
    {
        if ($this->isReversed()) {
            return;
        }

        $now = now();

        self::query()
            ->whereKey($this->id)
            ->update([
                'reversed_at' => $now,
                'reversed_by' => $userId,
                'updated_at' => $now,
            ]);

        $this->refresh();
    }

    /**
     * Check if this is an insurance claim
     */
    public function isInsuranceClaim(): bool
    {
        return $this->payment_source === 'insurance';
    }

    /**
     * Check if transaction reference exists
     */
    public function hasTransactionRef(): bool
    {
        return ! empty($this->transaction_ref);
    }

    /**
     * Get badge color by payment method
     */
    public function getMethodBadgeColor(): string
    {
        return ClinicRuntimeSettings::paymentMethodColor($this->method);
    }

    /**
     * Get badge color by payment source
     */
    public function getSourceBadgeColor(): string
    {
        return match ($this->payment_source) {
            'patient' => 'success',
            'insurance' => 'info',
            'other' => 'gray',
            default => 'gray',
        };
    }

    // ==================== SCOPES ====================

    public function scopeByMethod($query, string $method)
    {
        return $query->where('method', $method);
    }

    public function scopeCash($query)
    {
        return $query->where('method', 'cash');
    }

    public function scopeCard($query)
    {
        return $query->where('method', 'card');
    }

    public function scopeTransfer($query)
    {
        return $query->where('method', 'transfer');
    }

    public function scopeInsuranceOnly($query)
    {
        return $query->where('payment_source', 'insurance');
    }

    public function scopeForPatient($query, int $patientId)
    {
        return $query->whereHas('invoice', function ($q) use ($patientId) {
            $q->where('patient_id', $patientId);
        });
    }

    public function scopeReceipts($query)
    {
        return $query->where('direction', 'receipt');
    }

    public function scopeRefunds($query)
    {
        return $query->where('direction', 'refund');
    }

    public function scopeByDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('paid_at', [$startDate, $endDate]);
    }

    public function scopeByTransactionRef($query, string $transactionRef)
    {
        return $query->where('transaction_ref', trim($transactionRef));
    }

    public function scopeToday($query)
    {
        return $query->whereDate('paid_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('paid_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year);
    }
}
