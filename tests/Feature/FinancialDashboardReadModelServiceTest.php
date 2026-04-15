<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PlanItem;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Services\FinancialDashboardReadModelService;
use Illuminate\Support\Carbon;

if (! function_exists('createFinancialDashboardInvoice')) {
    function createFinancialDashboardInvoice(
        Branch $branch,
        User $doctor,
        User $receiver,
        array $invoiceAttributes = [],
        array $payments = [],
    ): Invoice {
        $customer = Customer::factory()->create([
            'branch_id' => $branch->id,
        ]);

        $patient = Patient::factory()->create([
            'customer_id' => $customer->id,
            'first_branch_id' => $branch->id,
            'full_name' => $customer->full_name,
            'phone' => $customer->phone,
            'email' => $customer->email,
        ]);

        $plan = TreatmentPlan::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
        ]);

        $planItem = PlanItem::factory()->create([
            'treatment_plan_id' => $plan->id,
        ]);

        $session = TreatmentSession::factory()->create([
            'treatment_plan_id' => $plan->id,
            'plan_item_id' => $planItem->id,
            'doctor_id' => $doctor->id,
            'status' => 'scheduled',
        ]);

        $invoice = Invoice::factory()->create(array_merge([
            'treatment_session_id' => $session->id,
            'treatment_plan_id' => $plan->id,
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 1_000_000,
            'paid_amount' => 0,
            'due_date' => now()->addDays(7)->toDateString(),
        ], $invoiceAttributes));

        foreach ($payments as $paymentAttributes) {
            Payment::factory()->create(array_merge([
                'invoice_id' => $invoice->id,
                'branch_id' => $branch->id,
                'received_by' => $receiver->id,
                'direction' => 'receipt',
                'payment_source' => 'patient',
            ], $paymentAttributes));
        }

        return $invoice;
    }
}

it('returns financial dashboard aggregates scoped to the manager branch', function (): void {
    $now = Carbon::parse('2026-03-18 10:00:00');
    Carbon::setTestNow($now);

    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $doctorA = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $doctorA->assignRole('Doctor');

    $doctorB = User::factory()->create([
        'branch_id' => $branchB->id,
    ]);
    $doctorB->assignRole('Doctor');

    createFinancialDashboardInvoice($branchA, $doctorA, $manager, [
        'invoice_no' => 'INV-DASH-001',
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 1_000_000,
        'paid_amount' => 0,
    ]);

    createFinancialDashboardInvoice($branchA, $doctorA, $manager, [
        'invoice_no' => 'INV-DASH-002',
        'status' => Invoice::STATUS_PARTIAL,
        'total_amount' => 2_000_000,
        'paid_amount' => 500_000,
    ], [
        [
            'amount' => 500_000,
            'method' => 'cash',
            'paid_at' => $now->copy(),
        ],
    ]);

    createFinancialDashboardInvoice($branchA, $doctorA, $manager, [
        'invoice_no' => 'INV-DASH-003',
        'status' => Invoice::STATUS_OVERDUE,
        'total_amount' => 3_000_000,
        'paid_amount' => 1_000_000,
        'due_date' => $now->copy()->subDays(5)->toDateString(),
    ], [
        [
            'amount' => 1_000_000,
            'method' => 'card',
            'paid_at' => $now->copy()->subDay(),
        ],
    ]);

    createFinancialDashboardInvoice($branchA, $doctorA, $manager, [
        'invoice_no' => 'INV-DASH-004',
        'status' => Invoice::STATUS_PAID,
        'total_amount' => 4_000_000,
        'paid_amount' => 4_000_000,
    ], [
        [
            'amount' => 4_000_000,
            'method' => 'transfer',
            'paid_at' => $now->copy()->subMonth(),
        ],
    ]);

    createFinancialDashboardInvoice($branchB, $doctorB, $manager, [
        'invoice_no' => 'INV-DASH-005',
        'status' => Invoice::STATUS_PAID,
        'total_amount' => 9_000_000,
        'paid_amount' => 9_000_000,
    ], [
        [
            'amount' => 9_000_000,
            'method' => 'cash',
            'paid_at' => $now->copy(),
        ],
    ]);

    $service = app(FinancialDashboardReadModelService::class);

    $overview = $service->revenueOverview($manager);
    $balances = $service->outstandingBalances($manager);
    $quickStats = $service->quickStats($manager);
    $paymentStats = $service->paymentStatsSnapshot($manager);
    $monthlySeries = $service->monthlyRevenueSeries('3months', $manager);
    $methodTotals = $service->paymentMethodTotals('month', $manager);

    expect($overview)
        ->toMatchArray([
            'today_revenue' => 500000.0,
            'yesterday_revenue' => 1000000.0,
            'today_change' => -50.0,
            'this_month_revenue' => 1500000.0,
            'last_month_revenue' => 4000000.0,
            'month_change' => -62.5,
            'total_outstanding' => 4500000.0,
            'overdue_count' => 1,
        ])
        ->and($overview['last_7_days'])->toHaveCount(7)
        ->and($overview['last_7_days'][5])->toBe(1000000.0)
        ->and($overview['last_7_days'][6])->toBe(500000.0);

    expect($balances)->toMatchArray([
        'unpaid_count' => 1,
        'unpaid_total' => 1000000.0,
        'partial_count' => 2,
        'partial_balance' => 3500000.0,
        'overdue_count' => 1,
        'overdue_balance' => 2000000.0,
        'week_collections' => 1500000.0,
        'week_payments_count' => 2,
    ]);

    expect($quickStats)->toMatchArray([
        'total_revenue' => 5500000.0,
        'total_payments' => 3,
        'avg_payment' => 1833333.33,
        'total_invoices' => 4,
        'paid_invoices' => 1,
        'paid_percentage' => 25.0,
        'cash_payments' => 500000.0,
        'card_payments' => 1000000.0,
        'transfer_payments' => 4000000.0,
        'non_cash_total' => 5000000.0,
        'cash_percentage' => 9.1,
        'avg_invoice' => 2500000.0,
        'highest_invoice' => 4000000.0,
        'this_month_payments' => 2,
        'last_month_payments' => 1,
        'frequency_change' => 100.0,
    ]);

    expect($paymentStats)
        ->toMatchArray([
            'today_revenue' => 500000.0,
            'today_change' => -50.0,
            'method_total' => 5500000.0,
            'cash_payments' => 500000.0,
            'card_payments' => 1000000.0,
            'transfer_payments' => 4000000.0,
            'insurance_payments' => 0.0,
            'unpaid_count' => 1,
            'unpaid_total' => 1000000.0,
            'overdue_count' => 1,
        ])
        ->and($paymentStats['last_7_days'])->toHaveCount(7)
        ->and($paymentStats['last_7_days'][5])->toBe(1000000.0)
        ->and($paymentStats['last_7_days'][6])->toBe(500000.0);

    expect($monthlySeries['labels'])->toHaveCount(3)
        ->and($monthlySeries['revenue'])->toBe([0.0, 4000000.0, 1500000.0])
        ->and($monthlySeries['count'])->toBe([0, 1, 2]);

    expect($methodTotals)->toMatchArray([
        'cash' => 500000.0,
        'card' => 1000000.0,
    ]);

    Carbon::setTestNow();
});

it('allows admins to aggregate across all branches', function (): void {
    $now = Carbon::parse('2026-03-18 10:00:00');
    Carbon::setTestNow($now);

    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $doctorA = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $doctorA->assignRole('Doctor');

    $doctorB = User::factory()->create([
        'branch_id' => $branchB->id,
    ]);
    $doctorB->assignRole('Doctor');

    createFinancialDashboardInvoice($branchA, $doctorA, $admin, [
        'invoice_no' => 'INV-ADMIN-A',
        'status' => Invoice::STATUS_PAID,
        'total_amount' => 700_000,
        'paid_amount' => 700_000,
    ], [
        [
            'amount' => 700_000,
            'method' => 'cash',
            'paid_at' => $now->copy(),
        ],
    ]);

    createFinancialDashboardInvoice($branchB, $doctorB, $admin, [
        'invoice_no' => 'INV-ADMIN-B',
        'status' => Invoice::STATUS_PAID,
        'total_amount' => 900_000,
        'paid_amount' => 900_000,
    ], [
        [
            'amount' => 900_000,
            'method' => 'card',
            'paid_at' => $now->copy(),
        ],
    ]);

    $overview = app(FinancialDashboardReadModelService::class)->revenueOverview($admin);
    $methodTotals = app(FinancialDashboardReadModelService::class)->paymentMethodTotals('today', $admin);

    expect($overview['today_revenue'])->toBe(1600000.0)
        ->and($methodTotals)->toMatchArray([
            'cash' => 700000.0,
            'card' => 900000.0,
        ]);

    Carbon::setTestNow();
});

it('returns overdue invoices scoped and ordered for dashboard widgets', function (): void {
    $now = Carbon::parse('2026-03-18 10:00:00');
    Carbon::setTestNow($now);

    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $doctorA = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $doctorA->assignRole('Doctor');

    $doctorB = User::factory()->create([
        'branch_id' => $branchB->id,
    ]);
    $doctorB->assignRole('Doctor');

    createFinancialDashboardInvoice($branchA, $doctorA, $manager, [
        'invoice_no' => 'INV-OVERDUE-001',
        'status' => Invoice::STATUS_OVERDUE,
        'total_amount' => 1_800_000,
        'paid_amount' => 300_000,
        'due_date' => $now->copy()->subDays(7)->toDateString(),
    ]);

    createFinancialDashboardInvoice($branchA, $doctorA, $manager, [
        'invoice_no' => 'INV-OVERDUE-002',
        'status' => Invoice::STATUS_OVERDUE,
        'total_amount' => 2_400_000,
        'paid_amount' => 900_000,
        'due_date' => $now->copy()->subDays(2)->toDateString(),
    ]);

    createFinancialDashboardInvoice($branchA, $doctorA, $manager, [
        'invoice_no' => 'INV-ISSUED-003',
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 1_100_000,
        'paid_amount' => 0,
        'due_date' => $now->copy()->subDay()->toDateString(),
    ]);

    createFinancialDashboardInvoice($branchB, $doctorB, $manager, [
        'invoice_no' => 'INV-OVERDUE-004',
        'status' => Invoice::STATUS_OVERDUE,
        'total_amount' => 3_000_000,
        'paid_amount' => 0,
        'due_date' => $now->copy()->subDays(10)->toDateString(),
    ]);

    $invoices = app(FinancialDashboardReadModelService::class)
        ->overdueInvoices($manager)
        ->get();

    expect($invoices->pluck('invoice_no')->all())->toBe([
        'INV-OVERDUE-001',
        'INV-OVERDUE-002',
    ])
        ->and($invoices)->toHaveCount(2)
        ->and($invoices->every(fn (Invoice $invoice): bool => $invoice->branch_id === $branchA->id))->toBeTrue()
        ->and($invoices->every(fn (Invoice $invoice): bool => $invoice->relationLoaded('patient')))->toBeTrue()
        ->and($invoices->every(fn (Invoice $invoice): bool => $invoice->relationLoaded('plan')))->toBeTrue();

    Carbon::setTestNow();
});
