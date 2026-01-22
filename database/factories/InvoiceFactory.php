<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $session = TreatmentSession::inRandomOrder()->first() ?? TreatmentSession::factory()->create();
        $plan = TreatmentPlan::find($session->treatment_plan_id) ?? TreatmentPlan::factory()->create();
        $patient = Patient::find($plan->patient_id) ?? Patient::factory()->create();
    return [
            'treatment_session_id' => $session->id,
            'treatment_plan_id' => $plan->id,
            'patient_id' => $patient->id,
            'invoice_no' => 'INV-' . $this->faker->unique()->numerify('########'),
            'total_amount' => $this->faker->numberBetween(500_000, 10_000_000),
            'status' => $this->faker->randomElement(['draft','issued','partial','paid']),
        ];
    }
}
