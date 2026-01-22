<?php

namespace Database\Factories;

use App\Models\Billing;
use App\Models\TreatmentPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillingFactory extends Factory
{
    protected $model = Billing::class;

    public function definition(): array
    {
        $plan = TreatmentPlan::inRandomOrder()->first() ?? TreatmentPlan::factory()->create();
        return [
            'treatment_plan_id' => $plan->id,
            'amount' => $this->faker->numberBetween(500_000, 10_000_000),
            'status' => $this->faker->randomElement(['unpaid','paid','partial']),
        ];
    }
}
