<?php

namespace Database\Factories;

use App\Models\ClinicalNote;
use App\Models\ClinicalNoteRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClinicalNoteRevision>
 */
class ClinicalNoteRevisionFactory extends Factory
{
    protected $model = ClinicalNoteRevision::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clinical_note_id' => ClinicalNote::factory(),
            'patient_id' => fn (array $attributes): ?int => ClinicalNote::query()
                ->whereKey((int) $attributes['clinical_note_id'])
                ->value('patient_id'),
            'visit_episode_id' => fn (array $attributes): ?int => ClinicalNote::query()
                ->whereKey((int) $attributes['clinical_note_id'])
                ->value('visit_episode_id'),
            'branch_id' => fn (array $attributes): ?int => ClinicalNote::query()
                ->whereKey((int) $attributes['clinical_note_id'])
                ->value('branch_id'),
            'version' => 1,
            'operation' => ClinicalNoteRevision::OPERATION_CREATE,
            'changed_by' => null,
            'previous_payload' => null,
            'current_payload' => [
                'general_exam_notes' => fake()->sentence(),
            ],
            'changed_fields' => ['general_exam_notes'],
            'reason' => null,
            'created_at' => now(),
        ];
    }
}
