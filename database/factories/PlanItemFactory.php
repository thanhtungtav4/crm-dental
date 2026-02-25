<?php

namespace Database\Factories;

use App\Models\PlanItem;
use App\Models\TreatmentPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanItemFactory extends Factory
{
    protected $model = PlanItem::class;

    public function definition(): array
    {
        $plan = TreatmentPlan::inRandomOrder()->first() ?? TreatmentPlan::factory()->create();
        return [
            'treatment_plan_id' => $plan->id,
            'name' => $this->faker->randomElement(['Trám răng','Nhổ răng khôn','Niềng răng','Cạo vôi răng','Cấy implant']),
            'quantity' => $this->faker->numberBetween(1, 4),
            'price' => $this->faker->numberBetween(200_000, 15_000_000),
            'approval_status' => PlanItem::APPROVAL_PROPOSED,
            'notes' => $this->faker->randomElement(['Ưu tiên bên trái','Hẹn tái khám sau 1 tuần','Gây tê Lidocain','Vệ sinh trước thủ thuật']),
        ];
    }
}
