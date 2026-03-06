<?php

namespace App\Services;

use App\Models\Invoice;
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
}
