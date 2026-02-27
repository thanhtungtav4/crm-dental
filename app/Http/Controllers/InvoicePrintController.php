<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
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

        $isPdf = request()->boolean('pdf', true);

        AuditLog::record(
            entityType: AuditLog::ENTITY_INVOICE,
            entityId: (int) $invoice->id,
            action: AuditLog::ACTION_PRINT,
            actorId: auth()->id(),
            metadata: [
                'channel' => 'invoice_print',
                'output' => $isPdf ? 'pdf' : 'html',
                'branch_id' => $invoice->resolveBranchId(),
                'patient_id' => $invoice->patient_id,
                'invoice_no' => $invoice->invoice_no,
            ],
        );

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class) && $isPdf) {
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
        $wasExported = (bool) $invoice->invoice_exported;

        if (! $invoice->invoice_exported) {
            $invoice->forceFill([
                'invoice_exported' => true,
                'exported_at' => now(),
            ])->save();
        }

        AuditLog::record(
            entityType: AuditLog::ENTITY_INVOICE,
            entityId: (int) $invoice->id,
            action: AuditLog::ACTION_EXPORT,
            actorId: auth()->id(),
            metadata: [
                'channel' => 'invoice_export',
                'branch_id' => $invoice->resolveBranchId(),
                'patient_id' => $invoice->patient_id,
                'invoice_no' => $invoice->invoice_no,
                'was_exported' => $wasExported,
                'is_exported' => (bool) $invoice->invoice_exported,
                'exported_at' => $invoice->exported_at?->toIso8601String(),
            ],
        );

        return response()->json([
            'ok' => true,
            'invoice_id' => $invoice->id,
            'invoice_exported' => (bool) $invoice->invoice_exported,
            'exported_at' => $invoice->exported_at?->toIso8601String(),
        ]);
    }
}
