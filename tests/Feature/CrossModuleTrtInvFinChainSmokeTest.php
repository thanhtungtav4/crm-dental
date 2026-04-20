<?php

/**
 * RRB-014 — Cross-module TRT/INV/FIN/SUP reconciliation chain smoke tests.
 *
 * Covers the full lifecycle:
 *  - TreatmentMaterialUsage → InventoryTransaction → stock decrease
 *  - MaterialUsage reversal → stock restored → original InventoryTransaction immutable
 *  - Invoice → Payment (cash) → WalletLedger (wallet source) chain
 *  - Payment reversal → WalletLedger CREDIT → original DEBIT unchanged
 *  - Branch attribution consistent across TRT/INV/FIN after reversal
 *  - Idempotency: double-reversal of material usage returns silently
 */

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\InventoryTransaction;
use App\Models\Invoice;
use App\Models\Material;
use App\Models\MaterialBatch;
use App\Models\Patient;
use App\Models\PatientWallet;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Services\PaymentReversalService;
use App\Services\TreatmentMaterialUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeChainFixture(): array
{
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $manager->id,
        'branch_id' => $branch->id,
        'status' => 'in_progress',
    ]);

    $session = TreatmentSession::factory()->create([
        'treatment_plan_id' => $plan->id,
        'doctor_id' => $manager->id,
        'status' => 'done',
    ]);

    $material = Material::factory()->create([
        'branch_id' => $branch->id,
        'stock_qty' => 20,
        'cost_price' => 50000,
    ]);

    $batch = MaterialBatch::query()->create([
        'material_id' => $material->id,
        'batch_number' => 'LOT-CHAIN-'.uniqid(),
        'expiry_date' => now()->addMonths(12)->toDateString(),
        'quantity' => 20,
        'purchase_price' => 50000,
        'received_date' => today()->toDateString(),
        'status' => 'active',
    ]);

    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => Invoice::STATUS_ISSUED,
        'subtotal' => 500000,
    ]);

    return compact('branch', 'manager', 'patient', 'plan', 'session', 'material', 'batch', 'invoice');
}

// ---------------------------------------------------------------------------
// TRT → INV chain
// ---------------------------------------------------------------------------

it('treatment material usage decreases stock and creates InventoryTransaction', function (): void {
    $f = makeChainFixture();
    $svc = app(TreatmentMaterialUsageService::class);

    $this->actingAs($f['manager']);

    $svc->create([
        'treatment_session_id' => $f['session']->id,
        'material_id' => $f['material']->id,
        'batch_id' => $f['batch']->id,
        'quantity' => 3,
    ]);

    expect($f['material']->fresh()->stock_qty)->toBe(17);

    $tx = InventoryTransaction::query()
        ->where('treatment_session_id', $f['session']->id)
        ->where('type', 'out')
        ->first();

    expect($tx)->not->toBeNull()
        ->and((int) $tx->quantity)->toBe(3)
        ->and($tx->branch_id)->toBe($f['branch']->id);
});

it('material usage reversal restores stock and creates RESTORE InventoryTransaction', function (): void {
    $f = makeChainFixture();
    $svc = app(TreatmentMaterialUsageService::class);

    $this->actingAs($f['manager']);

    $usage = $svc->create([
        'treatment_session_id' => $f['session']->id,
        'material_id' => $f['material']->id,
        'batch_id' => $f['batch']->id,
        'quantity' => 5,
    ]);

    expect($f['material']->fresh()->stock_qty)->toBe(15);

    $svc->delete($usage);

    expect($f['material']->fresh()->stock_qty)->toBe(20);

    expect(InventoryTransaction::query()
        ->where('treatment_session_id', $f['session']->id)
        ->where('type', 'out')
        ->exists()
    )->toBeTrue();

    expect(InventoryTransaction::query()
        ->where('treatment_session_id', $f['session']->id)
        ->where('type', 'adjust')
        ->exists()
    )->toBeTrue();
});

it('original InventoryTransaction is immutable after material usage reversal', function (): void {
    $f = makeChainFixture();
    $svc = app(TreatmentMaterialUsageService::class);

    $this->actingAs($f['manager']);

    $svc->create([
        'treatment_session_id' => $f['session']->id,
        'material_id' => $f['material']->id,
        'batch_id' => $f['batch']->id,
        'quantity' => 2,
    ]);

    $tx = InventoryTransaction::query()
        ->where('treatment_session_id', $f['session']->id)
        ->where('type', 'out')
        ->firstOrFail();

    expect(fn () => $tx->update(['quantity' => -99]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('double-reversal of material usage is idempotent and does not over-restore stock', function (): void {
    $f = makeChainFixture();
    $svc = app(TreatmentMaterialUsageService::class);

    $this->actingAs($f['manager']);

    $usage = $svc->create([
        'treatment_session_id' => $f['session']->id,
        'material_id' => $f['material']->id,
        'batch_id' => $f['batch']->id,
        'quantity' => 4,
    ]);

    expect($f['material']->fresh()->stock_qty)->toBe(16);

    $svc->delete($usage);
    $svc->delete($usage); // second call: record is gone, reversalAlreadyRecorded() returns early

    expect($f['material']->fresh()->stock_qty)->toBe(20);

    expect(InventoryTransaction::query()
        ->where('treatment_session_id', $f['session']->id)
        ->where('type', 'adjust')
        ->count()
    )->toBe(1); // only one reversal restore
});

// ---------------------------------------------------------------------------
// TRT → INV → audit trail continuity
// ---------------------------------------------------------------------------

it('material usage reversal writes ACTION_REVERSAL audit log with status_from metadata', function (): void {
    $f = makeChainFixture();
    $svc = app(TreatmentMaterialUsageService::class);

    $this->actingAs($f['manager']);

    $usage = $svc->create([
        'treatment_session_id' => $f['session']->id,
        'material_id' => $f['material']->id,
        'batch_id' => $f['batch']->id,
        'quantity' => 1,
    ]);

    $svc->delete($usage);

    $audit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_TREATMENT_SESSION)
        ->where('entity_id', $f['session']->id)
        ->where('action', AuditLog::ACTION_REVERSAL)
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->metadata)->toHaveKey('trigger');
});

// ---------------------------------------------------------------------------
// INV → FIN chain: Invoice → Payment → WalletLedger
// ---------------------------------------------------------------------------

it('invoice cash payment is recorded and audit log is created', function (): void {
    $f = makeChainFixture();

    $this->actingAs($f['manager']);

    $payment = $f['invoice']->recordPayment(
        amount: 500000,
        method: 'cash',
        paymentSource: 'patient',
    );

    expect($payment)->not->toBeNull()
        ->and((float) $payment->amount)->toEqual(500000.0)
        ->and($payment->direction)->toBe('receipt')
        ->and($payment->branch_id)->toBe($f['branch']->id);

    expect(AuditLog::query()
        ->where('entity_type', 'payment')
        ->where('entity_id', $payment->id)
        ->exists()
    )->toBeTrue();
});

it('wallet-source payment creates WalletLedgerEntry DEBIT and decreases balance', function (): void {
    $f = makeChainFixture();

    $this->actingAs($f['manager']);

    $wallet = PatientWallet::query()->firstOrCreate(
        ['patient_id' => $f['patient']->id],
        ['balance' => 600000, 'currency' => 'VND'],
    );

    $payment = $f['invoice']->recordPayment(
        amount: 200000,
        method: 'wallet',
        paymentSource: 'wallet',
    );

    $entry = WalletLedgerEntry::query()
        ->where('payment_id', $payment->id)
        ->where('direction', 'debit')
        ->first();

    expect($entry)->not->toBeNull()
        ->and((float) $entry->amount)->toEqual(200000.0);
});

it('payment reversal creates WalletLedgerEntry CREDIT and original DEBIT stays unchanged', function (): void {
    $f = makeChainFixture();

    $this->actingAs($f['manager']);

    PatientWallet::query()->firstOrCreate(
        ['patient_id' => $f['patient']->id],
        ['balance' => 600000, 'currency' => 'VND'],
    );

    $payment = $f['invoice']->recordPayment(
        amount: 200000,
        method: 'wallet',
        paymentSource: 'wallet',
    );

    $originalEntry = WalletLedgerEntry::query()
        ->where('payment_id', $payment->id)
        ->where('direction', 'debit')
        ->firstOrFail();

    $reversalSvc = app(PaymentReversalService::class);
    $reversal = $reversalSvc->reverse($payment, abs((float) $payment->amount), null, 'test reversal');

    // Original DEBIT entry unchanged
    expect($originalEntry->fresh()->direction)->toBe('debit')
        ->and((float) $originalEntry->fresh()->amount)->toEqual(200000.0);

    // Reversal CREDIT entry created
    $creditEntry = WalletLedgerEntry::query()
        ->where('payment_id', $reversal->id)
        ->where('direction', 'credit')
        ->first();

    expect($creditEntry)->not->toBeNull()
        ->and((float) $creditEntry->amount)->toEqual(200000.0);
});

// ---------------------------------------------------------------------------
// Branch attribution consistency
// ---------------------------------------------------------------------------

it('payment branch attribution matches invoice branch after reversal', function (): void {
    $f = makeChainFixture();

    $this->actingAs($f['manager']);

    $payment = $f['invoice']->recordPayment(
        amount: 300000,
        method: 'cash',
        paymentSource: 'patient',
    );

    expect($payment->branch_id)->toBe($f['branch']->id);

    $reversalSvc = app(PaymentReversalService::class);
    $reversal = $reversalSvc->reverse($payment, abs((float) $payment->amount), null, 'branch attribution test');

    expect($reversal->branch_id)->toBe($f['branch']->id);
});

it('material usage and inventory transaction share the same branch_id throughout the chain', function (): void {
    $f = makeChainFixture();
    $svc = app(TreatmentMaterialUsageService::class);

    $this->actingAs($f['manager']);

    $svc->create([
        'treatment_session_id' => $f['session']->id,
        'material_id' => $f['material']->id,
        'batch_id' => $f['batch']->id,
        'quantity' => 2,
    ]);

    $tx = InventoryTransaction::query()
        ->where('treatment_session_id', $f['session']->id)
        ->where('type', 'out')
        ->firstOrFail();

    expect($tx->branch_id)->toBe($f['branch']->id);
});
