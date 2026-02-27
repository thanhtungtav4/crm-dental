<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\TreatmentPlan;

it('exports reconciliation report with branch attribution delta between current and legacy models', function () {
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
        'issued_at' => now(),
    ]);

    $invoice->recordPayment(
        amount: 300_000,
        method: 'cash',
        notes: 'reconciliation test',
        direction: 'receipt',
    );

    $reportPath = storage_path('app/testing/reconciliation/finance-branch-attribution-test.json');
    @unlink($reportPath);

    $this->artisan('finance:reconcile-branch-attribution', [
        '--from' => now()->toDateString(),
        '--to' => now()->toDateString(),
        '--branch_id' => $billingBranch->id,
        '--export' => $reportPath,
    ])
        ->expectsOutputToContain('TOTAL_CURRENT_RECEIPTS')
        ->expectsOutputToContain('TOTAL_LEGACY_RECEIPTS')
        ->expectsOutputToContain('MISMATCH_COUNTS')
        ->assertSuccessful();

    expect(file_exists($reportPath))->toBeTrue();

    $report = json_decode((string) file_get_contents($reportPath), true, flags: JSON_THROW_ON_ERROR);
    $summary = (array) data_get($report, 'summary', []);

    expect((float) data_get($summary, 'invoice_current_amount'))->toEqualWithDelta(1_200_000.0, 0.01)
        ->and((float) data_get($summary, 'invoice_legacy_amount'))->toEqualWithDelta(0.0, 0.01)
        ->and((float) data_get($summary, 'receipt_current_amount'))->toEqualWithDelta(300_000.0, 0.01)
        ->and((float) data_get($summary, 'receipt_legacy_amount'))->toEqualWithDelta(0.0, 0.01)
        ->and((int) data_get($summary, 'invoice_mismatch_count'))->toBeGreaterThanOrEqual(1)
        ->and((int) data_get($summary, 'receipt_mismatch_count'))->toBeGreaterThanOrEqual(1);
});
