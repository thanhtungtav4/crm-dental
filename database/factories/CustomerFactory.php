<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        $branch = Branch::inRandomOrder()->first() ?? Branch::factory()->create();

        return [
            'branch_id' => $branch->id,
            'full_name' => fake()->randomElement(['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Huỳnh', 'Phan', 'Vũ', 'Võ', 'Đặng']).' '.fake()->firstName(),
            'phone' => fake()->unique()->numerify('09########'),
            'email' => fake()->unique()->safeEmail(),
            'source' => fake()->randomElement(['walkin', 'facebook', 'zalo', 'referral', 'other']),
            'status' => 'lead',
            'notes' => fake()->randomElement([
                'Khách quan tâm trồng răng implant',
                'Tư vấn chỉnh nha - niềng răng',
                'Đặt lịch cạo vôi răng',
                'Tư vấn tẩy trắng răng',
            ]),
        ];
    }
}
