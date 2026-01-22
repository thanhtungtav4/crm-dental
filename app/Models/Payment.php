<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'amount',
        'method',
        'transaction_ref',
        'payment_source',
        'insurance_claim_number',
        'paid_at',
        'received_by',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    // ==================== RELATIONSHIPS ====================

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    // ==================== HELPER METHODS ====================

    /**
     * Get payment method label in Vietnamese
     */
    public function getMethodLabel(): string
    {
        return match($this->method) {
            'cash' => 'Tiền mặt',
            'card' => 'Thẻ tín dụng/ghi nợ',
            'transfer' => 'Chuyển khoản',
            'other' => 'Khác',
            default => 'Không xác định',
        };
    }

    /**
     * Get payment method icon
     */
    public function getMethodIcon(): string
    {
        return match($this->method) {
            'cash' => 'heroicon-o-banknotes',
            'card' => 'heroicon-o-credit-card',
            'transfer' => 'heroicon-o-arrow-path',
            'other' => 'heroicon-o-ellipsis-horizontal-circle',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    /**
     * Get payment source label in Vietnamese
     */
    public function getSourceLabel(): string
    {
        return match($this->payment_source) {
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
        return number_format($this->amount, 0, ',', '.') . 'đ';
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
        return !empty($this->transaction_ref);
    }

    /**
     * Get badge color by payment method
     */
    public function getMethodBadgeColor(): string
    {
        return match($this->method) {
            'cash' => 'success',
            'card' => 'info',
            'transfer' => 'warning',
            'other' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get badge color by payment source
     */
    public function getSourceBadgeColor(): string
    {
        return match($this->payment_source) {
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

    public function scopeByDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('paid_at', [$startDate, $endDate]);
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
