<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\BranchAccess;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;

class PaymentRecordingService
{
    /**
     * @param  array{
     *     amount:mixed,
     *     method?:mixed,
     *     note?:mixed,
     *     paid_at?:mixed,
     *     direction?:mixed,
     *     refund_reason?:mixed,
     *     transaction_ref?:mixed,
     *     payment_source?:mixed,
     *     insurance_claim_number?:mixed,
     *     received_by?:mixed,
     *     is_deposit?:mixed
     * }  $data
     */
    public function record(Invoice $invoice, array $data, ?User $actor = null, ?string $fallbackNote = null): Payment
    {
        $resolvedActor = $actor ?? BranchAccess::currentUser();
        $resolvedBranchId = $invoice->resolveBranchId();

        if ($resolvedActor instanceof User) {
            Gate::forUser($resolvedActor)->authorize('view', $invoice);
            Gate::forUser($resolvedActor)->authorize('create', Payment::class);
            $this->assertActorCanAccessBranch($resolvedActor, $resolvedBranchId);
        } else {
            Gate::authorize('view', $invoice);
            Gate::authorize('create', Payment::class);
            BranchAccess::assertCanAccessBranch(
                branchId: $resolvedBranchId,
                field: 'invoice_id',
                message: 'Bạn không thể ghi nhận thanh toán cho hóa đơn thuộc chi nhánh ngoài phạm vi được phân quyền.',
            );
        }

        $transactionRef = filled($data['transaction_ref'] ?? null)
            ? trim((string) $data['transaction_ref'])
            : null;

        if ($transactionRef !== null) {
            $existingPayment = Payment::query()
                ->where('invoice_id', $invoice->getKey())
                ->where('transaction_ref', $transactionRef)
                ->first();

            if ($existingPayment instanceof Payment) {
                return $existingPayment;
            }
        }

        try {
            return $invoice->recordPayment(
                amount: (float) ($data['amount'] ?? 0),
                method: (string) ($data['method'] ?? 'cash'),
                notes: $this->resolveNote($data, $fallbackNote),
                paidAt: $data['paid_at'] ?? now(),
                direction: (string) ($data['direction'] ?? ClinicRuntimeSettings::defaultPaymentDirection()),
                refundReason: filled($data['refund_reason'] ?? null) ? (string) $data['refund_reason'] : null,
                transactionRef: $transactionRef,
                paymentSource: (string) ($data['payment_source'] ?? ClinicRuntimeSettings::defaultPaymentSource()),
                insuranceClaimNumber: filled($data['insurance_claim_number'] ?? null) ? (string) $data['insurance_claim_number'] : null,
                receivedBy: app(FinanceActorAuthorizer::class)->sanitizeReceivedBy(
                    actor: $resolvedActor,
                    receivedBy: isset($data['received_by']) && filled($data['received_by']) ? (int) $data['received_by'] : null,
                    branchId: $resolvedBranchId,
                ),
                reversalOfId: null,
                isDeposit: (bool) ($data['is_deposit'] ?? false),
            );
        } catch (QueryException $exception) {
            $isDuplicateTransaction = str_contains((string) $exception->getCode(), '23000');

            if (! $isDuplicateTransaction || $transactionRef === null) {
                throw $exception;
            }

            return Payment::query()
                ->where('invoice_id', $invoice->getKey())
                ->where('transaction_ref', $transactionRef)
                ->firstOrFail();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveNote(array $data, ?string $fallbackNote = null): ?string
    {
        if (filled($data['note'] ?? null)) {
            return (string) $data['note'];
        }

        return filled($fallbackNote) ? $fallbackNote : null;
    }

    protected function assertActorCanAccessBranch(User $actor, ?int $branchId): void
    {
        if ($actor->hasRole('Admin')) {
            return;
        }

        if ($branchId !== null && $actor->canAccessBranch($branchId)) {
            return;
        }

        throw \Illuminate\Validation\ValidationException::withMessages([
            'invoice_id' => 'Bạn không thể ghi nhận thanh toán cho hóa đơn thuộc chi nhánh ngoài phạm vi được phân quyền.',
        ]);
    }
}
