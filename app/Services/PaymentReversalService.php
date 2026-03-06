<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PaymentReversalService
{
    public function reverse(
        Payment $payment,
        float $amount,
        mixed $paidAt = null,
        ?string $refundReason = null,
        ?string $note = null,
        ?int $actorId = null,
    ): Payment {
        Gate::authorize('view', $payment);
        Gate::authorize('create', Payment::class);
        ActionGate::authorize(
            ActionPermission::PAYMENT_REVERSAL,
            'Bạn không có quyền thực hiện hoàn tiền hoặc đảo phiếu thu.',
        );

        return DB::transaction(function () use ($actorId, $amount, $note, $paidAt, $payment, $refundReason): Payment {
            $lockedPayment = Payment::query()
                ->with(['invoice'])
                ->lockForUpdate()
                ->findOrFail($payment->getKey());

            $invoice = $lockedPayment->invoice;
            if (! $invoice instanceof Invoice) {
                throw ValidationException::withMessages([
                    'payment' => 'Phiếu gốc không còn liên kết với hóa đơn hợp lệ.',
                ]);
            }

            Gate::authorize('update', $invoice);

            $lockedInvoice = Invoice::query()
                ->lockForUpdate()
                ->findOrFail($invoice->getKey());

            $existingReversal = Payment::query()
                ->where('reversal_of_id', $lockedPayment->getKey())
                ->lockForUpdate()
                ->first();

            if ($existingReversal instanceof Payment) {
                return $existingReversal;
            }

            $this->assertCanReverse($lockedPayment, $amount);

            $resolvedActorId = $actorId ?? (is_numeric(auth()->id()) ? (int) auth()->id() : null);
            $resolvedTransactionRef = Payment::reversalTransactionRef(
                invoiceId: (int) $lockedInvoice->getKey(),
                originalPaymentId: (int) $lockedPayment->getKey(),
            );

            $updatedRows = Payment::query()
                ->whereKey($lockedPayment->getKey())
                ->whereNull('reversed_at')
                ->update([
                    'reversed_at' => now(),
                    'reversed_by' => $resolvedActorId,
                    'updated_at' => now(),
                ]);

            if ($updatedRows === 0) {
                $existingReversal = Payment::query()
                    ->where('reversal_of_id', $lockedPayment->getKey())
                    ->first();

                if ($existingReversal instanceof Payment) {
                    return $existingReversal;
                }

                throw ValidationException::withMessages([
                    'payment' => 'Phiếu gốc đã được xử lý đảo phiếu trước đó.',
                ]);
            }

            try {
                return $lockedInvoice->recordPayment(
                    amount: $amount,
                    method: (string) $lockedPayment->method,
                    notes: $note,
                    paidAt: $paidAt ?? now(),
                    direction: 'refund',
                    refundReason: $refundReason,
                    transactionRef: $resolvedTransactionRef,
                    paymentSource: (string) $lockedPayment->payment_source,
                    insuranceClaimNumber: $lockedPayment->insurance_claim_number,
                    receivedBy: $resolvedActorId,
                    reversalOfId: (int) $lockedPayment->getKey(),
                );
            } catch (QueryException $exception) {
                $isDuplicateTransaction = str_contains((string) $exception->getCode(), '23000');

                if (! $isDuplicateTransaction) {
                    throw $exception;
                }

                return Payment::query()
                    ->where('invoice_id', $lockedInvoice->getKey())
                    ->where('transaction_ref', $resolvedTransactionRef)
                    ->firstOrFail();
            }
        }, 5);
    }

    protected function assertCanReverse(Payment $payment, float $amount): void
    {
        if (! $payment->canReverse()) {
            throw ValidationException::withMessages([
                'payment' => 'Phiếu gốc không còn hợp lệ để đảo phiếu.',
            ]);
        }

        $normalizedAmount = round(abs($amount), 2);
        $maximumAmount = round(abs((float) $payment->amount), 2);

        if ($normalizedAmount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Số tiền hoàn phải lớn hơn 0.',
            ]);
        }

        if ($normalizedAmount > $maximumAmount) {
            throw ValidationException::withMessages([
                'amount' => 'Số tiền hoàn không được vượt quá phiếu gốc.',
            ]);
        }
    }
}
