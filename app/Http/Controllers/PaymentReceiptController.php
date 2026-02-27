<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Http\Response;

class PaymentReceiptController extends Controller
{
    public function __invoke(Payment $payment): Response
    {
        $payment->load([
            'invoice.patient',
            'receiver',
        ]);
        $clinicBranding = ClinicRuntimeSettings::brandingProfile();

        $isPdf = request()->boolean('pdf', true);

        AuditLog::record(
            entityType: AuditLog::ENTITY_PAYMENT,
            entityId: (int) $payment->id,
            action: AuditLog::ACTION_PRINT,
            actorId: auth()->id(),
            metadata: [
                'channel' => 'payment_receipt_print',
                'output' => $isPdf ? 'pdf' : 'html',
                'branch_id' => $payment->resolveBranchId(),
                'invoice_id' => $payment->invoice_id,
                'patient_id' => $payment->invoice?->patient_id,
            ],
        );

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class) && $isPdf) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('payments.receipt', [
                'payment' => $payment,
                'clinicBranding' => $clinicBranding,
                'isPdf' => true,
            ]);

            /** @var Response $response */
            $response = $pdf->stream("phieu-{$payment->id}.pdf");

            return $response;
        }

        return response()->view('payments.receipt', [
            'payment' => $payment,
            'clinicBranding' => $clinicBranding,
            'isPdf' => false,
        ]);
    }
}
