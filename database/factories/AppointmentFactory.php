<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\User;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $patient = Patient::inRandomOrder()->first() ?? Patient::factory()->create();
        $branch = Branch::find($patient->first_branch_id) ?? Branch::factory()->create();
        $doctor = User::role('Doctor')->inRandomOrder()->first();
        if (!$doctor) {
            $doctor = User::factory()->create(['branch_id' => $branch->id]);
            $doctor->assignRole('Doctor');
        }
        return [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'date' => $this->faker->dateTimeBetween('-10 days', '+10 days'),
            'status' => $this->faker->randomElement(Appointment::statusValues()),
            'note' => $this->faker->sentence(),
        ];
    }
}
