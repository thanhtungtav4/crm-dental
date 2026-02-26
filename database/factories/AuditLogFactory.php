<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $actor = User::factory()->create();

        return [
            'entity_type' => $this->faker->randomElement(['payment', 'invoice', 'appointment', 'care_ticket']),
            'entity_id' => $this->faker->numberBetween(1, 5000),
            'action' => $this->faker->randomElement(['create', 'update', 'refund', 'reverse', 'cancel']),
            'actor_id' => $actor->id,
            'metadata' => [
                'note' => $this->faker->sentence(),
            ],
        ];
    }
}
