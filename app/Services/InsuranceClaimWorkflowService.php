<?php

namespace App\Services;

use App\Models\InsuranceClaim;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\BranchAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InsuranceClaimWorkflowService
{
    public function submit(InsuranceClaim $claim, ?string $reason = null, ?int $actorId = null): InsuranceClaim
    {
        $this->authorizeDecision();

        return $this->transition(
            claim: $claim,
            toStatus: InsuranceClaim::STATUS_SUBMITTED,
            trigger: 'manual_submit',
            reason: $reason,
            actorId: $actorId,
        );
    }

    public function approve(
        InsuranceClaim $claim,
        ?float $amountApproved = null,
        ?string $reason = null,
        ?int $actorId = null,
    ): InsuranceClaim {
        $this->authorizeDecision();

        $resolvedAmountApproved = $this->resolveApprovedAmount($claim, $amountApproved);

        return $this->transition(
            claim: $claim,
            toStatus: InsuranceClaim::STATUS_APPROVED,
            trigger: 'manual_approve',
            reason: $reason,
            actorId: $actorId,
            attributes: [
                'amount_approved' => $resolvedAmountApproved,
            ],
        );
    }

    public function deny(
        InsuranceClaim $claim,
        string $reasonCode,
        ?string $note = null,
        ?string $reason = null,
        ?int $actorId = null,
    ): InsuranceClaim {
        $this->authorizeDecision();

        $resolvedReasonCode = trim($reasonCode);

        if ($resolvedReasonCode === '') {
            throw ValidationException::withMessages([
                'denial_reason_code' => 'Vui lòng nhập mã lý do từ chối bảo hiểm.',
            ]);
        }

        return $this->transition(
            claim: $claim,
            toStatus: InsuranceClaim::STATUS_DENIED,
            trigger: 'manual_deny',
            reason: $reason,
            actorId: $actorId,
            attributes: [
                'amount_approved' => null,
                'denial_reason_code' => $resolvedReasonCode,
                'denial_note' => filled($note) ? trim((string) $note) : null,
            ],
        );
    }

    public function resubmit(InsuranceClaim $claim, ?string $reason = null, ?int $actorId = null): InsuranceClaim
    {
        $this->authorizeDecision();

        return $this->transition(
            claim: $claim,
            toStatus: InsuranceClaim::STATUS_RESUBMITTED,
            trigger: 'manual_resubmit',
            reason: $reason,
            actorId: $actorId,
            attributes: [
                'amount_approved' => null,
                'denial_reason_code' => null,
                'denial_note' => null,
            ],
        );
    }

    public function cancel(InsuranceClaim $claim, ?string $reason = null, ?int $actorId = null): InsuranceClaim
    {
        $this->authorizeDecision();

        return $this->transition(
            claim: $claim,
            toStatus: InsuranceClaim::STATUS_CANCELLED,
            trigger: 'manual_cancel',
            reason: $reason,
            actorId: $actorId,
        );
    }

    public function markPaid(
        InsuranceClaim $claim,
        ?float $amount = null,
        string $method = 'transfer',
        ?string $note = null,
        ?string $reason = null,
        ?int $actorId = null,
    ): Payment {
        $this->authorizeDecision();

        return DB::transaction(function () use ($claim, $amount, $method, $note, $reason, $actorId): Payment {
            $lockedClaim = $this->lockClaim($claim);
            $resolvedActorId = $this->resolveActorId($actorId);
            $fromStatus = (string) $lockedClaim->status;

            if ($fromStatus === InsuranceClaim::STATUS_PAID) {
                $existingPayment = $this->findExistingInsurancePayment($lockedClaim);

                if ($existingPayment instanceof Payment) {
                    return $existingPayment;
                }

                throw ValidationException::withMessages([
                    'status' => 'Hồ sơ bảo hiểm đã ghi nhận thanh toán trước đó.',
                ]);
            }

            $this->assertTransition($lockedClaim, InsuranceClaim::STATUS_PAID);

            $invoice = $this->resolveInvoice($lockedClaim);
            $resolvedAmount = $this->resolvePayoutAmount($lockedClaim, $amount);

            $payment = $invoice->recordPayment(
                amount: $resolvedAmount,
                method: trim($method) !== '' ? trim($method) : 'transfer',
                notes: filled($note) ? trim((string) $note) : 'Ghi nhận thanh toán bảo hiểm',
                paidAt: now(),
                direction: 'receipt',
                refundReason: null,
                transactionRef: $this->insurancePaymentTransactionRef($lockedClaim),
                paymentSource: 'insurance',
                insuranceClaimNumber: $lockedClaim->claim_number,
                receivedBy: $resolvedActorId,
            );

            InsuranceClaim::runWithinManagedWorkflow(function () use ($lockedClaim): void {
                $lockedClaim->forceFill([
                    'status' => InsuranceClaim::STATUS_PAID,
                ])->save();
            }, [
                'actor_id' => $resolvedActorId,
                'reason' => $reason,
                'trigger' => 'manual_mark_paid',
                'payment_id' => (int) $payment->getKey(),
                'payment_amount' => (float) $payment->amount,
                'payment_method' => $payment->method,
                'transaction_ref' => $payment->transaction_ref,
            ]);

            return Payment::query()->findOrFail($payment->getKey());
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function transition(
        InsuranceClaim $claim,
        string $toStatus,
        string $trigger,
        ?string $reason,
        ?int $actorId,
        array $attributes = [],
    ): InsuranceClaim {
        return DB::transaction(function () use ($claim, $toStatus, $trigger, $reason, $actorId, $attributes): InsuranceClaim {
            $lockedClaim = $this->lockClaim($claim);
            $resolvedActorId = $this->resolveActorId($actorId);
            $fromStatus = (string) $lockedClaim->status;

            if ($fromStatus === $toStatus) {
                return $lockedClaim;
            }

            $this->assertTransition($lockedClaim, $toStatus);

            InsuranceClaim::runWithinManagedWorkflow(function () use ($lockedClaim, $toStatus, $attributes): void {
                $lockedClaim->forceFill(array_merge($attributes, [
                    'status' => $toStatus,
                ]))->save();
            }, array_filter([
                'actor_id' => $resolvedActorId,
                'reason' => $reason,
                'trigger' => $trigger,
            ], static fn (mixed $value): bool => $value !== null));

            return $lockedClaim->fresh();
        }, 3);
    }

    protected function authorizeDecision(): void
    {
        ActionGate::authorize(
            ActionPermission::INSURANCE_CLAIM_DECISION,
            'Bạn không có quyền phê duyệt/từ chối hồ sơ bảo hiểm.',
        );
    }

    protected function lockClaim(InsuranceClaim $claim): InsuranceClaim
    {
        return InsuranceClaim::query()
            ->lockForUpdate()
            ->findOrFail($claim->getKey());
    }

    protected function resolveActorId(?int $actorId): ?int
    {
        return $actorId ?? (is_numeric(auth()->id()) ? (int) auth()->id() : null);
    }

    protected function assertTransition(InsuranceClaim $claim, string $toStatus): void
    {
        $fromStatus = (string) $claim->status;

        if (InsuranceClaim::canTransition($fromStatus, $toStatus)) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => 'INSURANCE_CLAIM_STATE_INVALID: Không thể chuyển trạng thái hồ sơ bảo hiểm.',
        ]);
    }

    protected function resolveInvoice(InsuranceClaim $claim): Invoice
    {
        $invoice = Invoice::query()
            ->lockForUpdate()
            ->find($claim->invoice_id);

        if (! $invoice instanceof Invoice) {
            throw ValidationException::withMessages([
                'invoice_id' => 'Hồ sơ bảo hiểm không còn liên kết với hóa đơn hợp lệ.',
            ]);
        }

        $actor = BranchAccess::currentUser();
        BranchAccess::assertUserCanAccessBranch(
            user: $actor,
            branchId: $invoice->resolveBranchId(),
            field: 'invoice_id',
            message: 'Bạn không có quyền thao tác hồ sơ bảo hiểm ở chi nhánh này.',
        );

        return $invoice;
    }

    protected function resolveApprovedAmount(InsuranceClaim $claim, ?float $amountApproved): float
    {
        $resolvedAmount = round((float) ($amountApproved ?? $claim->amount_claimed), 2);
        $claimedAmount = round((float) $claim->amount_claimed, 2);

        if ($resolvedAmount <= 0) {
            throw ValidationException::withMessages([
                'amount_approved' => 'Số tiền duyệt bảo hiểm phải lớn hơn 0.',
            ]);
        }

        if ($resolvedAmount > $claimedAmount) {
            throw ValidationException::withMessages([
                'amount_approved' => 'Số tiền duyệt không được vượt quá số tiền yêu cầu.',
            ]);
        }

        return $resolvedAmount;
    }

    protected function resolvePayoutAmount(InsuranceClaim $claim, ?float $amount): float
    {
        $resolvedAmount = round((float) ($amount ?? $claim->amount_approved ?? $claim->amount_claimed), 2);

        if ($resolvedAmount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Số tiền thanh toán bảo hiểm phải lớn hơn 0.',
            ]);
        }

        return $resolvedAmount;
    }

    protected function insurancePaymentTransactionRef(InsuranceClaim $claim): string
    {
        return sprintf('IC-PAYOUT-%d', (int) $claim->getKey());
    }

    protected function findExistingInsurancePayment(InsuranceClaim $claim): ?Payment
    {
        return Payment::query()
            ->where('invoice_id', $claim->invoice_id)
            ->where('payment_source', 'insurance')
            ->where('insurance_claim_number', $claim->claim_number)
            ->latest('id')
            ->first();
    }
}
