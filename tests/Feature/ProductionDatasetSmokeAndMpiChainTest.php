<?php

/**
 * RRB-016 — Production-like dataset smoke tests for financial reports
 *            and MPI merge reparenting chain.
 *
 * Covers:
 *  - invoiceBalanceSummary() aggregates match raw Invoice counts across 2 branches
 *  - Cross-branch isolation: branch A totals not polluted by branch B data
 *  - null branchIds (global) sums both branches
 *  - empty branchIds [] returns zero
 *  - MPI merge reparents TreatmentPlan and Invoice from merged → canonical patient
 *  - After merge: merged patient becomes inactive, canonical retains all records
 *  - MPI rollback: restores TreatmentPlan and Invoice back to original merged patient
 */

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MasterPatientDuplicate;
use App\Models\MasterPatientMerge;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\FinancialReportReadModelService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Seed N invoices on a given branch/patient with known subtotals.
 *
 * @return array{total_amount: float, paid_amount: float}
 */
function seedInvoices(Patient $patient, Branch $branch, int $count, float $subtotal, float $paidAmount): array
{
    $totalAmount = 0.0;
    $totalPaid = 0.0;

    for ($i = 0; $i < $count; $i++) {
        Invoice::query()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'subtotal' => $subtotal,
            'paid_amount' => $paidAmount,
            'status' => 'partial',
            'issued_at' => now(),
        ]);

        $totalAmount += $subtotal;
        $totalPaid += $paidAmount;
    }

    return ['total_amount' => $totalAmount, 'paid_amount' => $totalPaid];
}

/**
 * Build minimum patient fixture attached to a branch.
 */
function makeProductionDatasetPatientForBranch(Branch $branch): Patient
{
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);

    return Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'status' => 'active',
    ]);
}

// ---------------------------------------------------------------------------
// Financial report — cross-branch aggregation smoke tests
// ---------------------------------------------------------------------------

it('invoiceBalanceSummary sums total_amount and paid_amount for a single branch', function (): void {
    $svc = app(FinancialReportReadModelService::class);

    $branch = Branch::factory()->create();
    $patient = makeProductionDatasetPatientForBranch($branch);

    ['total_amount' => $expectedTotal, 'paid_amount' => $expectedPaid] =
        seedInvoices($patient, $branch, 5, 200_000.0, 100_000.0);

    $summary = $svc->invoiceBalanceSummary([$branch->id], null, null);

    expect($summary['total_amount'])->toEqual($expectedTotal)
        ->and($summary['paid_amount'])->toEqual($expectedPaid)
        ->and($summary['balance'])->toEqual($expectedTotal - $expectedPaid);
});

it('invoiceBalanceSummary across two branches sums both when branchIds is null', function (): void {
    $svc = app(FinancialReportReadModelService::class);

    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $patientA = makeProductionDatasetPatientForBranch($branchA);
    $patientB = makeProductionDatasetPatientForBranch($branchB);

    ['total_amount' => $totalA, 'paid_amount' => $paidA] =
        seedInvoices($patientA, $branchA, 10, 300_000.0, 150_000.0);

    ['total_amount' => $totalB, 'paid_amount' => $paidB] =
        seedInvoices($patientB, $branchB, 8, 500_000.0, 200_000.0);

    $summary = $svc->invoiceBalanceSummary(null, null, null);

    // null branchIds = global (all branches)
    expect($summary['total_amount'])->toBeGreaterThanOrEqual($totalA + $totalB)
        ->and($summary['paid_amount'])->toBeGreaterThanOrEqual($paidA + $paidB);
});

it('invoiceBalanceSummary for branch A does not include branch B invoices', function (): void {
    $svc = app(FinancialReportReadModelService::class);

    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $patientA = makeProductionDatasetPatientForBranch($branchA);
    $patientB = makeProductionDatasetPatientForBranch($branchB);

    ['total_amount' => $totalA] = seedInvoices($patientA, $branchA, 4, 100_000.0, 50_000.0);
    seedInvoices($patientB, $branchB, 6, 999_000.0, 0.0);

    $summaryA = $svc->invoiceBalanceSummary([$branchA->id], null, null);

    expect($summaryA['total_amount'])->toEqual($totalA);
});

it('invoiceBalanceSummary returns zeroes when branchIds is empty array', function (): void {
    $svc = app(FinancialReportReadModelService::class);

    $branch = Branch::factory()->create();
    $patient = makeProductionDatasetPatientForBranch($branch);
    seedInvoices($patient, $branch, 3, 100_000.0, 50_000.0);

    $summary = $svc->invoiceBalanceSummary([], null, null);

    expect($summary['total_amount'])->toEqual(0.0)
        ->and($summary['paid_amount'])->toEqual(0.0)
        ->and($summary['balance'])->toEqual(0.0);
});

it('invoiceBalanceSummary respects date range filter', function (): void {
    $svc = app(FinancialReportReadModelService::class);

    $branch = Branch::factory()->create();
    $patient = makeProductionDatasetPatientForBranch($branch);

    // Invoice last month — should be excluded
    Invoice::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'subtotal' => 500_000.0,
        'paid_amount' => 0.0,
        'status' => 'unpaid',
        'issued_at' => now()->subMonth(),
    ]);

    // Invoice today — should be included
    Invoice::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'subtotal' => 200_000.0,
        'paid_amount' => 200_000.0,
        'status' => 'paid',
        'issued_at' => now(),
    ]);

    $from = now()->startOfDay()->toDateString();
    $until = now()->toDateString();

    $summary = $svc->invoiceBalanceSummary([$branch->id], $from, $until);

    expect($summary['total_amount'])->toEqual(200_000.0)
        ->and($summary['paid_amount'])->toEqual(200_000.0);
});

// ---------------------------------------------------------------------------
// MPI merge reparenting — TreatmentPlan and Invoice reassigned to canonical
// ---------------------------------------------------------------------------

it('mpi merge reparents treatment_plans and invoices to canonical patient', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole('Admin');
    $this->actingAs($manager);

    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $customerA = Customer::factory()->create(['branch_id' => $branchA->id, 'phone' => '0911000021']);
    $customerB = Customer::factory()->create(['branch_id' => $branchB->id, 'phone' => '0911000022']);

    $canonical = Patient::factory()->create([
        'customer_id' => $customerA->id,
        'first_branch_id' => $branchA->id,
        'phone' => '0911000021',
        'status' => 'active',
    ]);

    $merged = Patient::factory()->create([
        'customer_id' => $customerB->id,
        'first_branch_id' => $branchB->id,
        'phone' => '0911000021',
        'status' => 'active',
    ]);

    // TreatmentPlan and Invoice belonging to the merged patient
    $plan = TreatmentPlan::factory()->create(['patient_id' => $merged->id, 'branch_id' => $branchB->id]);
    $invoice = Invoice::query()->create([
        'patient_id' => $merged->id,
        'branch_id' => $branchB->id,
        'subtotal' => 300_000.0,
        'paid_amount' => 0.0,
        'status' => 'unpaid',
        'issued_at' => now(),
    ]);

    $duplicateCase = MasterPatientDuplicate::query()->updateOrCreate(
        [
            'identity_type' => 'phone',
            'identity_hash' => hash('sha256', 'phone|0911000021'),
            'status' => MasterPatientDuplicate::STATUS_OPEN,
        ],
        [
            'patient_id' => $merged->id,
            'branch_id' => $branchB->id,
            'identity_value' => '0911000021',
            'matched_patient_ids' => [$canonical->id, $merged->id],
            'matched_branch_ids' => [$branchA->id, $branchB->id],
            'confidence_score' => 95,
        ]
    );

    $this->artisan('mpi:merge', [
        'canonical_patient_id' => $canonical->id,
        'merged_patient_id' => $merged->id,
        '--duplicate_case_id' => $duplicateCase->id,
        '--reason' => 'Cross-branch duplicate RRB-016',
    ])->assertSuccessful();

    // TreatmentPlan and Invoice must point to canonical
    expect($plan->fresh()?->patient_id)->toBe($canonical->id)
        ->and($invoice->fresh()?->patient_id)->toBe($canonical->id);

    // Merged patient must become inactive
    expect($merged->fresh()?->status)->toBe('inactive');

    // Canonical patient retains active status
    expect($canonical->fresh()?->status)->toBe('active');
});

it('mpi rollback restores treatment_plans and invoices to merged patient', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole('Admin');
    $this->actingAs($manager);

    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $customerA = Customer::factory()->create(['branch_id' => $branchA->id, 'phone' => '0911000031']);
    $customerB = Customer::factory()->create(['branch_id' => $branchB->id, 'phone' => '0911000032']);

    $canonical = Patient::factory()->create([
        'customer_id' => $customerA->id,
        'first_branch_id' => $branchA->id,
        'phone' => '0911000031',
        'status' => 'active',
    ]);

    $merged = Patient::factory()->create([
        'customer_id' => $customerB->id,
        'first_branch_id' => $branchB->id,
        'phone' => '0911000031',
        'status' => 'active',
    ]);

    $plan = TreatmentPlan::factory()->create(['patient_id' => $merged->id, 'branch_id' => $branchB->id]);
    $invoice = Invoice::query()->create([
        'patient_id' => $merged->id,
        'branch_id' => $branchB->id,
        'subtotal' => 400_000.0,
        'paid_amount' => 0.0,
        'status' => 'unpaid',
        'issued_at' => now(),
    ]);

    $duplicateCase = MasterPatientDuplicate::query()->updateOrCreate(
        [
            'identity_type' => 'phone',
            'identity_hash' => hash('sha256', 'phone|0911000031'),
            'status' => MasterPatientDuplicate::STATUS_OPEN,
        ],
        [
            'patient_id' => $merged->id,
            'branch_id' => $branchB->id,
            'identity_value' => '0911000031',
            'matched_patient_ids' => [$canonical->id, $merged->id],
            'matched_branch_ids' => [$branchA->id, $branchB->id],
            'confidence_score' => 95,
        ]
    );

    $this->artisan('mpi:merge', [
        'canonical_patient_id' => $canonical->id,
        'merged_patient_id' => $merged->id,
        '--duplicate_case_id' => $duplicateCase->id,
        '--reason' => 'Rollback candidate RRB-016',
    ])->assertSuccessful();

    $merge = MasterPatientMerge::query()->latest('id')->first();

    $this->artisan('mpi:merge-rollback', [
        'merge_id' => $merge->id,
    ])->assertSuccessful();

    // After rollback: TreatmentPlan and Invoice restored to merged patient
    expect($plan->fresh()?->patient_id)->toBe($merged->id)
        ->and($invoice->fresh()?->patient_id)->toBe($merged->id);

    // Merged patient should be reactivated
    expect($merged->fresh()?->status)->toBe('active');
});
