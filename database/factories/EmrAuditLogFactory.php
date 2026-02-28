<?php

namespace Database\Factories;

use App\Models\EmrAuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmrAuditLog>
 */
class EmrAuditLogFactory extends Factory
{
    protected $model = EmrAuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_type' => EmrAuditLog::ENTITY_SYNC_EVENT,
            'entity_id' => fake()->numberBetween(1, 100000),
            'action' => fake()->randomElement([
                EmrAuditLog::ACTION_PUBLISH,
                EmrAuditLog::ACTION_SYNC,
                EmrAuditLog::ACTION_FAIL,
            ]),
            'patient_id' => null,
            'visit_episode_id' => null,
            'branch_id' => null,
            'actor_id' => null,
            'context' => [
                'source' => 'factory',
            ],
            'occurred_at' => now(),
        ];
    }
}
