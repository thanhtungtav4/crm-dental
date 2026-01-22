<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\TreatmentMaterial;
use App\Models\TreatmentSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TreatmentMaterialFactory extends Factory
{
    protected $model = TreatmentMaterial::class;

    public function definition(): array
    {
        $session = TreatmentSession::inRandomOrder()->first() ?? TreatmentSession::factory()->create();
        $material = Material::inRandomOrder()->first() ?? Material::factory()->create();
        $user = User::inRandomOrder()->first() ?? User::factory()->create();
        return [
            'treatment_session_id' => $session->id,
            'material_id' => $material->id,
            'quantity' => $this->faker->numberBetween(1, 5),
            'cost' => $this->faker->numberBetween(10_000, 200_000),
            'used_by' => $user->id,
        ];
    }
}
