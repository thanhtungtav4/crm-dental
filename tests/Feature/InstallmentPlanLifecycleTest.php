<?php

use App\Models\InstallmentPlan;
use App\Models\Invoice;
use App\Models\Note;
use App\Models\Patient;

it('marks installment plan as defaulted when overdue and unpaid', function () {
    $patient = Patient::factory()->create();

    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 1200000,
        'paid_amount' => 0,
    ]);

    $plan = InstallmentPlan::create([
        'invoice_id' => $invoice->id,
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'financed_amount' => 1000000,
        'down_payment_amount' => 200000,
        'remaining_amount' => 1000000,
        'number_of_installments' => 2,
        'installment_amount' => 500000,
        'start_date' => now()->subMonths(2)->toDateString(),
        'status' => InstallmentPlan::STATUS_ACTIVE,
    ]);

    $plan->syncFinancialState(now());

    expect($plan->fresh()->status)->toBe(InstallmentPlan::STATUS_DEFAULTED)
        ->and((float) $plan->fresh()->remaining_amount)->toEqualWithDelta(1000000.00, 0.01);
});

it('marks installment plan as completed when fully paid', function () {
    $patient = Patient::factory()->create();

    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 1200000,
        'paid_amount' => 0,
    ]);

    $plan = InstallmentPlan::create([
        'invoice_id' => $invoice->id,
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'financed_amount' => 1000000,
        'down_payment_amount' => 200000,
        'remaining_amount' => 1000000,
        'number_of_installments' => 2,
        'installment_amount' => 500000,
        'start_date' => now()->subMonths(1)->toDateString(),
        'status' => InstallmentPlan::STATUS_ACTIVE,
    ]);

    $invoice->recordPayment(1200000, 'cash');
    $plan->syncFinancialState(now());

    expect($plan->fresh()->status)->toBe(InstallmentPlan::STATUS_COMPLETED)
        ->and((float) $plan->fresh()->remaining_amount)->toEqualWithDelta(0.00, 0.01);
});

it('creates dunning care ticket and updates dunning level', function () {
    $patient = Patient::factory()->create();

    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 900000,
        'paid_amount' => 0,
    ]);

    $plan = InstallmentPlan::create([
        'invoice_id' => $invoice->id,
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'financed_amount' => 900000,
        'down_payment_amount' => 0,
        'remaining_amount' => 900000,
        'number_of_installments' => 3,
        'installment_amount' => 300000,
        'start_date' => now()->subMonths(2)->toDateString(),
        'status' => InstallmentPlan::STATUS_ACTIVE,
    ]);

    $this->artisan('installments:run-dunning', [
        '--date' => now()->toDateString(),
    ])->assertSuccessful();

    $ticket = Note::query()
        ->where('source_type', InstallmentPlan::class)
        ->where('source_id', $plan->id)
        ->where('care_type', 'payment_reminder')
        ->first();

    expect($ticket)->not->toBeNull()
        ->and($plan->fresh()->dunning_level)->toBeGreaterThan(0);
});
