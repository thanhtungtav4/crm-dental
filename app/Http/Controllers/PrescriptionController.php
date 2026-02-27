<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Prescription;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Http\Response;

class PrescriptionController extends Controller
{
    public function print(Prescription $prescription): Response
    {
        $prescription->load(['patient', 'doctor', 'items']);
        $clinicBranding = ClinicRuntimeSettings::brandingProfile();

        $isPdf = request()->boolean('pdf', true);

        AuditLog::record(
            entityType: AuditLog::ENTITY_PRESCRIPTION,
            entityId: (int) $prescription->id,
            action: AuditLog::ACTION_PRINT,
            actorId: auth()->id(),
            metadata: [
                'channel' => 'prescription_print',
                'output' => $isPdf ? 'pdf' : 'html',
                'patient_id' => $prescription->patient_id,
                'doctor_id' => $prescription->doctor_id,
                'branch_id' => $prescription->resolveBranchId(),
            ],
        );

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class) && $isPdf) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('prescriptions.print', [
                'prescription' => $prescription,
                'clinicBranding' => $clinicBranding,
                'isPdf' => true,
            ]);

            /** @var Response $response */
            $response = $pdf->stream("don-thuoc-{$prescription->prescription_code}.pdf");

            return $response;
        }

        return response()
            ->view('prescriptions.print', [
                'prescription' => $prescription,
                'clinicBranding' => $clinicBranding,
                'isPdf' => false,
            ]);
    }
}
