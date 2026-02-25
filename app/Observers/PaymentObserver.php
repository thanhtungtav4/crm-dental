<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\Payment;

class PaymentObserver
{
    public function created(Payment $payment): void
    {
        $payment->invoice?->updatePaidAmount();
    }

    public function updated(Payment $payment): void
    {
        if ($payment->wasChanged('invoice_id')) {
            Invoice::query()
                ->find($payment->getOriginal('invoice_id'))
                ?->updatePaidAmount();
        }

        $payment->invoice?->updatePaidAmount();
    }

    public function deleted(Payment $payment): void
    {
        Invoice::query()
            ->find($payment->invoice_id)
            ?->updatePaidAmount();
    }

    public function restored(Payment $payment): void
    {
        $payment->invoice?->updatePaidAmount();
    }
}

