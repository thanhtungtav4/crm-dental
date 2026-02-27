<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PatientLoyalty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientLoyalty>
 */
class PatientLoyaltyFactory extends Factory
{
    protected $model = PatientLoyalty::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'referral_code' => strtoupper(fake()->bothify('REF####??')),
            'referral_code_used' => null,
            'referred_by_patient_id' => null,
            'referred_at' => null,
            'tier' => PatientLoyalty::TIER_BRONZE,
            'points_balance' => 0,
            'lifetime_points_earned' => 0,
            'lifetime_points_redeemed' => 0,
            'lifetime_revenue' => 0,
            'last_reactivation_at' => null,
            'last_activity_at' => null,
            'metadata' => null,
        ];
    }
}
