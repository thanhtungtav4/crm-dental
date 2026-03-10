<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TreatmentPlanFactory extends Factory
{
    protected $model = TreatmentPlan::class;

    public function definition(): array
    {
        $patient = Patient::inRandomOrder()->first() ?? Patient::factory()->create();
        $branch = Branch::find($patient->first_branch_id) ?? Branch::factory()->create();
        $doctor = User::role('Doctor')
            ->where('branch_id', $branch->id)
            ->inRandomOrder()
            ->first();
        if (! $doctor) {
            $doctor = User::factory()->create(['branch_id' => $branch->id]);
            $doctor->assignRole('Doctor');
        }

        return [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'title' => fake()->randomElement(['Kế hoạch trồng răng implant', 'Kế hoạch niềng răng', 'Kế hoạch phục hình thẩm mỹ', 'Kế hoạch điều trị nha chu']),
            'notes' => fake()->randomElement(['Tư vấn lần 1', 'Đã chụp X-quang', 'Chỉ định cạo vôi trước', 'Cần xét nghiệm máu']),
            'total_cost' => fake()->numberBetween(2_000_000, 50_000_000),
            'status' => TreatmentPlan::STATUS_APPROVED,
        ];
    }
}
