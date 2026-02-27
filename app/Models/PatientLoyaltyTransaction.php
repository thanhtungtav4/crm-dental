<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientLoyaltyTransaction extends Model
{
    /** @use HasFactory<\Database\Factories\PatientLoyaltyTransactionFactory> */
    use HasFactory;

    public const EVENT_REVENUE_EARN = 'revenue_earn';

    public const EVENT_REFERRAL_BONUS_REFERRER = 'referral_bonus_referrer';

    public const EVENT_REFERRAL_BONUS_REFEREE = 'referral_bonus_referee';

    public const EVENT_REACTIVATION_BONUS = 'reactivation_bonus';

    public const EVENT_REDEEM = 'redeem';

    public const EVENT_MANUAL_ADJUST = 'manual_adjust';

    protected $fillable = [
        'patient_loyalty_id',
        'patient_id',
        'event_type',
        'points_delta',
        'amount',
        'source_type',
        'source_id',
        'occurred_at',
        'created_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function loyalty(): BelongsTo
    {
        return $this->belongsTo(PatientLoyalty::class, 'patient_loyalty_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
