<?php

namespace Database\Factories;

use App\Models\ClinicalNote;
use App\Models\ClinicalOrder;
use App\Models\Patient;
use App\Models\User;
use App\Models\VisitEpisode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClinicalOrder>
 */
class ClinicalOrderFactory extends Factory
{
    protected $model = ClinicalOrder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'exam_session_id' => null,
            'visit_episode_id' => null,
            'clinical_note_id' => null,
            'branch_id' => null,
            'ordered_by' => User::factory(),
            'order_code' => null,
            'order_type' => fake()->randomElement(['xray', 'lab', 'ct_scan']),
            'status' => ClinicalOrder::STATUS_PENDING,
            'requested_at' => now(),
            'completed_at' => null,
            'payload' => [
                'priority' => fake()->randomElement(['normal', 'urgent']),
            ],
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function forPatient(Patient $patient): static
    {
        return $this->state(fn (array $attributes): array => [
            'patient_id' => $patient->id,
        ]);
    }

    public function forEncounter(VisitEpisode $encounter): static
    {
        return $this->state(fn (array $attributes): array => [
            'patient_id' => $encounter->patient_id,
            'visit_episode_id' => $encounter->id,
            'branch_id' => $encounter->branch_id,
        ]);
    }

    public function forClinicalNote(ClinicalNote $note): static
    {
        return $this->state(fn (array $attributes): array => [
            'patient_id' => $note->patient_id,
            'exam_session_id' => $note->exam_session_id,
            'visit_episode_id' => $note->visit_episode_id,
            'clinical_note_id' => $note->id,
            'branch_id' => $note->branch_id,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ClinicalOrder::STATUS_IN_PROGRESS,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ClinicalOrder::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }
}
