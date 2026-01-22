<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Doctor;
use Illuminate\Database\Eloquent\Factories\Factory;

class DoctorFactory extends Factory
{
    protected $model = Doctor::class;

    public function definition(): array
    {
        $branch = Branch::inRandomOrder()->first() ?? Branch::factory()->create();
        return [
            'branch_id' => $branch->id,
            'name' => $this->faker->name(),
            'specialization' => $this->faker->randomElement(['Nha chu','Chỉnh nha','Trồng răng','Nội nha','Phục hình']),
            'phone' => $this->faker->numerify('09########'),
        ];
    }
}
