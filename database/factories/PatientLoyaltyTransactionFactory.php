<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PatientLoyalty;
use App\Models\PatientLoyaltyTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientLoyaltyTransaction>
 */
class PatientLoyaltyTransactionFactory extends Factory
{
    protected $model = PatientLoyaltyTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_loyalty_id' => PatientLoyalty::factory(),
            'patient_id' => Patient::factory(),
            'event_type' => PatientLoyaltyTransaction::EVENT_MANUAL_ADJUST,
            'points_delta' => fake()->numberBetween(-50, 150),
            'amount' => fake()->randomFloat(2, 0, 5000000),
            'source_type' => null,
            'source_id' => null,
            'occurred_at' => now(),
            'created_by' => null,
            'metadata' => null,
        ];
    }
}
