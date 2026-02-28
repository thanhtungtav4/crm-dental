<?php

namespace Database\Factories;

use App\Models\ClinicalOrder;
use App\Models\ClinicalResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClinicalResult>
 */
class ClinicalResultFactory extends Factory
{
    protected $model = ClinicalResult::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clinical_order_id' => ClinicalOrder::factory(),
            'patient_id' => null,
            'visit_episode_id' => null,
            'branch_id' => null,
            'verified_by' => null,
            'result_code' => null,
            'status' => ClinicalResult::STATUS_DRAFT,
            'resulted_at' => null,
            'verified_at' => null,
            'payload' => [
                'finding' => fake()->sentence(3),
            ],
            'interpretation' => fake()->optional()->sentence(),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function forOrder(ClinicalOrder $order): static
    {
        return $this->state(fn (array $attributes): array => [
            'clinical_order_id' => $order->id,
            'patient_id' => $order->patient_id,
            'visit_episode_id' => $order->visit_episode_id,
            'branch_id' => $order->branch_id,
        ]);
    }

    public function preliminary(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ClinicalResult::STATUS_PRELIMINARY,
            'resulted_at' => now(),
        ]);
    }

    public function final(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ClinicalResult::STATUS_FINAL,
            'resulted_at' => now(),
            'verified_at' => now(),
        ]);
    }
}
