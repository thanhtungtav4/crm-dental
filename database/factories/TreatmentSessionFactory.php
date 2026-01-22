<?php

namespace Database\Factories;

use App\Models\PlanItem;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TreatmentSessionFactory extends Factory
{
    protected $model = TreatmentSession::class;

    public function definition(): array
    {
        $plan = TreatmentPlan::inRandomOrder()->first() ?? TreatmentPlan::factory()->create();
        $planItem = PlanItem::inRandomOrder()->first() ?? PlanItem::factory()->create(['treatment_plan_id' => $plan->id]);
        $doctor = User::role('Doctor')->inRandomOrder()->first() ?? User::factory()->create();
        return [
            'treatment_plan_id' => $plan->id,
            'plan_item_id' => $planItem->id,
            'doctor_id' => $doctor->id,
            'performed_at' => $this->faker->dateTimeBetween('-5 days', 'now'),
            'diagnosis' => $this->faker->randomElement(['Sâu răng mức độ II','Viêm nha chu nhẹ','Lệch khớp cắn nhẹ','Răng khôn mọc lệch']),
            'procedure' => $this->faker->randomElement(['Vệ sinh + cạo vôi','Gây tê và nhổ răng khôn','Lấy dấu niềng','Đặt trụ implant']),
            'images' => [],
            'notes' => $this->faker->randomElement(['Bệnh nhân hợp tác tốt','Cần theo dõi thêm','Dặn dò chăm sóc tại nhà','Kê đơn kháng sinh 3 ngày']),
            'status' => $this->faker->randomElement(['scheduled','done','follow_up']),
        ];
    }
}
