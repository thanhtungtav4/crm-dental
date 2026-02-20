<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Prescription;
use App\Models\TreatmentSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prescription>
 */
class PrescriptionFactory extends Factory
{
    protected $model = Prescription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'treatment_session_id' => null,
            'prescription_code' => Prescription::generatePrescriptionCode(),
            'prescription_name' => fake()->optional()->sentence(3),
            'doctor_id' => User::factory(),
            'treatment_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'notes' => fake()->optional()->paragraph(),
            'created_by' => null,
        ];
    }

    /**
     * Indicate that the prescription is for a specific patient.
     */
    public function forPatient(Patient $patient): static
    {
        return $this->state(fn(array $attributes) => [
            'patient_id' => $patient->id,
        ]);
    }

    /**
     * Indicate that the prescription is by a specific doctor.
     */
    public function byDoctor(User $doctor): static
    {
        return $this->state(fn(array $attributes) => [
            'doctor_id' => $doctor->id,
        ]);
    }

    /**
     * Indicate that the prescription has a treatment session.
     */
    public function withTreatmentSession(TreatmentSession $session): static
    {
        return $this->state(fn(array $attributes) => [
            'treatment_session_id' => $session->id,
        ]);
    }

    /**
     * Indicate that the prescription is for today.
     */
    public function today(): static
    {
        return $this->state(fn(array $attributes) => [
            'treatment_date' => now(),
        ]);
    }
}
