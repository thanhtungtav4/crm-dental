<?php

use App\Models\Branch;
use App\Models\InventoryTransaction;
use App\Models\Invoice;
use App\Models\Material;
use App\Models\Patient;
use App\Models\PatientWallet;
use App\Models\Payment;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use Illuminate\Validation\ValidationException;

// ---------------------------------------------------------------------------
// Helper: build a deposit payment so PaymentObserver fires postPayment,
// creating a WalletLedgerEntry automatically.
// ---------------------------------------------------------------------------
function makeDepositWithObserver(
    Patient $patient,
    Branch $branch,
    \App\Models\User $actor,
    float $amount = 200_000,
): Payment {
    PatientWallet::query()->firstOrCreate(
        ['patient_id' => $patient->id],
        ['branch_id' => $branch->id, 'balance' => 0],
    );

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
    ]);
    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'treatment_plan_id' => $plan->id,
    ]);

    // is_deposit + direction=receipt → Observer calls postPayment → creates WalletLedgerEntry(type=deposit)
    return Payment::query()->create([
        'invoice_id' => $invoice->id,
        'branch_id' => $branch->id,
        'amount' => $amount,
        'direction' => 'receipt',
        'payment_source' => 'cash',
        'is_deposit' => true,
        'method' => 'cash',
        'paid_at' => now(),
        'received_by' => $actor->id,
    ]);
}

// ---------------------------------------------------------------------------
// WalletLedgerEntry — immutability contract
// ---------------------------------------------------------------------------

it('WalletLedgerEntry blocks direct update after creation', function (): void {
    $branch = Branch::factory()->create();
    $actor = User::factory()->create(['branch_id' => $branch->id]);
    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    $payment = makeDepositWithObserver($patient, $branch, $actor);

    $entry = WalletLedgerEntry::query()
        ->where('payment_id', $payment->id)
        ->where('entry_type', 'deposit')
        ->firstOrFail();

    expect(fn () => $entry->update(['amount' => 999_999]))->toThrow(ValidationException::class);
});

it('WalletLedgerEntry blocks delete after creation', function (): void {
    $branch = Branch::factory()->create();
    $actor = User::factory()->create(['branch_id' => $branch->id]);
    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    $payment = makeDepositWithObserver($patient, $branch, $actor);

    $entry = WalletLedgerEntry::query()
        ->where('payment_id', $payment->id)
        ->where('entry_type', 'deposit')
        ->firstOrFail();

    expect(fn () => $entry->delete())->toThrow(ValidationException::class);
});

it('WalletLedgerEntry firstOrCreate with same payment_id + entry_type is idempotent', function (): void {
    $branch = Branch::factory()->create();
    $actor = User::factory()->create(['branch_id' => $branch->id]);
    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    $payment = makeDepositWithObserver($patient, $branch, $actor);

    // Observer already created the entry; firstOrCreate should return the same row
    $wallet = PatientWallet::query()->where('patient_id', $patient->id)->firstOrFail();

    $first = WalletLedgerEntry::query()->firstOrCreate(
        ['payment_id' => $payment->id, 'entry_type' => 'deposit'],
        ['patient_wallet_id' => $wallet->id, 'patient_id' => $patient->id, 'branch_id' => $branch->id,
            'direction' => WalletLedgerEntry::DIRECTION_CREDIT, 'amount' => 200_000,
            'balance_before' => 0, 'balance_after' => 200_000, 'note' => 'Idempotency test'],
    );

    $second = WalletLedgerEntry::query()->firstOrCreate(
        ['payment_id' => $payment->id, 'entry_type' => 'deposit'],
        ['patient_wallet_id' => $wallet->id, 'patient_id' => $patient->id, 'branch_id' => $branch->id,
            'direction' => WalletLedgerEntry::DIRECTION_CREDIT, 'amount' => 200_000,
            'balance_before' => 0, 'balance_after' => 200_000, 'note' => 'Idempotency test'],
    );

    expect($first->id)->toBe($second->id)
        ->and(WalletLedgerEntry::query()->where('payment_id', $payment->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// InventoryTransaction — immutability contract (standalone model-level guard)
// ---------------------------------------------------------------------------

it('InventoryTransaction blocks direct update after creation', function (): void {
    $branch = Branch::factory()->create();
    $actor = User::factory()->create(['branch_id' => $branch->id]);
    $material = Material::factory()->create();

    $tx = InventoryTransaction::query()->create([
        'material_id' => $material->id,
        'branch_id' => $branch->id,
        'type' => 'issue',
        'quantity' => 2,
        'unit_cost' => 50_000,
        'created_by' => $actor->id,
    ]);

    expect(fn () => $tx->update(['quantity' => 99]))->toThrow(ValidationException::class);
});

it('InventoryTransaction blocks delete after creation', function (): void {
    $branch = Branch::factory()->create();
    $actor = User::factory()->create(['branch_id' => $branch->id]);
    $material = Material::factory()->create();

    $tx = InventoryTransaction::query()->create([
        'material_id' => $material->id,
        'branch_id' => $branch->id,
        'type' => 'adjust',
        'quantity' => 5,
        'unit_cost' => 10_000,
        'created_by' => $actor->id,
    ]);

    expect(fn () => $tx->delete())->toThrow(ValidationException::class);
});

// ---------------------------------------------------------------------------
// Reversal creates entry of opposite direction to original
// ---------------------------------------------------------------------------

it('reversal ledger entry carries opposite direction to original spend entry', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    // Pre-fund wallet so spend won't go negative
    PatientWallet::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'balance' => 500_000,
        'total_deposit' => 500_000,
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

    // Wallet spend → Observer postPayment → DEBIT ledger entry
    $spendPayment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'branch_id' => $branch->id,
        'amount' => 200_000,
        'direction' => 'receipt',
        'payment_source' => 'wallet',
        'is_deposit' => false,
        'method' => 'wallet',
        'paid_at' => now(),
        'received_by' => $manager->id,
    ]);

    $spendEntry = WalletLedgerEntry::query()
        ->where('payment_id', $spendPayment->id)
        ->where('entry_type', 'spend')
        ->firstOrFail();

    expect($spendEntry->direction)->toBe(WalletLedgerEntry::DIRECTION_DEBIT);

    // Refund (reversal) → Observer postPayment → opposite direction = CREDIT
    $reversalPayment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'branch_id' => $branch->id,
        'amount' => 200_000,
        'direction' => 'refund',
        'payment_source' => 'wallet',
        'is_deposit' => false,
        'method' => 'wallet',
        'paid_at' => now(),
        'received_by' => $manager->id,
        'reversal_of_id' => $spendPayment->id,
    ]);

    // postPayment is also called by observer; wallet balance is restored
    $reversalEntry = WalletLedgerEntry::query()
        ->where('payment_id', $reversalPayment->id)
        ->where('entry_type', 'reversal')
        ->firstOrFail();

    expect($reversalEntry->direction)->toBe(WalletLedgerEntry::DIRECTION_CREDIT)
        ->and((float) $reversalEntry->amount)->toBe(200_000.0);

    $wallet = PatientWallet::query()->where('patient_id', $patient->id)->firstOrFail();
    expect((float) $wallet->balance)->toBe(500_000.0);

});
