<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        $branch = Branch::inRandomOrder()->first() ?? Branch::factory()->create();
        $customer = Customer::inRandomOrder()->first() ?? Customer::factory()->create(['branch_id' => $branch->id]);
        return [
            'patient_code' => 'PAT-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
            'customer_id' => $customer->id,
            'first_branch_id' => $branch->id,
            'full_name' => $customer->full_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'gender' => $this->faker->randomElement(['male','female','other']),
            'address' => $this->faker->streetAddress() . ', ' . $this->faker->randomElement(['Quận 1','Quận 3','Quận 7','Cầu Giấy','Thanh Xuân','Ngũ Hành Sơn']),
        ];
    }
}
