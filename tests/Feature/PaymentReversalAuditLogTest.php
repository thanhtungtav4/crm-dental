<?php

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\User;

it('records an audit log when reversing a payment', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create([
        'total_amount' => 1_000_000,
        'paid_amount' => 0,
        'status' => 'issued',
    ]);

    $receipt = $invoice->recordPayment(400_000, 'cash', 'Thanh toán đợt 1', now(), 'receipt', null, null, 'patient', null, $user->id);
    $receipt->markReversed($user->id);

    $refund = $invoice->recordPayment(
        amount: 200_000,
        method: 'cash',
        notes: 'Hoàn tiền đợt 1',
        paidAt: now(),
        direction: 'refund',
        refundReason: 'Điều chỉnh dịch vụ',
        transactionRef: null,
        paymentSource: 'patient',
        insuranceClaimNumber: null,
        receivedBy: $user->id,
        reversalOfId: $receipt->id
    );

    $log = AuditLog::query()
        ->where('entity_type', 'payment')
        ->where('entity_id', $refund->id)
        ->where('action', 'reversal')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($user->id)
        ->and($log->metadata['reversal_of_id'] ?? null)->toBe($receipt->id)
        ->and($log->metadata['invoice_id'] ?? null)->toBe($invoice->id);
});
