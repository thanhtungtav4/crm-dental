<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Response;

class PaymentReceiptController extends Controller
{
    public function __invoke(Payment $payment): Response
    {
        $payment->load([
            'invoice.patient',
            'receiver',
        ]);

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class) && request()->boolean('pdf', true)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('payments.receipt', [
                'payment' => $payment,
                'isPdf' => true,
            ]);

            /** @var Response $response */
            $response = $pdf->stream("phieu-{$payment->id}.pdf");
            return $response;
        }

        return response()->view('payments.receipt', [
            'payment' => $payment,
            'isPdf' => false,
        ]);
    }
}
