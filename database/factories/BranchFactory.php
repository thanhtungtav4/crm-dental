<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'name' => 'Chi nhánh ' . $this->faker->randomElement(['Hà Nội','TP. HCM','Đà Nẵng','Cần Thơ','Hải Phòng']),
            'address' => $this->faker->streetAddress() . ', ' . $this->faker->randomElement(['Quận 1','Quận 3','Quận 7','Cầu Giấy','Thanh Xuân','Ngũ Hành Sơn']),
            'phone' => $this->faker->numerify('09########'),
            'active' => true,
        ];
    }
}
