<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletLedgerEntry extends Model
{
    public const DIRECTION_CREDIT = 'credit';

    public const DIRECTION_DEBIT = 'debit';

    protected $fillable = [
        'patient_wallet_id',
        'patient_id',
        'branch_id',
        'payment_id',
        'invoice_id',
        'entry_type',
        'direction',
        'amount',
        'balance_before',
        'balance_after',
        'reference_no',
        'note',
        'metadata',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'patient_wallet_id' => 'integer',
            'patient_id' => 'integer',
            'branch_id' => 'integer',
            'payment_id' => 'integer',
            'invoice_id' => 'integer',
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'metadata' => 'array',
            'created_by' => 'integer',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(PatientWallet::class, 'patient_wallet_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
