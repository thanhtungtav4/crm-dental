<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientLoyalty extends Model
{
    /** @use HasFactory<\Database\Factories\PatientLoyaltyFactory> */
    use HasFactory;

    public const TIER_BRONZE = 'bronze';

    public const TIER_SILVER = 'silver';

    public const TIER_GOLD = 'gold';

    public const TIER_PLATINUM = 'platinum';

    protected $fillable = [
        'patient_id',
        'referral_code',
        'referral_code_used',
        'referred_by_patient_id',
        'referred_at',
        'tier',
        'points_balance',
        'lifetime_points_earned',
        'lifetime_points_redeemed',
        'lifetime_revenue',
        'last_reactivation_at',
        'last_activity_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'referred_at' => 'datetime',
            'lifetime_revenue' => 'decimal:2',
            'last_reactivation_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function referredByPatient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'referred_by_patient_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PatientLoyaltyTransaction::class);
    }
}
