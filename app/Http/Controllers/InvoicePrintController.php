<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class InvoicePrintController extends Controller
{
    public function __invoke(Invoice $invoice): Response
    {
        $invoice->load([
            'patient',
            'plan',
            'payments.receiver',
        ]);

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class) && request()->boolean('pdf', true)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoices.print', [
                'invoice' => $invoice,
                'isPdf' => true,
            ]);

            /** @var Response $response */
            $response = $pdf->stream("hoa-don-{$invoice->invoice_no}.pdf");

            return $response;
        }

        return response()->view('invoices.print', [
            'invoice' => $invoice,
            'isPdf' => false,
        ]);
    }

    public function markExported(Invoice $invoice): JsonResponse
    {
        if (! $invoice->invoice_exported) {
            $invoice->forceFill([
                'invoice_exported' => true,
                'exported_at' => now(),
            ])->save();
        }

        return response()->json([
            'ok' => true,
            'invoice_id' => $invoice->id,
            'invoice_exported' => (bool) $invoice->invoice_exported,
            'exported_at' => $invoice->exported_at?->toIso8601String(),
        ]);
    }
}
