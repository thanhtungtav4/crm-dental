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
            'name' => 'Chi nhánh '.fake()->randomElement(['Hà Nội', 'TP. HCM', 'Đà Nẵng', 'Cần Thơ', 'Hải Phòng']),
            'address' => fake()->streetAddress().', '.fake()->randomElement(['Quận 1', 'Quận 3', 'Quận 7', 'Cầu Giấy', 'Thanh Xuân', 'Ngũ Hành Sơn']),
            'phone' => fake()->numerify('09########'),
            'active' => true,
        ];
    }
}
