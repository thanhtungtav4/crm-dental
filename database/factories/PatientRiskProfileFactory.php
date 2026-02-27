<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PatientRiskProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientRiskProfile>
 */
class PatientRiskProfileFactory extends Factory
{
    protected $model = PatientRiskProfile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'as_of_date' => now()->toDateString(),
            'model_version' => PatientRiskProfile::MODEL_VERSION_BASELINE,
            'no_show_risk_score' => fake()->randomFloat(2, 0, 100),
            'churn_risk_score' => fake()->randomFloat(2, 0, 100),
            'risk_level' => fake()->randomElement([
                PatientRiskProfile::LEVEL_LOW,
                PatientRiskProfile::LEVEL_MEDIUM,
                PatientRiskProfile::LEVEL_HIGH,
            ]),
            'recommended_action' => fake()->sentence(),
            'generated_at' => now(),
            'created_by' => null,
            'feature_payload' => [
                'appointments_90d' => fake()->numberBetween(0, 12),
            ],
        ];
    }
}
