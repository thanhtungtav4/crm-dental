<?php

use App\Filament\Resources\Payments\Widgets\PaymentStatsWidget;
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

it('scopes payment stats widget to the manager branch', function (): void {
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
        'invoice_no' => 'INV-PAYMENT-STATS-A1',
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
        'invoice_no' => 'INV-PAYMENT-STATS-A2',
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 1_000_000,
        'paid_amount' => 0,
    ]);

    createFinancialDashboardInvoice($branchB, $doctorB, $manager, [
        'invoice_no' => 'INV-PAYMENT-STATS-B1',
        'status' => Invoice::STATUS_PAID,
        'total_amount' => 7_777_000,
        'paid_amount' => 7_777_000,
    ], [
        [
            'amount' => 7_777_000,
            'method' => 'card',
            'paid_at' => $now->copy(),
        ],
    ]);

    $this->actingAs($manager);

    Livewire::test(PaymentStatsWidget::class)
        ->assertSee('💰 Tổng thu hôm nay')
        ->assertSee('500.000đ')
        ->assertDontSee('8.277.000đ')
        ->assertSee('⏰ Hóa đơn chưa thanh toán')
        ->assertSee('1.000.000đ | Quá hạn: 0');

    Carbon::setTestNow();
});
