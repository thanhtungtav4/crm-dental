<?php

use App\Models\AuditLog;
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

    $approveAudit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_INSURANCE_CLAIM)
        ->where('entity_id', $claim->id)
        ->where('action', AuditLog::ACTION_APPROVE)
        ->latest('id')
        ->first();

    $paidAudit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_INSURANCE_CLAIM)
        ->where('entity_id', $claim->id)
        ->where('action', AuditLog::ACTION_COMPLETE)
        ->latest('id')
        ->first();

    $payment = $invoice->payments()
        ->where('payment_source', 'insurance')
        ->where('insurance_claim_number', $claim->claim_number)
        ->first();

    expect($claim->fresh()->status)->toBe(InsuranceClaim::STATUS_PAID)
        ->and($payment)->not->toBeNull()
        ->and((float) $payment->amount)->toEqualWithDelta(450000.00, 0.01)
        ->and($approveAudit)->not->toBeNull()
        ->and($approveAudit->patient_id)->toBe($patient->id)
        ->and($approveAudit->branch_id)->toBe($invoice->branch_id)
        ->and(data_get($approveAudit, 'metadata.status_from'))->toBe(InsuranceClaim::STATUS_SUBMITTED)
        ->and(data_get($approveAudit, 'metadata.status_to'))->toBe(InsuranceClaim::STATUS_APPROVED)
        ->and(data_get($approveAudit, 'metadata.amount_approved'))->toBe('450000.00')
        ->and($paidAudit)->not->toBeNull()
        ->and($paidAudit->patient_id)->toBe($patient->id)
        ->and($paidAudit->branch_id)->toBe($invoice->branch_id)
        ->and(data_get($paidAudit, 'metadata.status_from'))->toBe(InsuranceClaim::STATUS_APPROVED)
        ->and(data_get($paidAudit, 'metadata.status_to'))->toBe(InsuranceClaim::STATUS_PAID);
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
