<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;

class PaymentObserver
{
    public function created(Payment $payment): void
    {
        $payment->invoice?->updatePaidAmount();
        $payment->invoice?->installmentPlan?->syncFinancialState();
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
        $payment->invoice?->installmentPlan?->syncFinancialState();
    }

    public function deleted(Payment $payment): void
    {
        Invoice::query()
            ->find($payment->invoice_id)
            ?->updatePaidAmount();

        $payment->invoice?->installmentPlan?->syncFinancialState();
    }

    public function restored(Payment $payment): void
    {
        $payment->invoice?->updatePaidAmount();
        $payment->invoice?->installmentPlan?->syncFinancialState();
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

        AuditLog::record(
            entityType: AuditLog::ENTITY_PAYMENT,
            entityId: $payment->id,
            action: $action,
            actorId: $payment->received_by,
            metadata: [
                'patient_id' => $invoice->patient_id,
                'invoice_id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'amount' => $payment->amount,
                'method' => $payment->method,
                'direction' => $payment->direction,
                'is_deposit' => (bool) $payment->is_deposit,
                'reversal_of_id' => $payment->reversal_of_id,
                'refund_reason' => $payment->refund_reason,
            ]
        );
    }
}
