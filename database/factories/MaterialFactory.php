<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaterialFactory extends Factory
{
    protected $model = Material::class;

    public function definition(): array
    {
        $branch = Branch::inRandomOrder()->first() ?? Branch::factory()->create();

        return [
            'branch_id' => $branch->id,
            'name' => fake()->randomElement(['Trụ implant', 'Keo gắn răng', 'Chỉ nha khoa', 'Gương nha khoa', 'Bông gòn', 'Đầu hút nước bọt', 'Mặt nạ phẫu thuật', 'Găng tay y tế']),
            'sku' => strtoupper(fake()->bothify('VT-####')),
            'unit' => fake()->randomElement(['cái', 'hộp', 'tube', 'gói']),
            'stock_qty' => fake()->numberBetween(10, 200),
            'sale_price' => fake()->numberBetween(10_000, 500_000),
            'min_stock' => fake()->numberBetween(5, 20),
        ];
    }
}
