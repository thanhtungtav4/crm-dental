<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptExpense extends Model
{
    use HasFactory;

    protected $table = 'receipts_expense';

    protected $fillable = [
        'clinic_id',
        'voucher_code',
        'voucher_type',
        'voucher_date',
        'group_code',
        'category_code',
        'amount',
        'payment_method',
        'payer_or_receiver',
        'content',
        'status',
        'posted_at',
        'posted_by',
    ];

    protected $casts = [
        'voucher_date' => 'date',
        'amount' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'clinic_id');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function getVoucherTypeLabel(): string
    {
        return match ($this->voucher_type) {
            'expense' => 'Phiếu chi',
            default => 'Phiếu thu',
        };
    }

    public function getPaymentMethodLabel(): string
    {
        return match ($this->payment_method) {
            'cash' => 'Tiền mặt',
            'transfer' => 'Chuyển khoản',
            'card' => 'Thẻ',
            default => 'Khác',
        };
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'approved' => 'Đã duyệt',
            'posted' => 'Đã hạch toán',
            'cancelled' => 'Đã hủy',
            default => 'Nháp',
        };
    }
}
