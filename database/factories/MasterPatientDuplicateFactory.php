<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\MasterPatientDuplicate;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MasterPatientDuplicate>
 */
class MasterPatientDuplicateFactory extends Factory
{
    protected $model = MasterPatientDuplicate::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'branch_id' => Branch::factory(),
            'identity_type' => 'phone',
            'identity_hash' => hash('sha256', 'phone|'.$this->faker->numerify('09########')),
            'identity_value' => $this->faker->numerify('09########'),
            'matched_patient_ids' => [],
            'matched_branch_ids' => [],
            'confidence_score' => 95.0,
            'status' => MasterPatientDuplicate::STATUS_OPEN,
            'review_note' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'metadata' => [
                'patient_count' => 0,
                'branch_count' => 0,
            ],
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => [
            'status' => MasterPatientDuplicate::STATUS_RESOLVED,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }
}
