<?php

namespace Database\Factories;

use App\Models\ClinicalNote;
use App\Models\EmrApiMutation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmrApiMutation>
 */
class EmrApiMutationFactory extends Factory
{
    protected $model = EmrApiMutation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_id' => 'emr-'.strtolower(fake()->bothify('????????????????')),
            'endpoint' => '/api/v1/emr/internal/clinical-notes/1/amend',
            'mutation_type' => EmrApiMutation::TYPE_CLINICAL_NOTE_AMEND,
            'payload_checksum' => hash('sha256', fake()->sentence()),
            'patient_id' => null,
            'clinical_note_id' => ClinicalNote::factory(),
            'actor_id' => null,
            'status_code' => 200,
            'response_payload' => ['ok' => true],
            'processed_at' => now(),
        ];
    }
}
