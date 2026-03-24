<?php

use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\TreatmentPlan;
use App\Models\User;
use Livewire\Livewire;

it('prefills and persists patient id when creating invoice from patient profile context', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $patient = Patient::factory()->create();

    $component = Livewire::actingAs($admin)
        ->withQueryParams([
            'patient_id' => $patient->id,
        ])
        ->test(CreateInvoice::class);

    expect((int) $component->get('data.patient_id'))->toBe($patient->id);

    $component
        ->set('data.subtotal', 1200000)
        ->set('data.discount_amount', 200000)
        ->set('data.tax_amount', 10000)
        ->set('data.status', Invoice::STATUS_ISSUED)
        ->set('data.due_date', now()->addDays(10)->toDateString())
        ->call('create')
        ->assertHasNoErrors();

    $invoice = Invoice::query()->latest('id')->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->patient_id)->toBe($patient->id)
        ->and((float) $invoice->subtotal)->toEqualWithDelta(1200000.0, 0.01)
        ->and((float) $invoice->discount_amount)->toEqualWithDelta(200000.0, 0.01)
        ->and((float) $invoice->tax_amount)->toEqualWithDelta(10000.0, 0.01)
        ->and((float) $invoice->total_amount)->toEqualWithDelta(1010000.0, 0.01)
        ->and($invoice->status)->toBe(Invoice::STATUS_ISSUED)
        ->and($invoice->invoice_no)->toStartWith('INV-');
});

it('hydrates patient and subtotal from selected treatment plan when creating invoice', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'status' => TreatmentPlan::STATUS_APPROVED,
        'total_estimated_cost' => 0,
        'total_cost' => 0,
    ]);

    PlanItem::factory()->create([
        'treatment_plan_id' => $plan->id,
        'quantity' => 1,
        'price' => 900000,
        'final_amount' => 800000,
    ]);

    PlanItem::factory()->create([
        'treatment_plan_id' => $plan->id,
        'quantity' => 1,
        'price' => 400000,
        'final_amount' => 200000,
    ]);

    $component = Livewire::actingAs($admin)
        ->test(CreateInvoice::class);

    $component->set('data.treatment_plan_id', $plan->id);

    expect((int) $component->get('data.patient_id'))->toBe($patient->id)
        ->and((float) $component->get('data.subtotal'))->toEqualWithDelta(1000000.0, 0.01)
        ->and((float) $component->get('data.total_amount'))->toEqualWithDelta(1000000.0, 0.01);

    $component
        ->set('data.status', Invoice::STATUS_ISSUED)
        ->set('data.due_date', now()->addDays(7)->toDateString())
        ->call('create')
        ->assertHasNoErrors();

    $invoice = Invoice::query()->latest('id')->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->patient_id)->toBe($patient->id)
        ->and($invoice->treatment_plan_id)->toBe($plan->id)
        ->and((float) $invoice->subtotal)->toEqualWithDelta(1000000.0, 0.01)
        ->and((float) $invoice->total_amount)->toEqualWithDelta(1000000.0, 0.01);
});

it('ignores hidden patient and treatment plan query params outside the accessible branch scope', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $hiddenPatient = Patient::factory()->create([
        'first_branch_id' => $branchB->id,
    ]);

    $hiddenPlan = TreatmentPlan::factory()->create([
        'patient_id' => $hiddenPatient->id,
        'branch_id' => $branchB->id,
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);

    $component = Livewire::actingAs($manager)
        ->withQueryParams([
            'patient_id' => $hiddenPatient->id,
            'treatment_plan_id' => $hiddenPlan->id,
        ])
        ->test(CreateInvoice::class);

    expect($component->get('data.patient_id'))->toBeNull()
        ->and($component->get('data.treatment_plan_id'))->toBeNull()
        ->and((float) $component->get('data.subtotal'))->toEqualWithDelta(0.0, 0.01)
        ->and((float) $component->get('data.total_amount'))->toEqualWithDelta(0.0, 0.01);
});
