<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Material;
use App\Models\Patient;
use App\Models\ReceiptExpense;
use App\Services\AppointmentReportReadModelService;
use App\Services\FinancialReportReadModelService;
use App\Services\InventorySupplyReportReadModelService;
use App\Services\PatientInsightReportReadModelService;

// ---------------------------------------------------------------------------
// Helper: seed cashflow receipts for a branch and date
// ---------------------------------------------------------------------------
function seedReceiptExpense(Branch $branch, string $voucherDate, float $amount, string $type = 'receipt'): void
{
    ReceiptExpense::query()->create([
        'clinic_id' => $branch->id,
        'voucher_type' => $type,
        'amount' => $amount,
        'voucher_date' => $voucherDate,
        'status' => 'posted',
        'note' => "Seed {$type} {$voucherDate}",
    ]);
}

// ---------------------------------------------------------------------------
// FinancialReportReadModelService — date-range boundary
// ---------------------------------------------------------------------------

it('cashflowSummary excludes receipts outside the selected date range', function (): void {
    $branch = Branch::factory()->create();

    $yesterday = now()->subDay()->toDateString();
    $today = now()->toDateString();
    $lastWeek = now()->subDays(7)->toDateString();

    // Inside range
    seedReceiptExpense($branch, $today, 200_000);
    seedReceiptExpense($branch, $today, 300_000);

    // Outside range — older records
    seedReceiptExpense($branch, $yesterday, 999_000);
    seedReceiptExpense($branch, $lastWeek, 500_000);

    $svc = app(FinancialReportReadModelService::class);
    $summary = $svc->cashflowSummary([$branch->id], $today, $today);

    expect($summary['total_receipt'])->toBe(500_000.0);
});

it('cashflowSummary includes receipts spanning a multi-day range', function (): void {
    $branch = Branch::factory()->create();

    $dayA = now()->subDays(2)->toDateString();
    $dayB = now()->subDay()->toDateString();
    $dayC = now()->toDateString();
    $outsideOld = now()->subDays(10)->toDateString();

    seedReceiptExpense($branch, $dayA, 100_000);
    seedReceiptExpense($branch, $dayB, 200_000);
    seedReceiptExpense($branch, $dayC, 300_000);
    seedReceiptExpense($branch, $outsideOld, 999_000); // excluded

    $svc = app(FinancialReportReadModelService::class);
    $summary = $svc->cashflowSummary([$branch->id], $dayA, $dayC);

    expect($summary['total_receipt'])->toBe(600_000.0);
});

it('cashflowSummary returns zero totals for empty branch filter', function (): void {
    $branch = Branch::factory()->create();
    $today = now()->toDateString();

    seedReceiptExpense($branch, $today, 500_000);

    $svc = app(FinancialReportReadModelService::class);
    $summary = $svc->cashflowSummary([], $today, $today);

    expect($summary['total_receipt'])->toBe(0.0)
        ->and($summary['total_expense'])->toBe(0.0);
});

it('cashflowSummary aggregates expense and receipt separately across many records', function (): void {
    $branch = Branch::factory()->create();
    $today = now()->toDateString();

    // 5 receipts × 100k = 500k
    for ($i = 0; $i < 5; $i++) {
        seedReceiptExpense($branch, $today, 100_000, 'receipt');
    }

    // 3 expenses × 80k = 240k
    for ($i = 0; $i < 3; $i++) {
        seedReceiptExpense($branch, $today, 80_000, 'expense');
    }

    $svc = app(FinancialReportReadModelService::class);
    $summary = $svc->cashflowSummary([$branch->id], $today, $today);

    expect($summary['total_receipt'])->toBe(500_000.0)
        ->and($summary['total_expense'])->toBe(240_000.0);
});

// ---------------------------------------------------------------------------
// AppointmentReportReadModelService — date-range boundary
// ---------------------------------------------------------------------------

it('appointmentSummary excludes appointments outside the selected date range', function (): void {
    $branch = Branch::factory()->create();
    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    $today = now()->toDateString();
    $tomorrow = now()->addDay()->toDateString();
    $yesterday = now()->subDay()->toDateString();

    // Inside range
    Appointment::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'date' => $today,
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    // Outside range
    Appointment::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'date' => $tomorrow,
        'status' => Appointment::STATUS_SCHEDULED,
    ]);
    Appointment::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'date' => $yesterday,
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    $svc = app(AppointmentReportReadModelService::class);
    $summary = $svc->appointmentSummary([$branch->id], $today, $today);

    expect($summary['total'])->toBe(1);
});

it('appointmentSummary counts 10 appointments across 5 days correctly', function (): void {
    $branch = Branch::factory()->create();
    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    $dates = [];
    for ($i = 0; $i < 5; $i++) {
        $dates[] = now()->subDays($i)->toDateString();
        Appointment::factory()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'date' => $dates[$i],
            'status' => Appointment::STATUS_SCHEDULED,
        ]);
        Appointment::factory()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'date' => $dates[$i],
            'status' => Appointment::STATUS_COMPLETED,
        ]);
    }

    $from = min($dates);
    $until = max($dates);

    $svc = app(AppointmentReportReadModelService::class);
    $summary = $svc->appointmentSummary([$branch->id], $from, $until);

    expect($summary['total'])->toBe(10)
        ->and($summary['completed'])->toBe(5);
});

// ---------------------------------------------------------------------------
// PatientInsightReportReadModelService — date-range boundary
// ---------------------------------------------------------------------------

it('patientSummary excludes patients created outside the selected date range', function (): void {
    $branch = Branch::factory()->create();
    $today = now()->toDateString();
    $lastMonth = now()->subMonth()->toDateString();

    // Patient today — inside range
    Patient::factory()->create([
        'first_branch_id' => $branch->id,
        'created_at' => now(),
    ]);

    // Patient last month — outside today's range
    Patient::factory()->create([
        'first_branch_id' => $branch->id,
        'created_at' => now()->subMonth(),
    ]);

    $svc = app(PatientInsightReportReadModelService::class);
    $summary = $svc->patientSummary([$branch->id], $today, $today);

    // Only the patient created today should be in range
    expect($summary['total_patients'])->toBeGreaterThanOrEqual(1);

    // Now test that broadening range includes both
    $broadSummary = $svc->patientSummary([$branch->id], $lastMonth, $today);
    expect($broadSummary['total_patients'])->toBeGreaterThan($summary['total_patients']);
});

// ---------------------------------------------------------------------------
// InventorySupplyReportReadModelService — multi-record smoke test
// ---------------------------------------------------------------------------

it('materialInventorySummary counts usage records across multiple branches with isolation', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    // Seed Material records in each branch
    \App\Models\Material::factory()->count(3)->create(['branch_id' => $branchA->id]);
    \App\Models\Material::factory()->count(2)->create(['branch_id' => $branchB->id]);

    $svc = app(InventorySupplyReportReadModelService::class);

    $summaryA = $svc->materialInventorySummary([$branchA->id], null, null);
    $summaryB = $svc->materialInventorySummary([$branchB->id], null, null);
    $summaryBoth = $svc->materialInventorySummary([$branchA->id, $branchB->id], null, null);

    // Branch A has 3, B has 2 — isolation enforced
    expect($summaryA['total_materials'])->toBe(3)
        ->and($summaryB['total_materials'])->toBe(2)
        ->and($summaryBoth['total_materials'])->toBe(5);

    // Empty branch array → early return with zeros
    $empty = $svc->materialInventorySummary([], null, null);
    expect($empty['total_materials'])->toBe(0)
        ->and($empty['low_stock'])->toBe(0);
});

// ---------------------------------------------------------------------------
// Cross-service: branchIds=null means all branches (admin view)
// ---------------------------------------------------------------------------

it('financial report services accept null branchIds for admin unrestricted view', function (): void {
    $branch = Branch::factory()->create();
    $today = now()->toDateString();

    seedReceiptExpense($branch, $today, 250_000);

    $svc = app(FinancialReportReadModelService::class);
    $summary = $svc->cashflowSummary(null, $today, $today);

    // null = no branch filter → includes all branches
    expect($summary['total_receipt'])->toBeGreaterThanOrEqual(250_000.0);
});
