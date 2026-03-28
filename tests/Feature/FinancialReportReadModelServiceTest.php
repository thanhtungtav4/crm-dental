<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\ReceiptExpense;
use App\Services\FinancialReportReadModelService;

it('summarizes cashflow and invoice balances within selected branches', function (): void {
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
        'invoice_no' => 'INV-RPT-A',
        'subtotal' => 2_000_000,
        'discount_amount' => 100_000,
        'tax_amount' => 0,
        'total_amount' => 1_900_000,
        'paid_amount' => 1_100_000,
        'status' => Invoice::STATUS_PARTIAL,
        'issued_at' => now(),
    ]);

    Invoice::query()->create([
        'patient_id' => $patientB->id,
        'branch_id' => $branchB->id,
        'invoice_no' => 'INV-RPT-B',
        'subtotal' => 5_000_000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total_amount' => 5_000_000,
        'paid_amount' => 1_000_000,
        'status' => Invoice::STATUS_PARTIAL,
        'issued_at' => now(),
    ]);

    ReceiptExpense::query()->create([
        'clinic_id' => $branchA->id,
        'patient_id' => $patientA->id,
        'invoice_id' => $invoiceA->id,
        'voucher_code' => 'RCPT-A1',
        'voucher_type' => 'receipt',
        'voucher_date' => now()->toDateString(),
        'group_code' => 'receipt',
        'category_code' => 'service',
        'amount' => 1_100_000,
        'payment_method' => 'transfer',
        'payer_or_receiver' => 'Khach A',
        'content' => 'Thu tien',
        'status' => ReceiptExpense::STATUS_POSTED,
    ]);

    ReceiptExpense::query()->create([
        'clinic_id' => $branchA->id,
        'patient_id' => $patientA->id,
        'voucher_code' => 'EX-A1',
        'voucher_type' => 'expense',
        'voucher_date' => now()->toDateString(),
        'group_code' => 'expense',
        'category_code' => 'ops',
        'amount' => 250_000,
        'payment_method' => 'cash',
        'payer_or_receiver' => 'Vendor A',
        'content' => 'Chi phi van hanh',
        'status' => ReceiptExpense::STATUS_POSTED,
    ]);

    ReceiptExpense::query()->create([
        'clinic_id' => $branchB->id,
        'patient_id' => $patientB->id,
        'voucher_code' => 'RCPT-B1',
        'voucher_type' => 'receipt',
        'voucher_date' => now()->toDateString(),
        'group_code' => 'receipt',
        'category_code' => 'service',
        'amount' => 999_000,
        'payment_method' => 'cash',
        'payer_or_receiver' => 'Khach B',
        'content' => 'Thu tien hidden',
        'status' => ReceiptExpense::STATUS_POSTED,
    ]);

    $service = app(FinancialReportReadModelService::class);

    expect($service->cashflowSummary([$branchA->id], now()->toDateString(), now()->toDateString()))
        ->toBe([
            'total_receipt' => 1_100_000.0,
            'total_expense' => 250_000.0,
        ])
        ->and($service->invoiceBalanceSummary([$branchA->id], now()->toDateString(), now()->toDateString()))
        ->toBe([
            'total_amount' => 1_900_000.0,
            'paid_amount' => 1_100_000.0,
            'balance' => 800_000.0,
        ]);
});

it('returns empty readers for inaccessible branch selections', function (): void {
    $service = app(FinancialReportReadModelService::class);

    expect($service->cashflowBreakdownQuery([])->get())->toHaveCount(0)
        ->and($service->invoiceBalanceQuery([])->get())->toHaveCount(0)
        ->and($service->cashflowSummary([], now()->toDateString(), now()->toDateString()))->toBe([
            'total_receipt' => 0.0,
            'total_expense' => 0.0,
        ])
        ->and($service->invoiceBalanceSummary([], now()->toDateString(), now()->toDateString()))->toBe([
            'total_amount' => 0.0,
            'paid_amount' => 0.0,
            'balance' => 0.0,
        ]);
});
