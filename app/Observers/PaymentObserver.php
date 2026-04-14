<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InstallmentPlanLifecycleService;
use App\Services\PatientWalletService;
use App\Support\WorkflowAuditMetadata;

class PaymentObserver
{
    public function __construct(
        protected InstallmentPlanLifecycleService $installmentPlanLifecycleService,
    ) {}

    public function created(Payment $payment): void
    {
        $payment->invoice?->updatePaidAmount(
            actorId: is_numeric($payment->received_by) ? (int) $payment->received_by : null,
            reason: $this->invoiceFinancialReason($payment),
            metadata: $this->invoiceFinancialMetadata($payment),
        );
        $this->syncInstallmentPlan($payment);
        app(PatientWalletService::class)->postPayment($payment);
        $this->logPaymentCreated($payment);
    }

    public function updated(Payment $payment): void
    {
        if ($payment->wasChanged('invoice_id')) {
            Invoice::query()
                ->find($payment->getOriginal('invoice_id'))
                ?->updatePaidAmount(
                    actorId: is_numeric($payment->received_by) ? (int) $payment->received_by : null,
                    metadata: array_filter([
                        'trigger' => 'payment_reassigned',
                        'payment_id' => $payment->id,
                        'from_invoice_id' => is_numeric($payment->getOriginal('invoice_id'))
                            ? (int) $payment->getOriginal('invoice_id')
                            : null,
                        'to_invoice_id' => is_numeric($payment->invoice_id) ? (int) $payment->invoice_id : null,
                    ], static fn (mixed $value): bool => $value !== null),
                );
        }

        $payment->invoice?->updatePaidAmount(
            actorId: is_numeric($payment->received_by) ? (int) $payment->received_by : null,
            reason: $this->invoiceFinancialReason($payment),
            metadata: array_merge($this->invoiceFinancialMetadata($payment), [
                'trigger' => 'payment_updated',
            ]),
        );
        $this->syncInstallmentPlan($payment);
    }

    public function deleted(Payment $payment): void
    {
        Invoice::query()
            ->find($payment->invoice_id)
            ?->updatePaidAmount(
                actorId: is_numeric($payment->received_by) ? (int) $payment->received_by : null,
                reason: $this->invoiceFinancialReason($payment),
                metadata: array_merge($this->invoiceFinancialMetadata($payment), [
                    'trigger' => 'payment_deleted',
                ]),
            );

        $this->syncInstallmentPlan($payment);
    }

    public function restored(Payment $payment): void
    {
        $payment->invoice?->updatePaidAmount(
            actorId: is_numeric($payment->received_by) ? (int) $payment->received_by : null,
            reason: $this->invoiceFinancialReason($payment),
            metadata: array_merge($this->invoiceFinancialMetadata($payment), [
                'trigger' => 'payment_restored',
            ]),
        );
        $this->syncInstallmentPlan($payment);
    }

    protected function syncInstallmentPlan(Payment $payment): void
    {
        if (! $payment->invoice?->installmentPlan) {
            return;
        }

        $this->installmentPlanLifecycleService->syncFinancialState($payment->invoice->installmentPlan);
    }

    protected function logPaymentCreated(Payment $payment): void
    {
        $invoice = $payment->invoice;

        if (! $invoice) {
            return;
        }

        $action = match (true) {
            $payment->direction === 'refund' && $payment->reversal_of_id !== null => AuditLog::ACTION_REVERSAL,
            $payment->direction === 'refund' => AuditLog::ACTION_REFUND,
            default => AuditLog::ACTION_CREATE,
        };

        $reason = in_array($action, [AuditLog::ACTION_REFUND, AuditLog::ACTION_REVERSAL], true)
            ? WorkflowAuditMetadata::normalizeReason($payment->refund_reason)
            : null;
        $trigger = match ($action) {
            AuditLog::ACTION_REVERSAL => 'manual_reversal',
            AuditLog::ACTION_REFUND => 'manual_refund',
            default => 'record_payment',
        };

        AuditLog::record(
            entityType: AuditLog::ENTITY_PAYMENT,
            entityId: $payment->id,
            action: $action,
            actorId: $payment->received_by,
            metadata: array_filter([
                'patient_id' => $invoice->patient_id,
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'amount' => $payment->amount,
                'method' => $payment->method,
                'direction' => $payment->direction,
                'trigger' => $trigger,
                'reason' => $reason,
                'is_deposit' => (bool) $payment->is_deposit,
                'reversal_of_id' => $payment->reversal_of_id,
                'refund_reason' => $payment->refund_reason,
                'transaction_ref' => $payment->transaction_ref,
                'payment_source' => $payment->payment_source,
            ], static fn (mixed $value): bool => $value !== null)
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function invoiceFinancialMetadata(Payment $payment): array
    {
        $trigger = match (true) {
            $payment->direction === 'refund' && $payment->reversal_of_id !== null => 'payment_reversal',
            $payment->direction === 'refund' => 'payment_refund',
            default => 'payment_recorded',
        };

        return array_filter([
            'trigger' => $trigger,
            'payment_id' => $payment->id,
            'direction' => $payment->direction,
            'transaction_ref' => $payment->transaction_ref,
            'payment_source' => $payment->payment_source,
            'reversal_of_id' => $payment->reversal_of_id,
            'is_deposit' => (bool) $payment->is_deposit,
        ], static fn (mixed $value): bool => $value !== null);
    }

    protected function invoiceFinancialReason(Payment $payment): ?string
    {
        if (! in_array($payment->direction, ['refund'], true) && $payment->reversal_of_id === null) {
            return null;
        }

        return WorkflowAuditMetadata::normalizeReason($payment->refund_reason);
    }
}
