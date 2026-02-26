<?php

use App\Models\InsuranceClaim;
use App\Models\Invoice;
use App\Models\Patient;
use Illuminate\Validation\ValidationException;

it('runs insurance claim lifecycle and records insurance payment when paid', function () {
    $patient = Patient::factory()->create();

    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 600000,
        'paid_amount' => 0,
    ]);

    $claim = InsuranceClaim::create([
        'invoice_id' => $invoice->id,
        'patient_id' => $patient->id,
        'amount_claimed' => 500000,
        'status' => InsuranceClaim::STATUS_DRAFT,
    ]);

    $claim->submit();
    $claim->approve(450000);
    $claim->markPaid();

    $payment = $invoice->payments()
        ->where('payment_source', 'insurance')
        ->where('insurance_claim_number', $claim->claim_number)
        ->first();

    expect($claim->fresh()->status)->toBe(InsuranceClaim::STATUS_PAID)
        ->and($payment)->not->toBeNull()
        ->and((float) $payment->amount)->toEqualWithDelta(450000.00, 0.01);
});

it('blocks invalid insurance claim transitions', function () {
    $patient = Patient::factory()->create();

    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 300000,
        'paid_amount' => 0,
    ]);

    $claim = InsuranceClaim::create([
        'invoice_id' => $invoice->id,
        'patient_id' => $patient->id,
        'amount_claimed' => 300000,
        'status' => InsuranceClaim::STATUS_DRAFT,
    ]);

    expect(fn () => $claim->update(['status' => InsuranceClaim::STATUS_PAID]))
        ->toThrow(ValidationException::class, 'INSURANCE_CLAIM_STATE_INVALID');
});
