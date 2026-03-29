<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\InstallmentPlan;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Services\FinanceOperationalReadModelService;
use Database\Seeders\FinanceScenarioSeeder;

it('summarizes finance operational signals and watchlist within selected branches', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $customerA = Customer::factory()->create(['branch_id' => $branchA->id]);
    $patientA = Patient::factory()->create([
        'customer_id' => $customerA->id,
        'first_branch_id' => $branchA->id,
    ]);

    $customerB = Customer::factory()->create(['branch_id' => $branchB->id]);
    $patientB = Patient::factory()->create([
        'customer_id' => $customerB->id,
        'first_branch_id' => $branchB->id,
    ]);

    $invoiceA = Invoice::query()->create([
        'patient_id' => $patientA->id,
        'branch_id' => $branchA->id,
        'invoice_no' => FinanceScenarioSeeder::OVERDUE_INVOICE_NO,
        'subtotal' => 2_000_000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total_amount' => 2_000_000,
        'paid_amount' => 500_000,
        'status' => Invoice::STATUS_ISSUED,
        'issued_at' => now()->subDays(3),
        'due_date' => now()->subDay()->toDateString(),
    ]);

    Invoice::query()->create([
        'patient_id' => $patientA->id,
        'branch_id' => $branchA->id,
        'invoice_no' => 'INV-PARTIAL-A',
        'subtotal' => 1_500_000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total_amount' => 1_500_000,
        'paid_amount' => 500_000,
        'status' => Invoice::STATUS_PARTIAL,
        'issued_at' => now()->subDays(2),
        'due_date' => now()->addDay()->toDateString(),
    ]);

    $receiptScenarioInvoice = Invoice::query()->create([
        'patient_id' => $patientA->id,
        'branch_id' => $branchA->id,
        'invoice_no' => 'INV-RECEIPT-SCENARIO-A',
        'subtotal' => 1_000_000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total_amount' => 1_000_000,
        'paid_amount' => 0,
        'status' => Invoice::STATUS_ISSUED,
        'issued_at' => now()->subDays(2),
        'due_date' => now()->addDays(2)->toDateString(),
    ]);

    $installmentScenarioInvoice = Invoice::query()->create([
        'patient_id' => $patientA->id,
        'branch_id' => $branchA->id,
        'invoice_no' => 'INV-INSTALLMENT-SCENARIO-A',
        'subtotal' => 1_500_000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total_amount' => 1_500_000,
        'paid_amount' => 300_000,
        'status' => Invoice::STATUS_PARTIAL,
        'issued_at' => now()->subDays(2),
        'due_date' => now()->addDays(3)->toDateString(),
    ]);

    Invoice::query()->create([
        'patient_id' => $patientA->id,
        'branch_id' => $branchA->id,
        'invoice_no' => 'INV-OVERDUE-A',
        'subtotal' => 900_000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total_amount' => 900_000,
        'paid_amount' => 100_000,
        'status' => Invoice::STATUS_OVERDUE,
        'issued_at' => now()->subDays(5),
        'due_date' => now()->subDays(2)->toDateString(),
    ]);

    $invoiceB = Invoice::query()->create([
        'patient_id' => $patientB->id,
        'branch_id' => $branchB->id,
        'invoice_no' => 'INV-HIDDEN-B',
        'subtotal' => 4_000_000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total_amount' => 4_000_000,
        'paid_amount' => 0,
        'status' => Invoice::STATUS_ISSUED,
        'issued_at' => now()->subDays(3),
        'due_date' => now()->subDay()->toDateString(),
    ]);

    Payment::query()->create([
        'invoice_id' => $receiptScenarioInvoice->id,
        'branch_id' => $branchA->id,
        'amount' => 500_000,
        'direction' => 'receipt',
        'method' => 'cash',
        'transaction_ref' => FinanceScenarioSeeder::REVERSAL_RECEIPT_TRANSACTION_REF,
        'payment_source' => 'manual',
        'paid_at' => now()->subDay(),
    ]);

    Payment::query()->create([
        'invoice_id' => $receiptScenarioInvoice->id,
        'branch_id' => $branchA->id,
        'amount' => 100_000,
        'direction' => 'receipt',
        'method' => 'cash',
        'transaction_ref' => 'RCPT-REVERSED-A',
        'payment_source' => 'manual',
        'paid_at' => now()->subDay(),
        'reversed_at' => now(),
    ]);

    Payment::query()->create([
        'invoice_id' => $invoiceB->id,
        'branch_id' => $branchB->id,
        'amount' => 800_000,
        'direction' => 'receipt',
        'method' => 'transfer',
        'transaction_ref' => 'RCPT-HIDDEN-B',
        'payment_source' => 'manual',
        'paid_at' => now()->subDay(),
    ]);

    InstallmentPlan::query()->create([
        'invoice_id' => $installmentScenarioInvoice->id,
        'patient_id' => $patientA->id,
        'branch_id' => $branchA->id,
        'plan_code' => FinanceScenarioSeeder::INSTALLMENT_PLAN_CODE,
        'financed_amount' => 1_500_000,
        'down_payment_amount' => 300_000,
        'remaining_amount' => 1_200_000,
        'number_of_installments' => 3,
        'installment_amount' => 400_000,
        'start_date' => now()->subMonths(2)->toDateString(),
        'next_due_date' => now()->subDay()->toDateString(),
        'status' => InstallmentPlan::STATUS_ACTIVE,
    ]);

    InstallmentPlan::query()->create([
        'invoice_id' => $invoiceB->id,
        'patient_id' => $patientB->id,
        'branch_id' => $branchB->id,
        'plan_code' => 'INS-HIDDEN-B',
        'financed_amount' => 2_000_000,
        'down_payment_amount' => 200_000,
        'remaining_amount' => 1_800_000,
        'number_of_installments' => 4,
        'installment_amount' => 450_000,
        'start_date' => now()->subMonths(2)->toDateString(),
        'next_due_date' => now()->subDay()->toDateString(),
        'status' => InstallmentPlan::STATUS_DEFAULTED,
    ]);

    $summary = app(FinanceOperationalReadModelService::class)->summary([$branchA->id]);

    expect($summary['visible_branch_count'])->toBe(1)
        ->and($summary['needs_overdue_sync_count'])->toBe(1)
        ->and($summary['overdue_count'])->toBe(1)
        ->and($summary['dunning_candidate_count'])->toBe(1)
        ->and($summary['reversible_receipt_count'])->toBe(1)
        ->and($summary['tone'])->toBe('danger')
        ->and($summary['status'])->toBe('Needs aging sync')
        ->and($summary['overdue_scenario_invoice']?->invoice_no)->toBe(FinanceScenarioSeeder::OVERDUE_INVOICE_NO)
        ->and($summary['reversal_scenario_payment']?->transaction_ref)->toBe(FinanceScenarioSeeder::REVERSAL_RECEIPT_TRANSACTION_REF)
        ->and($summary['installment_scenario_plan']?->plan_code)->toBe(FinanceScenarioSeeder::INSTALLMENT_PLAN_CODE)
        ->and((int) $summary['partial_count'])->toBeGreaterThanOrEqual(1);
});

it('returns empty finance operational signals when no branches are visible', function (): void {
    $summary = app(FinanceOperationalReadModelService::class)->summary([]);

    expect($summary['visible_branch_count'])->toBe(0)
        ->and($summary['needs_overdue_sync_count'])->toBe(0)
        ->and($summary['overdue_count'])->toBe(0)
        ->and($summary['partial_count'])->toBe(0)
        ->and($summary['dunning_candidate_count'])->toBe(0)
        ->and($summary['reversible_receipt_count'])->toBe(0)
        ->and($summary['tone'])->toBe('success')
        ->and($summary['status'])->toBe('Healthy')
        ->and($summary['overdue_scenario_invoice'])->toBeNull()
        ->and($summary['reversal_scenario_payment'])->toBeNull()
        ->and($summary['installment_scenario_plan'])->toBeNull();
});
