<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PatientToothCondition;
use App\Models\ToothCondition;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PatientToothCondition>
 */
class PatientToothConditionFactory extends Factory
{
    protected $model = PatientToothCondition::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $allTeeth = PatientToothCondition::getAllTeethNumbers();

        return [
            'patient_id' => Patient::factory(),
            'tooth_number' => fake()->randomElement($allTeeth),
            'tooth_condition_id' => ToothCondition::factory(),
            'treatment_status' => fake()->randomElement([
                PatientToothCondition::STATUS_CURRENT,
                PatientToothCondition::STATUS_IN_TREATMENT,
                PatientToothCondition::STATUS_COMPLETED,
            ]),
            'treatment_plan_id' => null,
            'notes' => fake()->optional(0.3)->sentence(),
            'diagnosed_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'completed_at' => null,
            'diagnosed_by' => null,
        ];
    }

    /**
     * Indicate that the condition is for a specific patient.
     */
    public function forPatient(Patient $patient): static
    {
        return $this->state(fn(array $attributes) => [
            'patient_id' => $patient->id,
        ]);
    }

    /**
     * Indicate that the condition is for a specific tooth.
     */
    public function forTooth(string $toothNumber): static
    {
        return $this->state(fn(array $attributes) => [
            'tooth_number' => $toothNumber,
        ]);
    }

    /**
     * Indicate that the condition has current status.
     */
    public function current(): static
    {
        return $this->state(fn(array $attributes) => [
            'treatment_status' => PatientToothCondition::STATUS_CURRENT,
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the condition is in treatment.
     */
    public function inTreatment(): static
    {
        return $this->state(fn(array $attributes) => [
            'treatment_status' => PatientToothCondition::STATUS_IN_TREATMENT,
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the condition is completed.
     */
    public function completed(): static
    {
        return $this->state(fn(array $attributes) => [
            'treatment_status' => PatientToothCondition::STATUS_COMPLETED,
            'completed_at' => fake()->dateTimeBetween($attributes['diagnosed_at'] ?? '-1 month', 'now'),
        ]);
    }

    /**
     * Indicate that the condition has a treatment plan.
     */
    public function withTreatmentPlan(TreatmentPlan $plan): static
    {
        return $this->state(fn(array $attributes) => [
            'treatment_plan_id' => $plan->id,
            'treatment_status' => PatientToothCondition::STATUS_IN_TREATMENT,
        ]);
    }
}
