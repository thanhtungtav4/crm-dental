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
            'name' => $this->faker->randomElement(['Trụ implant','Keo gắn răng','Chỉ nha khoa','Gương nha khoa','Bông gòn','Đầu hút nước bọt','Mặt nạ phẫu thuật','Găng tay y tế']),
            'sku' => strtoupper($this->faker->bothify('VT-####')),
            'unit' => $this->faker->randomElement(['cái','hộp','tube','gói']),
            'stock_qty' => $this->faker->numberBetween(10, 200),
            'sale_price' => $this->faker->numberBetween(10_000, 500_000),
            'min_stock' => $this->faker->numberBetween(5, 20),
        ];
    }
}
