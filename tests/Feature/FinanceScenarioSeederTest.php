<?php

use App\Models\InstallmentPlan;
use App\Models\Invoice;
use App\Models\Note;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentReversalService;
use Database\Seeders\FinanceScenarioSeeder;
use Database\Seeders\LocalDemoDataSeeder;

use function Pest\Laravel\seed;

it('creates finance scenarios for overdue sync, reminders, dunning, and reversal smoke', function (): void {
    seed(LocalDemoDataSeeder::class);

    $manager = User::query()->where('email', 'manager.q1@demo.ident.test')->firstOrFail();
    $this->actingAs($manager);

    $overdueInvoice = Invoice::query()
        ->where('invoice_no', FinanceScenarioSeeder::OVERDUE_INVOICE_NO)
        ->firstOrFail();
    $receipt = Payment::query()
        ->where('transaction_ref', FinanceScenarioSeeder::REVERSAL_RECEIPT_TRANSACTION_REF)
        ->firstOrFail();
    $plan = InstallmentPlan::query()
        ->where('plan_code', FinanceScenarioSeeder::INSTALLMENT_PLAN_CODE)
        ->firstOrFail();

    $this->artisan('invoices:sync-overdue-status')->assertSuccessful();

    expect($overdueInvoice->fresh()->status)->toBe(Invoice::STATUS_OVERDUE);

    $this->artisan('finance:run-invoice-aging-reminders', [
        '--date' => now()->toDateString(),
    ])->assertSuccessful();
    $this->artisan('finance:run-invoice-aging-reminders', [
        '--date' => now()->toDateString(),
    ])->assertSuccessful();

    expect(Note::query()
        ->where('source_type', Invoice::class)
        ->where('source_id', $overdueInvoice->id)
        ->where('care_type', 'payment_reminder')
        ->count())->toBe(1);

    $this->artisan('installments:run-dunning', [
        '--date' => now()->toDateString(),
    ])->assertSuccessful();

    expect(Note::query()
        ->where('source_type', InstallmentPlan::class)
        ->where('source_id', $plan->id)
        ->where('care_type', 'payment_reminder')
        ->count())->toBe(1)
        ->and($plan->fresh()->dunning_level)->toBeGreaterThan(0);

    $refund = app(PaymentReversalService::class)->reverse(
        payment: $receipt,
        amount: 200_000,
        paidAt: now(),
        refundReason: 'Seed finance scenario refund',
        note: 'Seed finance scenario refund',
        actorId: $manager->id,
    );

    expect($receipt->fresh()->isReversed())->toBeTrue()
        ->and($refund->reversal_of_id)->toBe($receipt->id)
        ->and($refund->direction)->toBe('refund');
});
