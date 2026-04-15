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
use App\Support\ClinicRuntimeSettings;
use Filament\Support\Icons\Heroicon;
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
    $paymentStatsCards = $service->paymentStatsCards($manager);
    $paymentMethodChart = $service->paymentMethodChart('month', $manager);
    $monthlySeries = $service->monthlyRevenueSeries('3months', $manager);
    $monthlyChart = $service->monthlyRevenueChart('3months', $manager);
    $revenueOverviewCards = $service->revenueOverviewCards($manager);
    $quickFinancialCards = $service->quickFinancialStatCards($manager);
    $outstandingCards = $service->outstandingBalanceCards($manager);
    $overdueHeading = $service->overdueInvoiceHeading($manager);
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

    expect($paymentStatsCards)->toMatchArray([
        'today' => [
            'label' => '💰 Tổng thu hôm nay',
            'value' => '500.000đ',
            'description' => 'Giảm 50% so với hôm qua',
            'description_icon' => 'heroicon-m-arrow-trending-down',
            'color' => 'danger',
            'chart' => [0.0, 0.0, 0.0, 0.0, 0.0, 1000000.0, 500000.0],
        ],
        'methods' => [
            'label' => '💳 Theo phương thức',
            'value' => '5.500.000đ',
            'description' => 'Tiền mặt: 500.000đ | Thẻ: 1.000.000đ',
            'description_icon' => 'heroicon-m-banknotes',
            'color' => 'info',
            'title' => 'Chuyển khoản: 4.000.000đ | Bảo hiểm: 0đ',
        ],
        'unpaid' => [
            'label' => '⏰ Hóa đơn chưa thanh toán',
            'value' => 1,
            'description' => 'Tổng: 1.000.000đ | Quá hạn: 1',
            'description_icon' => 'heroicon-m-exclamation-triangle',
            'color' => 'danger',
            'url' => route('filament.admin.resources.invoices.index', [
                'tableFilters' => ['status' => ['value' => ['issued', 'partial']]],
            ]),
        ],
    ]);

    expect($paymentMethodChart)->toMatchArray([
        'values' => [500000.0, 1000000.0],
        'labels' => [
            ClinicRuntimeSettings::paymentMethodOptions(withEmoji: true)['cash'],
            ClinicRuntimeSettings::paymentMethodOptions(withEmoji: true)['card'],
        ],
        'background_color' => [
            'rgba(34, 197, 94, 0.8)',
            'rgba(59, 130, 246, 0.8)',
        ],
        'border_color' => [
            'rgb(34, 197, 94)',
            'rgb(59, 130, 246)',
        ],
    ]);

    expect($monthlySeries['labels'])->toHaveCount(3)
        ->and($monthlySeries['revenue'])->toBe([0.0, 4000000.0, 1500000.0])
        ->and($monthlySeries['count'])->toBe([0, 1, 2]);

    expect($monthlyChart)->toMatchArray([
        'labels' => ['01/2026', '02/2026', '03/2026'],
        'datasets' => [
            [
                'label' => 'Doanh thu (VNĐ)',
                'data' => [0.0, 4000000.0, 1500000.0],
                'borderColor' => 'rgb(59, 130, 246)',
                'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                'fill' => true,
                'tension' => 0.4,
            ],
            [
                'label' => 'Số lượng thanh toán',
                'data' => [0, 1, 2],
                'borderColor' => 'rgb(16, 185, 129)',
                'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                'fill' => true,
                'tension' => 0.4,
                'yAxisID' => 'y1',
            ],
        ],
    ]);

    expect($revenueOverviewCards)->toMatchArray([
        'today' => [
            'label' => 'Doanh thu hôm nay',
            'value' => '500.000đ',
            'description' => '50% so với hôm qua',
            'description_icon' => Heroicon::OutlinedArrowTrendingDown,
            'color' => 'danger',
            'chart' => [0.0, 0.0, 0.0, 0.0, 0.0, 1000000.0, 500000.0],
            'title' => 'Tổng doanh thu từ các khoản thanh toán hôm nay',
        ],
        'month' => [
            'label' => 'Doanh thu tháng này',
            'value' => '1.500.000đ',
            'description' => '62.5% so với tháng trước',
            'description_icon' => Heroicon::OutlinedArrowTrendingDown,
            'color' => 'danger',
            'title' => 'Tổng doanh thu tháng 03/2026',
        ],
        'outstanding' => [
            'label' => 'Tổng công nợ',
            'value' => '4.500.000đ',
            'description' => '1 hóa đơn quá hạn',
            'description_icon' => Heroicon::OutlinedExclamationTriangle,
            'color' => 'danger',
            'title' => 'Tổng số tiền chưa thu được từ các hóa đơn',
            'url' => route('filament.admin.resources.invoices.index', [
                'tableFilters' => ['status' => ['values' => ['overdue', 'partial']]],
            ]),
        ],
    ]);

    expect($quickFinancialCards)->toMatchArray([
        'total_revenue' => [
            'label' => 'Tổng doanh thu',
            'value' => '5.500.000đ',
            'description' => '3 giao dịch | TB: 1.833.333đ',
            'description_icon' => Heroicon::OutlinedCurrencyDollar,
            'color' => 'success',
            'title' => 'Tổng doanh thu từ tất cả các khoản thanh toán',
        ],
        'payment_rate' => [
            'label' => 'Tỷ lệ thanh toán',
            'value' => '25%',
            'description' => '1/4 hóa đơn đã thanh toán đầy đủ',
            'description_icon' => Heroicon::OutlinedCheckCircle,
            'color' => 'danger',
            'chart' => [25.0, 75.0],
            'title' => 'Phần trăm hóa đơn đã thanh toán hoàn tất',
        ],
        'cash_mix' => [
            'label' => 'Tiền mặt / Phi tiền mặt',
            'value' => '500.000đ',
            'description' => 'Phi tiền mặt: 5.000.000đ (90.9%)',
            'description_icon' => Heroicon::OutlinedCreditCard,
            'color' => 'info',
            'title' => 'So sánh thanh toán tiền mặt và phi tiền mặt',
        ],
        'invoice_average' => [
            'label' => 'Giá trị HĐ trung bình',
            'value' => '2.500.000đ',
            'description' => 'Cao nhất: 4.000.000đ',
            'description_icon' => Heroicon::OutlinedDocumentText,
            'color' => 'info',
            'title' => 'Giá trị trung bình của các hóa đơn',
        ],
        'payment_frequency' => [
            'label' => 'Tần suất thanh toán',
            'value' => '2 thanh toán/tháng',
            'description' => '+100% so với tháng trước',
            'description_icon' => Heroicon::OutlinedArrowTrendingUp,
            'color' => 'success',
            'title' => 'Số lượng thanh toán trung bình mỗi tháng',
        ],
    ]);

    expect($outstandingCards)->toMatchArray([
        'unpaid' => [
            'label' => 'Hóa đơn chưa thanh toán',
            'value' => '1 hóa đơn',
            'description' => 'Tổng: 1.000.000đ',
            'description_icon' => Heroicon::OutlinedDocumentText,
            'color' => 'warning',
            'title' => 'Hóa đơn chưa có khoản thanh toán nào',
            'url' => route('filament.admin.resources.invoices.index', [
                'tableFilters' => ['payment_progress' => ['value' => 'unpaid']],
            ]),
        ],
        'partial' => [
            'label' => 'Thanh toán một phần',
            'value' => '2 hóa đơn',
            'description' => 'Còn lại: 3.500.000đ',
            'description_icon' => Heroicon::OutlinedClock,
            'color' => 'info',
            'title' => 'Hóa đơn đã thanh toán một phần',
            'url' => route('filament.admin.resources.invoices.index', [
                'tableFilters' => ['status' => ['values' => ['partial']]],
            ]),
        ],
        'overdue' => [
            'label' => 'Hóa đơn quá hạn',
            'value' => '1 hóa đơn',
            'description' => 'Nợ: 2.000.000đ',
            'description_icon' => Heroicon::OutlinedExclamationTriangle,
            'color' => 'danger',
            'title' => 'Hóa đơn đã quá ngày đến hạn',
            'url' => route('filament.admin.resources.invoices.index', [
                'tableFilters' => ['status' => ['values' => ['overdue']]],
            ]),
        ],
        'week' => [
            'label' => 'Thu tuần này',
            'value' => '1.500.000đ',
            'description' => '2 giao dịch',
            'description_icon' => Heroicon::OutlinedBanknotes,
            'color' => 'success',
            'title' => 'Tổng thu từ đầu tuần đến nay',
            'url' => route('filament.admin.resources.payments.index'),
        ],
    ]);

    expect($overdueHeading)->toBe('Hóa đơn quá hạn (1 hóa đơn, nợ: 2.000.000đ)');

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
    $methodChart = app(FinancialDashboardReadModelService::class)->paymentMethodChart('today', $admin);

    expect($overview['today_revenue'])->toBe(1600000.0)
        ->and($methodTotals)->toMatchArray([
            'cash' => 700000.0,
            'card' => 900000.0,
        ]);
    expect($methodChart['labels'])->toBe([
        ClinicRuntimeSettings::paymentMethodOptions(withEmoji: true)['cash'],
        ClinicRuntimeSettings::paymentMethodOptions(withEmoji: true)['card'],
    ]);
    expect($methodChart['values'])->toBe([700000.0, 900000.0]);

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
