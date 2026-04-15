<?php

use App\Filament\Pages\FinancialDashboard;
use App\Filament\Resources\Payments\Widgets\PaymentMethodsChartWidget;
use App\Filament\Widgets\MonthlyRevenueChartWidget;
use App\Filament\Widgets\OutstandingBalanceWidget;
use App\Filament\Widgets\OverdueInvoicesWidget;
use App\Filament\Widgets\QuickFinancialStatsWidget;
use App\Filament\Widgets\RevenueOverviewWidget;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PlanItem;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

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

it('renders financial dashboard widgets through the shared read model service', function (): void {
    $now = Carbon::parse('2026-03-18 10:00:00');
    Carbon::setTestNow($now);

    $branch = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    createFinancialDashboardInvoice($branch, $doctor, $manager, [
        'invoice_no' => 'INV-WIDGET-001',
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

    createFinancialDashboardInvoice($branch, $doctor, $manager, [
        'invoice_no' => 'INV-WIDGET-002',
        'status' => Invoice::STATUS_OVERDUE,
        'total_amount' => 3_000_000,
        'paid_amount' => 1_000_000,
        'due_date' => $now->copy()->subDays(3)->toDateString(),
    ], [
        [
            'amount' => 1_000_000,
            'method' => 'card',
            'paid_at' => $now->copy()->subDay(),
        ],
    ]);

    $this->actingAs($manager)
        ->get(FinancialDashboard::getUrl())
        ->assertOk()
        ->assertSeeLivewire(RevenueOverviewWidget::class)
        ->assertSeeLivewire(OutstandingBalanceWidget::class)
        ->assertSeeLivewire(MonthlyRevenueChartWidget::class)
        ->assertSeeLivewire(PaymentMethodsChartWidget::class)
        ->assertSeeLivewire(OverdueInvoicesWidget::class)
        ->assertSeeLivewire(QuickFinancialStatsWidget::class);

    $this->actingAs($manager);

    Livewire::test(RevenueOverviewWidget::class)
        ->assertSee('Doanh thu hôm nay')
        ->assertSee('500.000đ')
        ->assertSee('Tổng công nợ')
        ->assertSee('3.500.000đ');

    Livewire::test(OutstandingBalanceWidget::class)
        ->assertSee('Hóa đơn quá hạn')
        ->assertSee('1 hóa đơn')
        ->assertSee('Nợ: 2.000.000đ');

    Livewire::test(OverdueInvoicesWidget::class)
        ->assertSee('Hóa đơn quá hạn (1 hóa đơn, nợ: 2.000.000đ)');

    Livewire::test(MonthlyRevenueChartWidget::class)
        ->assertSee('Doanh thu 12 tháng');

    Livewire::test(PaymentMethodsChartWidget::class)
        ->assertSee('Phân tích phương thức thanh toán');

    Livewire::test(QuickFinancialStatsWidget::class)
        ->assertSee('Tổng doanh thu')
        ->assertSee('1.500.000đ')
        ->assertSee('Tỷ lệ thanh toán');

    Carbon::setTestNow();
});

it('routes overdue invoice widget rows through the financial dashboard read model', function (): void {
    $widget = File::get(app_path('Filament/Widgets/OverdueInvoicesWidget.php'));

    expect($widget)
        ->toContain('->overdueInvoices(auth()->user())')
        ->not->toContain('->scopedInvoiceQuery()');
});

it('keeps the shared financial widget scope trait focused on visibility gating', function (): void {
    $trait = File::get(app_path('Filament/Widgets/Concerns/InteractsWithFinancialBranchScope.php'));

    expect($trait)
        ->toContain('FinancialAccess::canViewDashboard()')
        ->not->toContain('scopedInvoiceQuery(')
        ->not->toContain('scopedPaymentQuery(')
        ->not->toContain('scopeFinancialQueryToAccessibleBranches(');
});

it('routes payment methods chart widget data through the financial dashboard read model', function (): void {
    $widget = File::get(app_path('Filament/Resources/Payments/Widgets/PaymentMethodsChartWidget.php'));

    expect($widget)
        ->toContain('->paymentMethodChart($this->filter ?? \'month\', auth()->user())')
        ->not->toContain('ClinicRuntimeSettings::paymentMethodOptions(withEmoji: true)')
        ->not->toContain('->paymentMethodTotals($this->filter ?? \'month\', auth()->user())');
});
