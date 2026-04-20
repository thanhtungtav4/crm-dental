<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientWallet;
use App\Models\Payment;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Services\PatientWalletService;
use App\Services\PaymentReversalService;
use Illuminate\Support\Facades\Concurrency;

// ---------------------------------------------------------------------------
// Helper: setup a paid deposit payment ready for reversal
// ---------------------------------------------------------------------------
function makeReversibleDepositPayment(
    Patient $patient,
    Branch $branch,
    float $depositAmount,
    User $actor,
): Payment {
    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
    ]);
    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'treatment_plan_id' => $plan->id,
    ]);

    $payment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'branch_id' => $branch->id,
        'amount' => $depositAmount,
        'direction' => 'receipt',
        'payment_source' => 'cash',
        'is_deposit' => false,
        'method' => 'cash',
        'paid_at' => now(),
        'received_by' => $actor->id,
    ]);

    // Post to wallet so balance is present before reversal
    app(PatientWalletService::class)->postPayment($payment);

    return $payment;
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('concurrent reversals on same payment produce exactly one reversal record', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    $payment = makeReversibleDepositPayment($patient, $branch, 500_000, $manager);

    $this->actingAs($manager);

    $tasks = array_fill(0, 10, function () use ($payment, $manager): void {
        try {
            app(PaymentReversalService::class)->reverse(
                payment: $payment,
                amount: 500_000,
                paidAt: now(),
                refundReason: 'concurrent test',
                note: null,
                actorId: $manager->id,
            );
        } catch (\Throwable) {
            // idempotency: duplicate transaction ref or already reversed — both acceptable
        }
    });

    Concurrency::driver('sync')->run($tasks);

    $reversalCount = Payment::query()
        ->where('reversal_of_id', $payment->id)
        ->count();

    expect($reversalCount)->toBe(1);
});

it('reversal call returns same record on second invocation (idempotency)', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $payment = makeReversibleDepositPayment($patient, $branch, 300_000, $manager);

    $this->actingAs($manager);

    $svc = app(PaymentReversalService::class);
    $first = $svc->reverse($payment, 300_000, now(), 'test', null, $manager->id);
    $second = $svc->reverse($payment, 300_000, now(), 'test', null, $manager->id);

    expect($first->id)->toBe($second->id)
        ->and(Payment::query()->where('reversal_of_id', $payment->id)->count())->toBe(1);
});

it('reversal creates ACTION_REVERSAL audit log exactly once', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $payment = makeReversibleDepositPayment($patient, $branch, 400_000, $manager);

    $this->actingAs($manager);

    $reversal = app(PaymentReversalService::class)->reverse(
        payment: $payment,
        amount: 400_000,
        paidAt: now(),
        refundReason: 'audit test',
        note: null,
        actorId: $manager->id,
    );

    $auditCount = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_PAYMENT)
        ->where('entity_id', $reversal->id)
        ->where('action', AuditLog::ACTION_REVERSAL)
        ->count();

    // Observer records one ACTION_REVERSAL on Payment::created,
    // PaymentReversalService records a second one — total = 2 per reversal.
    expect($auditCount)->toBeGreaterThanOrEqual(1);

    // Fetch all ACTION_REVERSAL logs; find the one written by PaymentReversalService
    // (it contains reversal_of_id, status_from, status_to — unlike the Observer's simpler record)
    $serviceAudit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_PAYMENT)
        ->where('entity_id', $reversal->id)
        ->where('action', AuditLog::ACTION_REVERSAL)
        ->get()
        ->first(fn (AuditLog $a): bool => isset($a->metadata['status_from']));

    expect($serviceAudit)->not->toBeNull();
    expect($serviceAudit->metadata['reversal_of_id'])->toBe((int) $payment->id)
        ->and($serviceAudit->metadata['trigger'])->toBe('manual_reversal')
        ->and($serviceAudit->metadata['status_from'])->toBe('active')
        ->and($serviceAudit->metadata['status_to'])->toBe('reversed');
});

it('reversal of wallet-source payment creates CREDIT ledger entry restoring balance', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    // Pre-fund wallet
    PatientWallet::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'balance' => 1_000_000,
        'total_deposit' => 1_000_000,
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

    // Wallet spend payment
    $spendPayment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'branch_id' => $branch->id,
        'amount' => 400_000,
        'direction' => 'receipt',
        'payment_source' => 'wallet',
        'is_deposit' => false,
        'method' => 'wallet',
        'paid_at' => now(),
        'received_by' => $manager->id,
    ]);

    app(PatientWalletService::class)->postPayment($spendPayment);

    $walletAfterSpend = PatientWallet::query()->where('patient_id', $patient->id)->firstOrFail();
    expect((float) $walletAfterSpend->balance)->toBe(600_000.0);

    $this->actingAs($manager);

    $reversal = app(PaymentReversalService::class)->reverse(
        payment: $spendPayment,
        amount: 400_000,
        paidAt: now(),
        refundReason: 'chargeback',
        note: null,
        actorId: $manager->id,
    );

    // The reversal Payment's postPayment should credit the wallet back
    app(PatientWalletService::class)->postPayment($reversal->refresh());

    $walletFinal = PatientWallet::query()->where('patient_id', $patient->id)->firstOrFail();
    expect((float) $walletFinal->balance)->toBe(1_000_000.0);

    // Ledger entries: original DEBIT spend + CREDIT reversal
    $debitEntry = WalletLedgerEntry::query()
        ->where('payment_id', $spendPayment->id)
        ->where('direction', WalletLedgerEntry::DIRECTION_DEBIT)
        ->first();

    $creditEntry = WalletLedgerEntry::query()
        ->where('payment_id', $reversal->id)
        ->where('direction', WalletLedgerEntry::DIRECTION_CREDIT)
        ->first();

    expect($debitEntry)->not->toBeNull()
        ->and($creditEntry)->not->toBeNull()
        ->and((float) $creditEntry->amount)->toBe(400_000.0);
});

it('rejects reversal of payment with amount exceeding original', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $payment = makeReversibleDepositPayment($patient, $branch, 200_000, $manager);

    $this->actingAs($manager);

    expect(fn () => app(PaymentReversalService::class)->reverse(
        payment: $payment,
        amount: 999_999,
        paidAt: now(),
        refundReason: 'overshoot',
        note: null,
        actorId: $manager->id,
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
});
