<?php

namespace Database\Factories;

use App\Models\ToothCondition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ToothCondition>
 */
class ToothConditionFactory extends Factory
{
    protected $model = ToothCondition::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $conditions = [
            ['code' => 'K02', 'name' => '(K02) Sâu răng', 'category' => 'diagnosis', 'color' => '#ef4444'],
            ['code' => 'MR', 'name' => '(MR) Mòn cổ răng', 'category' => 'diagnosis', 'color' => '#84cc16'],
            ['code' => 'IMP', 'name' => '(Imp) Implant', 'category' => 'treatment', 'color' => '#22c55e'],
            ['code' => 'VN', 'name' => '(VN) Viêm nướu', 'category' => 'diagnosis', 'color' => '#fca5a5'],
            ['code' => 'MAT', 'name' => 'Mất răng', 'category' => 'status', 'color' => '#0f172a'],
        ];

        $condition = fake()->randomElement($conditions);

        return [
            'code' => $condition['code'] . '_' . fake()->unique()->numberBetween(1, 9999),
            'name' => $condition['name'],
            'category' => $condition['category'],
            'color' => $condition['color'],
            'description' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate a diagnosis condition.
     */
    public function diagnosis(): static
    {
        return $this->state(fn(array $attributes) => [
            'category' => ToothCondition::CATEGORY_DIAGNOSIS,
        ]);
    }

    /**
     * Indicate a treatment condition.
     */
    public function treatment(): static
    {
        return $this->state(fn(array $attributes) => [
            'category' => ToothCondition::CATEGORY_TREATMENT,
        ]);
    }

    /**
     * Indicate a status condition.
     */
    public function status(): static
    {
        return $this->state(fn(array $attributes) => [
            'category' => ToothCondition::CATEGORY_STATUS,
        ]);
    }
}
