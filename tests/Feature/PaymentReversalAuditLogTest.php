<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use App\Services\PaymentReversalService;

it('records an audit log when reversing a payment', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->assignRole('Manager');
    $this->actingAs($user);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'total_amount' => 1_000_000,
        'paid_amount' => 0,
        'status' => 'issued',
    ]);

    $receipt = $invoice->recordPayment(400_000, 'cash', 'Thanh toán đợt 1', now(), 'receipt', null, null, 'patient', null, $user->id);
    $refund = app(PaymentReversalService::class)->reverse(
        payment: $receipt,
        amount: 200_000,
        paidAt: now(),
        refundReason: 'Điều chỉnh dịch vụ',
        note: 'Hoàn tiền đợt 1',
        actorId: $user->id,
    );

    $log = AuditLog::query()
        ->where('entity_type', 'payment')
        ->where('entity_id', $refund->id)
        ->where('action', 'reversal')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($user->id)
        ->and($log->patient_id)->toBe($invoice->patient_id)
        ->and($log->branch_id)->toBe($invoice->resolveBranchId())
        ->and($log->metadata['reversal_of_id'] ?? null)->toBe($receipt->id)
        ->and($log->metadata['invoice_id'] ?? null)->toBe($invoice->id);
});
