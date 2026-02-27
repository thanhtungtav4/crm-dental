<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Services\OperationalKpiService;

it('attributes payment and KPI revenue to transactional branch instead of patient first branch', function () {
    $billingBranch = Branch::factory()->create();
    $originBranch = Branch::factory()->create();

    $customer = Customer::factory()->create([
        'branch_id' => $originBranch->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $originBranch->id,
    ]);

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $billingBranch->id,
    ]);

    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'treatment_plan_id' => $plan->id,
        'branch_id' => $billingBranch->id,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 1_200_000,
        'paid_amount' => 0,
    ]);

    $invoice->recordPayment(
        amount: 300_000,
        method: 'cash',
        notes: 'Thu theo chi nhánh điều trị',
        direction: 'receipt',
    );

    $payment = $invoice->payments()->latest('id')->first();

    expect($payment)->not->toBeNull()
        ->and((int) $payment?->branch_id)->toBe($billingBranch->id);

    /** @var OperationalKpiService $service */
    $service = app(OperationalKpiService::class);

    $from = now()->startOfDay();
    $to = now()->endOfDay();

    $billingMetrics = $service->buildSnapshot($from, $to, $billingBranch->id)['metrics'];
    $originMetrics = $service->buildSnapshot($from, $to, $originBranch->id)['metrics'];

    expect((float) $billingMetrics['revenue_per_patient'])->toEqualWithDelta(300_000.0, 0.01)
        ->and((float) $billingMetrics['ltv_patient'])->toEqualWithDelta(300_000.0, 0.01)
        ->and((float) $originMetrics['revenue_per_patient'])->toEqualWithDelta(0.0, 0.01)
        ->and((float) $originMetrics['ltv_patient'])->toEqualWithDelta(0.0, 0.01);
});
