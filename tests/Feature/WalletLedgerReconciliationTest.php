<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientWallet;
use App\Models\Payment;
use App\Models\TreatmentPlan;
use App\Models\WalletLedgerEntry;
use App\Services\PatientWalletService;
use Illuminate\Validation\ValidationException;

// ---------------------------------------------------------------------------
// Helper: create a deposit Payment linked to an invoice for a patient
// ---------------------------------------------------------------------------
function makeDepositPayment(Patient $patient, Branch $branch, float $amount, \App\Models\User $receiver): Payment
{
    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
    ]);
    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'treatment_plan_id' => $plan->id,
    ]);

    return Payment::query()->create([
        'invoice_id' => $invoice->id,
        'branch_id' => $branch->id,
        'amount' => $amount,
        'direction' => 'receipt',
        'payment_source' => 'cash',
        'is_deposit' => true,
        'method' => 'cash',
        'paid_at' => now(),
        'received_by' => $receiver->id,
    ]);
}

// ---------------------------------------------------------------------------
// Helper: create a wallet-spend Payment (direction=receipt, source=wallet)
// ---------------------------------------------------------------------------
function makeWalletSpendPayment(Patient $patient, Branch $branch, float $amount, \App\Models\User $receiver): Payment
{
    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
    ]);
    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'treatment_plan_id' => $plan->id,
    ]);

    return Payment::query()->create([
        'invoice_id' => $invoice->id,
        'branch_id' => $branch->id,
        'amount' => $amount,
        'direction' => 'receipt',
        'payment_source' => 'wallet',
        'is_deposit' => false,
        'method' => 'wallet',
        'paid_at' => now(),
        'received_by' => $receiver->id,
    ]);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('creates wallet and CREDIT ledger entry when deposit payment is posted', function (): void {
    $branch = Branch::factory()->create();
    $actor = \App\Models\User::factory()->create(['branch_id' => $branch->id]);
    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    $payment = makeDepositPayment($patient, $branch, 500_000, $actor);

    // PaymentObserver fires postPayment on create, wallet already exists
    $wallet = PatientWallet::query()->where('patient_id', $patient->id)->firstOrFail();
    expect((float) $wallet->balance)->toBe(500_000.0)
        ->and((float) $wallet->total_deposit)->toBe(500_000.0);

    $entry = WalletLedgerEntry::query()
        ->where('payment_id', $payment->id)
        ->where('entry_type', 'deposit')
        ->firstOrFail();

    expect($entry->direction)->toBe(WalletLedgerEntry::DIRECTION_CREDIT)
        ->and((float) $entry->amount)->toBe(500_000.0)
        ->and((float) $entry->balance_before)->toBe(0.0)
        ->and((float) $entry->balance_after)->toBe(500_000.0);
});

it('records DEBIT ledger entry when wallet-spend payment is posted', function (): void {
    $branch = Branch::factory()->create();
    $actor = \App\Models\User::factory()->create(['branch_id' => $branch->id]);
    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    // Pre-fund wallet
    PatientWallet::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'balance' => 800_000,
        'total_deposit' => 800_000,
    ]);

    $payment = makeWalletSpendPayment($patient, $branch, 300_000, $actor);

    app(PatientWalletService::class)->postPayment($payment);

    $wallet = PatientWallet::query()->where('patient_id', $patient->id)->firstOrFail();
    expect((float) $wallet->balance)->toBe(500_000.0)
        ->and((float) $wallet->total_spent)->toBe(300_000.0);

    $entry = WalletLedgerEntry::query()
        ->where('payment_id', $payment->id)
        ->where('entry_type', 'spend')
        ->firstOrFail();

    expect($entry->direction)->toBe(WalletLedgerEntry::DIRECTION_DEBIT)
        ->and((float) $entry->amount)->toBe(300_000.0)
        ->and((float) $entry->balance_before)->toBe(800_000.0)
        ->and((float) $entry->balance_after)->toBe(500_000.0);
});

it('postPayment is idempotent — double-call does not duplicate ledger entry or change balance', function (): void {
    $branch = Branch::factory()->create();
    $actor = \App\Models\User::factory()->create(['branch_id' => $branch->id]);
    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    $payment = makeDepositPayment($patient, $branch, 200_000, $actor);

    $svc = app(PatientWalletService::class);
    $svc->postPayment($payment);
    $svc->postPayment($payment); // second call — should be no-op

    $wallet = PatientWallet::query()->where('patient_id', $patient->id)->firstOrFail();
    expect((float) $wallet->balance)->toBe(200_000.0)
        ->and((float) $wallet->total_deposit)->toBe(200_000.0);

    $entryCount = WalletLedgerEntry::query()
        ->where('payment_id', $payment->id)
        ->where('entry_type', 'deposit')
        ->count();

    expect($entryCount)->toBe(1);
});

it('balance chain is consistent across deposit → spend sequence', function (): void {
    $branch = Branch::factory()->create();
    $actor = \App\Models\User::factory()->create(['branch_id' => $branch->id]);
    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    // Pre-create wallet so postPayment doesn't need to create it
    $wallet = PatientWallet::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'balance' => 0,
    ]);

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
    ]);
    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'treatment_plan_id' => $plan->id,
    ]);

    // Deposit payment — is_deposit+receipt → CREDIT deposit entry
    $depositPayment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'branch_id' => $branch->id,
        'amount' => 1_000_000,
        'direction' => 'receipt',
        'payment_source' => 'cash',
        'is_deposit' => true,
        'method' => 'cash',
        'paid_at' => now(),
        'received_by' => $actor->id,
    ]);

    // Wallet refreshed by Observer; now manually post a spend using service directly
    $wallet->refresh();
    $wallet->balance = 1_000_000;
    $wallet->total_deposit = 1_000_000;
    $wallet->save();

    // Wallet spend payment — payment_source=wallet, direction=receipt → DEBIT spend entry
    $spendPayment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'branch_id' => $branch->id,
        'amount' => 400_000,
        'direction' => 'receipt',
        'payment_source' => 'wallet',
        'is_deposit' => false,
        'method' => 'wallet',
        'paid_at' => now(),
        'received_by' => $actor->id,
    ]);

    $entries = WalletLedgerEntry::query()
        ->where('patient_id', $patient->id)
        ->whereIn('payment_id', [$depositPayment->id, $spendPayment->id])
        ->orderBy('id')
        ->get();

    expect($entries)->toHaveCount(2);

    $depositEntry = $entries->firstWhere('entry_type', 'deposit');
    $spendEntry = $entries->firstWhere('entry_type', 'spend');

    expect($depositEntry)->not->toBeNull()
        ->and($spendEntry)->not->toBeNull();

    // Direction checks
    expect($depositEntry->direction)->toBe(WalletLedgerEntry::DIRECTION_CREDIT)
        ->and($spendEntry->direction)->toBe(WalletLedgerEntry::DIRECTION_DEBIT);

    // After both entries, wallet balance reflects the spend (wallet was updated by observer for spend)
    $wallet->refresh();
    expect((float) $wallet->balance)->toBe((float) $spendEntry->balance_after);
});

it('wallet-refund payment creates CREDIT ledger entry and restores balance', function (): void {
    $branch = Branch::factory()->create();
    $actor = \App\Models\User::factory()->create(['branch_id' => $branch->id]);
    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    PatientWallet::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'balance' => 0,
        'total_spent' => 500_000,
    ]);

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
    ]);
    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'treatment_plan_id' => $plan->id,
    ]);

    $refundPayment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'branch_id' => $branch->id,
        'amount' => 200_000,
        'direction' => 'refund',
        'payment_source' => 'wallet',
        'is_deposit' => false,
        'method' => 'wallet',
        'paid_at' => now(),
        'received_by' => $actor->id,
    ]);

    app(PatientWalletService::class)->postPayment($refundPayment);

    $wallet = PatientWallet::query()->where('patient_id', $patient->id)->firstOrFail();
    expect((float) $wallet->balance)->toBe(200_000.0);

    $entry = WalletLedgerEntry::query()
        ->where('payment_id', $refundPayment->id)
        ->where('entry_type', 'refund')
        ->firstOrFail();

    expect($entry->direction)->toBe(WalletLedgerEntry::DIRECTION_CREDIT)
        ->and((float) $entry->amount)->toBe(200_000.0);
});

it('postPayment skips posting when payment has no wallet-relevant source', function (): void {
    $branch = Branch::factory()->create();
    $actor = \App\Models\User::factory()->create(['branch_id' => $branch->id]);
    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
    ]);
    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'treatment_plan_id' => $plan->id,
    ]);

    // Cash payment, not deposit, not wallet-source → no ledger impact
    $cashPayment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'branch_id' => $branch->id,
        'amount' => 300_000,
        'direction' => 'receipt',
        'payment_source' => 'cash',
        'is_deposit' => false,
        'method' => 'cash',
        'paid_at' => now(),
        'received_by' => $actor->id,
    ]);

    app(PatientWalletService::class)->postPayment($cashPayment);

    expect(WalletLedgerEntry::query()->where('payment_id', $cashPayment->id)->count())->toBe(0)
        ->and(PatientWallet::query()->where('patient_id', $patient->id)->count())->toBe(0);
});

it('adjustment audit entry contains correct balance_before and balance_after', function (): void {
    $branch = Branch::factory()->create();
    $manager = \App\Models\User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $wallet = PatientWallet::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'balance' => 600_000,
    ]);

    $this->actingAs($manager);

    $entry = app(PatientWalletService::class)->adjustBalance(
        wallet: $wallet,
        amount: 100_000,
        note: 'Reconciliation top-up',
        actorId: $manager->id,
    );

    expect((float) $entry->balance_before)->toBe(600_000.0)
        ->and((float) $entry->balance_after)->toBe(700_000.0)
        ->and($entry->direction)->toBe(WalletLedgerEntry::DIRECTION_CREDIT)
        ->and($entry->entry_type)->toBe('adjustment');

    $wallet->refresh();
    expect((float) $wallet->balance)->toBe(700_000.0);

    $auditLog = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_PATIENT_WALLET)
        ->where('entity_id', $wallet->id)
        ->where('action', AuditLog::ACTION_UPDATE)
        ->latest()
        ->firstOrFail();

    expect((float) $auditLog->metadata['balance_before'])->toBe(600_000.0)
        ->and((float) $auditLog->metadata['balance_after'])->toBe(700_000.0)
        ->and($auditLog->metadata['trigger'])->toBe('manual_wallet_adjustment');
});

it('rejects adjustment that would make wallet balance negative', function (): void {
    $branch = Branch::factory()->create();
    $manager = \App\Models\User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $wallet = PatientWallet::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'balance' => 100_000,
    ]);

    $this->actingAs($manager);

    expect(fn () => app(PatientWalletService::class)->adjustBalance(
        wallet: $wallet,
        amount: -200_000,
        note: 'Should fail',
        actorId: $manager->id,
    ))->toThrow(ValidationException::class, 'âm');
});
