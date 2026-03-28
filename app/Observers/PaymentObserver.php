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
        $payment->invoice?->updatePaidAmount();
        $this->syncInstallmentPlan($payment);
        app(PatientWalletService::class)->postPayment($payment);
        $this->logPaymentCreated($payment);
    }

    public function updated(Payment $payment): void
    {
        if ($payment->wasChanged('invoice_id')) {
            Invoice::query()
                ->find($payment->getOriginal('invoice_id'))
                ?->updatePaidAmount();
        }

        $payment->invoice?->updatePaidAmount();
        $this->syncInstallmentPlan($payment);
    }

    public function deleted(Payment $payment): void
    {
        Invoice::query()
            ->find($payment->invoice_id)
            ?->updatePaidAmount();

        $this->syncInstallmentPlan($payment);
    }

    public function restored(Payment $payment): void
    {
        $payment->invoice?->updatePaidAmount();
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
}
