<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\InsuranceClaim;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use App\Services\InsuranceClaimWorkflowService;
use Illuminate\Validation\ValidationException;

it('records structured audit metadata when approving an insurance claim through the workflow service', function (): void {
    [$claim, $manager] = makeInsuranceClaimWorkflowFixture();

    $this->actingAs($manager);

    app(InsuranceClaimWorkflowService::class)->submit(
        claim: $claim,
        reason: 'payer_submitted',
        actorId: $manager->id,
    );

    $approvedClaim = app(InsuranceClaimWorkflowService::class)->approve(
        claim: $claim->fresh(),
        amountApproved: 280_000,
        reason: 'manager_approved',
        actorId: $manager->id,
    );

    $auditLog = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_INSURANCE_CLAIM)
        ->where('entity_id', $claim->id)
        ->where('action', AuditLog::ACTION_APPROVE)
        ->latest('id')
        ->first();

    expect($approvedClaim->status)->toBe(InsuranceClaim::STATUS_APPROVED)
        ->and((float) $approvedClaim->amount_approved)->toEqualWithDelta(280_000.00, 0.01)
        ->and($auditLog)->not->toBeNull()
        ->and($auditLog?->actor_id)->toBe($manager->id)
        ->and($auditLog?->branch_id)->toBe($approvedClaim->invoice?->branch_id)
        ->and($auditLog?->patient_id)->toBe($approvedClaim->patient_id)
        ->and($auditLog?->metadata)->toMatchArray([
            'status_from' => InsuranceClaim::STATUS_SUBMITTED,
            'status_to' => InsuranceClaim::STATUS_APPROVED,
            'reason' => 'manager_approved',
            'trigger' => 'manual_approve',
            'invoice_id' => $approvedClaim->invoice_id,
            'patient_id' => $approvedClaim->patient_id,
            'claim_number' => $approvedClaim->claim_number,
        ]);
});

it('records payment context when marking an insurance claim paid through the workflow service', function (): void {
    [$claim, $manager] = makeInsuranceClaimWorkflowFixture();

    $this->actingAs($manager);

    app(InsuranceClaimWorkflowService::class)->submit(
        claim: $claim,
        actorId: $manager->id,
    );

    app(InsuranceClaimWorkflowService::class)->approve(
        claim: $claim->fresh(),
        amountApproved: 260_000,
        actorId: $manager->id,
    );

    $payment = app(InsuranceClaimWorkflowService::class)->markPaid(
        claim: $claim->fresh(),
        amount: 260_000,
        method: 'transfer',
        note: 'Bao hiem da doi soat',
        reason: 'insurer_paid',
        actorId: $manager->id,
    );

    $auditLog = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_INSURANCE_CLAIM)
        ->where('entity_id', $claim->id)
        ->where('action', AuditLog::ACTION_COMPLETE)
        ->latest('id')
        ->first();

    expect($claim->fresh()->status)->toBe(InsuranceClaim::STATUS_PAID)
        ->and($payment->payment_source)->toBe('insurance')
        ->and($payment->insurance_claim_number)->toBe($claim->claim_number)
        ->and($payment->transaction_ref)->toBe('IC-PAYOUT-'.$claim->id)
        ->and($auditLog)->not->toBeNull()
        ->and($auditLog?->actor_id)->toBe($manager->id)
        ->and($auditLog?->metadata)->toMatchArray([
            'status_from' => InsuranceClaim::STATUS_APPROVED,
            'status_to' => InsuranceClaim::STATUS_PAID,
            'reason' => 'insurer_paid',
            'trigger' => 'manual_mark_paid',
            'payment_id' => $payment->id,
            'payment_method' => 'transfer',
            'transaction_ref' => 'IC-PAYOUT-'.$claim->id,
        ]);
});

it('blocks raw insurance claim status changes outside the workflow service', function (): void {
    [$claim, $manager] = makeInsuranceClaimWorkflowFixture();

    $this->actingAs($manager);

    expect(fn () => $claim->update([
        'status' => InsuranceClaim::STATUS_SUBMITTED,
    ]))->toThrow(ValidationException::class, 'InsuranceClaimWorkflowService');
});

/**
 * @return array{0: InsuranceClaim, 1: User}
 */
function makeInsuranceClaimWorkflowFixture(): array
{
    $branch = Branch::factory()->create();
    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $patient = Patient::factory()->create([
        'first_branch_id' => $branch->id,
    ]);

    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 500_000,
        'paid_amount' => 0,
    ]);

    $claim = InsuranceClaim::query()->create([
        'invoice_id' => $invoice->id,
        'patient_id' => $patient->id,
        'payer_name' => 'Bảo hiểm PVI',
        'amount_claimed' => 300_000,
        'status' => InsuranceClaim::STATUS_DRAFT,
    ]);

    return [$claim, $manager];
}
