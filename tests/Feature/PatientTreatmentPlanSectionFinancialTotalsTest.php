<?php

use App\Livewire\PatientTreatmentPlanSection;
use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders treatment plan totals from final amount when vat is present', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);

    PlanItem::factory()->create([
        'treatment_plan_id' => $plan->id,
        'service_id' => null,
        'quantity' => 2,
        'price' => 100000,
        'discount_percent' => 0,
        'discount_amount' => 10000,
        'vat_amount' => 5000,
        'final_amount' => 195000,
        'status' => PlanItem::STATUS_PENDING,
        'is_completed' => false,
    ]);

    PlanItem::factory()->create([
        'treatment_plan_id' => $plan->id,
        'service_id' => null,
        'quantity' => 1,
        'price' => 100000,
        'discount_percent' => 0,
        'discount_amount' => 0,
        'vat_amount' => 5000,
        'final_amount' => 105000,
        'status' => PlanItem::STATUS_COMPLETED,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'patient_approved' => true,
        'is_completed' => true,
    ]);

    $component = Livewire::actingAs($admin)
        ->test(PatientTreatmentPlanSection::class, [
            'patientId' => $patient->id,
        ]);

    $html = $component->html();

    expect($html)
        ->toMatch('/Tổng chi phí dự kiến:\\s*<strong>300\\.000<\\/strong>/')
        ->toMatch('/Đã hoàn thành:\\s*<strong>105\\.000<\\/strong>/')
        ->toMatch('/Chưa hoàn thành:\\s*<strong>195\\.000<\\/strong>/')
        ->toContain('<th class="is-right">VAT</th>')
        ->toContain('<td class="is-right">5.000</td>')
        ->toContain('<td class="is-right">195.000</td>');
});
