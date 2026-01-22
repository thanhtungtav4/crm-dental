<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BranchLog;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BranchLogFactory extends Factory
{
    protected $model = BranchLog::class;

    public function definition(): array
    {
        $patient = Patient::inRandomOrder()->first() ?? Patient::factory()->create();
        $from = Branch::inRandomOrder()->first() ?? Branch::factory()->create();
        $to = Branch::inRandomOrder()->first() ?? Branch::factory()->create();
        $user = User::inRandomOrder()->first() ?? User::factory()->create();
        return [
            'patient_id' => $patient->id,
            'from_branch_id' => $from->id,
            'to_branch_id' => $to->id,
            'moved_by' => $user->id,
            'note' => $this->faker->sentence(),
        ];
    }
}
