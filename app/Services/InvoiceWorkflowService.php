<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Support\WorkflowAuditMetadata;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InvoiceWorkflowService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareCreatePayload(array $data): array
    {
        $status = Invoice::normalizeStatusValue($data['status'] ?? Invoice::STATUS_DRAFT) ?? Invoice::STATUS_DRAFT;

        if ($status === Invoice::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'status' => 'Khong the tao hoa don o trang thai da huy.',
            ]);
        }

        if (in_array($status, [
            Invoice::STATUS_PARTIAL,
            Invoice::STATUS_PAID,
            Invoice::STATUS_OVERDUE,
        ], true)) {
            $status = Invoice::STATUS_ISSUED;
        }

        $data['status'] = $status;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareEditablePayload(Invoice $invoice, array $data): array
    {
        $incomingStatus = Invoice::normalizeStatusValue($data['status'] ?? $invoice->status)
            ?? Invoice::STATUS_DRAFT;
        $currentStatus = Invoice::normalizeStatusValue($invoice->status) ?? Invoice::STATUS_DRAFT;

        if ($incomingStatus !== $currentStatus) {
            throw ValidationException::withMessages([
                'status' => 'Trang thai hoa don chi duoc thay doi qua InvoiceWorkflowService.',
            ]);
        }

        $data['status'] = $invoice->status;

        return $data;
    }

    public function cancel(Invoice $invoice, ?string $reason = null, ?int $actorId = null): Invoice
    {
        Gate::authorize('update', $invoice);

        $resolvedReason = filled($reason) ? trim((string) $reason) : null;

        return DB::transaction(function () use ($actorId, $invoice, $resolvedReason): Invoice {
            $lockedInvoice = Invoice::query()
                ->withExists(['payments', 'installmentPlan'])
                ->lockForUpdate()
                ->findOrFail($invoice->getKey());

            if ($lockedInvoice->status === Invoice::STATUS_CANCELLED) {
                return $lockedInvoice;
            }

            $this->assertCanCancel($lockedInvoice);

            Invoice::runWithinManagedCancellation(function () use ($lockedInvoice): void {
                $lockedInvoice->forceFill([
                    'status' => Invoice::STATUS_CANCELLED,
                    'paid_at' => null,
                ])->save();
            }, [
                'reason' => $resolvedReason,
                'requested_by' => $actorId,
            ]);

            return $lockedInvoice->fresh();
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function syncFinancialStatus(
        Invoice $invoice,
        ?int $actorId = null,
        string $auditAction = AuditLog::ACTION_UPDATE,
        ?string $reason = null,
        array $metadata = [],
        bool $persistQuietly = false,
    ): Invoice {
        return DB::transaction(function () use (
            $invoice,
            $actorId,
            $auditAction,
            $reason,
            $metadata,
            $persistQuietly,
        ): Invoice {
            $lockedInvoice = Invoice::query()
                ->lockForUpdate()
                ->findOrFail($invoice->getKey());

            $resolvedActorId = $this->resolveActorId($actorId);
            $fromStatus = Invoice::normalizeStatusValue($lockedInvoice->status) ?? Invoice::STATUS_DRAFT;
            $originalPaidAmount = round((float) $lockedInvoice->paid_amount, 2);
            $originalPaidAt = $lockedInvoice->paid_at?->toDateTimeString();

            $lockedInvoice->paid_amount = $lockedInvoice->getTotalPaid();
            $lockedInvoice->updatePaymentStatus();

            $toStatus = Invoice::normalizeStatusValue($lockedInvoice->status) ?? Invoice::STATUS_DRAFT;
            $paidAmountChanged = round((float) $lockedInvoice->paid_amount, 2) !== $originalPaidAmount;
            $paidAtChanged = $lockedInvoice->paid_at?->toDateTimeString() !== $originalPaidAt;
            $statusChanged = $fromStatus !== $toStatus;

            if (! $paidAmountChanged && ! $paidAtChanged && ! $statusChanged) {
                return $lockedInvoice;
            }

            if ($persistQuietly) {
                $lockedInvoice->saveQuietly();
            } else {
                $lockedInvoice->save();
            }

            $lockedInvoice->refresh();

            if ($statusChanged) {
                $this->recordFinancialStatusAudit(
                    invoice: $lockedInvoice,
                    action: $auditAction,
                    actorId: $resolvedActorId,
                    fromStatus: $fromStatus,
                    toStatus: $toStatus,
                    reason: $reason,
                    metadata: $metadata,
                );
            }

            return $lockedInvoice;
        }, 3);
    }

    protected function assertCanCancel(Invoice $invoice): void
    {
        if ($invoice->hasPayments()) {
            throw ValidationException::withMessages([
                'status' => 'Khong the huy hoa don da co thanh toan.',
            ]);
        }

        if ($invoice->hasInstallmentPlan()) {
            throw ValidationException::withMessages([
                'status' => 'Khong the huy hoa don da co ho so tra gop.',
            ]);
        }
    }

    protected function resolveActorId(?int $actorId): ?int
    {
        return $actorId ?? (is_numeric(auth()->id()) ? (int) auth()->id() : null);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function recordFinancialStatusAudit(
        Invoice $invoice,
        string $action,
        ?int $actorId,
        string $fromStatus,
        string $toStatus,
        ?string $reason,
        array $metadata = [],
    ): void {
        AuditLog::record(
            entityType: AuditLog::ENTITY_INVOICE,
            entityId: (int) $invoice->getKey(),
            action: $action,
            actorId: $actorId,
            metadata: WorkflowAuditMetadata::transition(
                fromStatus: $fromStatus,
                toStatus: $toStatus,
                reason: $reason,
                metadata: array_filter(array_merge($metadata, [
                    'invoice_id' => (int) $invoice->getKey(),
                    'invoice_no' => $invoice->invoice_no,
                    'patient_id' => $invoice->patient_id,
                    'branch_id' => $invoice->branch_id,
                    'previous_status' => $fromStatus,
                    'paid_amount' => round((float) $invoice->paid_amount, 2),
                    'total_amount' => round((float) $invoice->total_amount, 2),
                    'paid_at' => $invoice->paid_at?->toDateTimeString(),
                ]), static fn (mixed $value): bool => $value !== null),
            ),
        );
    }
}
