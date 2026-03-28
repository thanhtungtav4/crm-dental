<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ReceiptExpense;
use App\Support\WorkflowAuditMetadata;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReceiptExpenseWorkflowService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareCreatePayload(array $data): array
    {
        $data['status'] = ReceiptExpense::STATUS_DRAFT;
        $data['posted_at'] = null;
        $data['posted_by'] = null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareEditablePayload(ReceiptExpense $receiptExpense, array $data): array
    {
        $incomingStatus = (string) ($data['status'] ?? $receiptExpense->status);
        $currentStatus = (string) $receiptExpense->status;

        if ($incomingStatus !== $currentStatus) {
            throw ValidationException::withMessages([
                'status' => 'Trang thai phieu thu chi chi duoc thay doi qua ReceiptExpenseWorkflowService.',
            ]);
        }

        $data['status'] = $receiptExpense->status;
        $data['posted_at'] = $receiptExpense->posted_at;
        $data['posted_by'] = $receiptExpense->posted_by;

        return $data;
    }

    public function approve(ReceiptExpense $receiptExpense, ?string $reason = null, ?int $actorId = null): ReceiptExpense
    {
        Gate::authorize('update', $receiptExpense);

        return $this->transition(
            receiptExpense: $receiptExpense,
            toStatus: ReceiptExpense::STATUS_APPROVED,
            auditAction: AuditLog::ACTION_APPROVE,
            trigger: 'manual_approve',
            reason: $reason,
            actorId: $actorId,
        );
    }

    public function post(ReceiptExpense $receiptExpense, ?string $reason = null, ?int $actorId = null): ReceiptExpense
    {
        Gate::authorize('update', $receiptExpense);

        $resolvedActorId = $this->resolveActorId($actorId);

        return $this->transition(
            receiptExpense: $receiptExpense,
            toStatus: ReceiptExpense::STATUS_POSTED,
            auditAction: AuditLog::ACTION_COMPLETE,
            trigger: 'manual_post',
            reason: $reason,
            actorId: $resolvedActorId,
            attributes: [
                'posted_at' => now(),
                'posted_by' => $resolvedActorId,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function transition(
        ReceiptExpense $receiptExpense,
        string $toStatus,
        string $auditAction,
        string $trigger,
        ?string $reason,
        ?int $actorId,
        array $attributes = [],
    ): ReceiptExpense {
        return DB::transaction(function () use ($receiptExpense, $toStatus, $auditAction, $trigger, $reason, $actorId, $attributes): ReceiptExpense {
            $lockedReceiptExpense = $this->lockReceiptExpense($receiptExpense);
            $resolvedActorId = $this->resolveActorId($actorId);
            $fromStatus = (string) $lockedReceiptExpense->status;

            if ($fromStatus === $toStatus) {
                return $lockedReceiptExpense;
            }

            $this->assertTransition($fromStatus, $toStatus);

            ReceiptExpense::runWithinManagedWorkflow(function () use ($lockedReceiptExpense, $attributes, $toStatus): void {
                $lockedReceiptExpense->forceFill(array_merge($attributes, [
                    'status' => $toStatus,
                ]))->save();
            });

            $lockedReceiptExpense->refresh();

            AuditLog::record(
                entityType: AuditLog::ENTITY_RECEIPT_EXPENSE,
                entityId: (int) $lockedReceiptExpense->getKey(),
                action: $auditAction,
                actorId: $resolvedActorId,
                metadata: WorkflowAuditMetadata::transition(
                    fromStatus: $fromStatus,
                    toStatus: $toStatus,
                    reason: $reason,
                    metadata: [
                        'trigger' => $trigger,
                        'receipt_expense_id' => (int) $lockedReceiptExpense->getKey(),
                        'voucher_code' => $lockedReceiptExpense->voucher_code,
                        'voucher_type' => $lockedReceiptExpense->voucher_type,
                        'invoice_id' => $lockedReceiptExpense->invoice_id,
                        'patient_id' => $lockedReceiptExpense->patient_id,
                        'branch_id' => $lockedReceiptExpense->resolveBranchId(),
                        'amount' => (float) $lockedReceiptExpense->amount,
                        'posted_at' => optional($lockedReceiptExpense->posted_at)?->toIso8601String(),
                        'posted_by' => $lockedReceiptExpense->posted_by,
                    ],
                ),
            );

            return $lockedReceiptExpense;
        }, 3);
    }

    protected function assertTransition(string $fromStatus, string $toStatus): void
    {
        $allowed = match ($fromStatus) {
            ReceiptExpense::STATUS_DRAFT => [ReceiptExpense::STATUS_APPROVED, ReceiptExpense::STATUS_POSTED],
            ReceiptExpense::STATUS_APPROVED => [ReceiptExpense::STATUS_POSTED],
            default => [],
        };

        if (in_array($toStatus, $allowed, true)) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => sprintf(
                'Khong the chuyen phieu thu chi tu "%s" sang "%s".',
                ReceiptExpense::statusLabel($fromStatus),
                ReceiptExpense::statusLabel($toStatus),
            ),
        ]);
    }

    protected function lockReceiptExpense(ReceiptExpense $receiptExpense): ReceiptExpense
    {
        return ReceiptExpense::query()
            ->lockForUpdate()
            ->findOrFail($receiptExpense->getKey());
    }

    protected function resolveActorId(?int $actorId): ?int
    {
        return $actorId ?? (is_numeric(auth()->id()) ? (int) auth()->id() : null);
    }
}
