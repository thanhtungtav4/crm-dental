<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\FactoryOrder;
use App\Models\Invoice;
use App\Models\MaterialIssueNote;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\TreatmentMaterial;
use App\Models\TreatmentPlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PatientOverviewReadModelService
{
    /**
     * @return array{
     *     treatment_plans_count:int,
     *     active_treatment_plans_count:int,
     *     invoices_count:int,
     *     unpaid_invoices_count:int,
     *     total_owed:float,
     *     appointments_count:int,
     *     upcoming_appointments_count:int,
     *     total_spent:float,
     *     total_paid:float,
     *     last_visit_at:?Carbon,
     *     next_appointment_at:?Carbon
     * }
     */
    public function overview(Patient $patient): array
    {
        $treatmentPlans = $patient->treatmentPlans()
            ->get(['id', 'status']);

        $invoices = $patient->invoices()
            ->get(['id', 'status', 'total_amount', 'paid_amount']);

        $appointments = $patient->appointments()
            ->orderBy('date')
            ->get(['id', 'date', 'status']);

        $activeTreatmentStatuses = [
            TreatmentPlan::STATUS_APPROVED,
            TreatmentPlan::STATUS_IN_PROGRESS,
        ];

        $unpaidInvoiceStatuses = [
            'issued',
            'partial',
            'overdue',
        ];

        $upcomingAppointments = $appointments
            ->filter(function (Appointment $appointment): bool {
                return $appointment->date !== null
                    && $appointment->date->isFuture()
                    && in_array(
                        Appointment::normalizeStatus($appointment->status),
                        Appointment::activeStatuses(),
                        true,
                    );
            })
            ->values();

        $lastVisit = $appointments
            ->filter(fn (Appointment $appointment): bool => in_array(
                Appointment::normalizeStatus($appointment->status),
                Appointment::statusesForQuery([Appointment::STATUS_COMPLETED]),
                true,
            ))
            ->sortByDesc(fn (Appointment $appointment): int => $appointment->date?->getTimestamp() ?? 0)
            ->first();

        return [
            'treatment_plans_count' => $treatmentPlans->count(),
            'active_treatment_plans_count' => $treatmentPlans
                ->whereIn('status', $activeTreatmentStatuses)
                ->count(),
            'invoices_count' => $invoices->count(),
            'unpaid_invoices_count' => $invoices
                ->whereIn('status', $unpaidInvoiceStatuses)
                ->count(),
            'total_owed' => round((float) $invoices
                ->whereIn('status', $unpaidInvoiceStatuses)
                ->sum(fn ($invoice): float => max(0, (float) $invoice->total_amount - (float) $invoice->paid_amount)), 2),
            'appointments_count' => $appointments->count(),
            'upcoming_appointments_count' => $upcomingAppointments->count(),
            'total_spent' => round((float) $invoices
                ->where('status', 'paid')
                ->sum('total_amount'), 2),
            'total_paid' => round((float) $invoices->sum('paid_amount'), 2),
            'last_visit_at' => $lastVisit?->date,
            'next_appointment_at' => $upcomingAppointments->first()?->date,
        ];
    }

    /**
     * @return array{
     *     total_treatment_amount:float,
     *     total_discount_amount:float,
     *     must_pay_amount:float,
     *     net_collected_amount:float,
     *     remaining_amount:float,
     *     balance_amount:float,
     *     balance_is_positive:bool,
     *     latest_invoice_id:?int
     * }
     */
    public function paymentSummary(Patient $patient): array
    {
        $invoices = $patient->invoices()
            ->latest('created_at')
            ->get(['id', 'status', 'total_amount', 'discount_amount', 'paid_amount']);

        $payments = $patient->payments()
            ->get(['payments.id', 'payments.direction', 'payments.amount']);

        $totalTreatmentAmount = round((float) $invoices->sum('total_amount'), 2);
        $totalDiscountAmount = round((float) $invoices->sum('discount_amount'), 2);
        $mustPayAmount = max(0, $totalTreatmentAmount);
        $receiptAmount = round((float) $payments->where('direction', 'receipt')->sum('amount'), 2);
        $refundAmount = abs(round((float) $payments->where('direction', 'refund')->sum('amount'), 2));
        $netCollectedAmount = round($receiptAmount - $refundAmount, 2);
        $remainingAmount = max(0, round($mustPayAmount - $netCollectedAmount, 2));
        $balanceAmount = round($netCollectedAmount - $mustPayAmount, 2);

        $latestInvoiceId = $invoices
            ->firstWhere(fn ($invoice): bool => ! in_array($invoice->status, ['paid', 'cancelled'], true))
            ?->id;

        $latestInvoiceId ??= $invoices->first()?->id;

        return [
            'total_treatment_amount' => $totalTreatmentAmount,
            'total_discount_amount' => $totalDiscountAmount,
            'must_pay_amount' => $mustPayAmount,
            'net_collected_amount' => $netCollectedAmount,
            'remaining_amount' => $remainingAmount,
            'balance_amount' => $balanceAmount,
            'balance_is_positive' => $balanceAmount >= 0,
            'latest_invoice_id' => $latestInvoiceId,
        ];
    }

    /**
     * @return array{
     *     treatment_plans:int,
     *     invoices:int,
     *     appointments:int,
     *     notes:int,
     *     clinical_notes:int,
     *     exam_sessions:int,
     *     photos:int,
     *     prescriptions:int,
     *     payments:int,
     *     materials:int,
     *     activity:int
     * }
     */
    public function tabCounters(Patient $patient): array
    {
        $countedPatient = Patient::query()
            ->withCount([
                'treatmentPlans',
                'invoices',
                'appointments',
                'notes',
                'clinicalNotes',
                'examSessions',
                'photos',
                'prescriptions',
                'payments',
                'branchLogs',
                'factoryOrders',
                'materialIssueNotes',
            ])
            ->findOrFail($patient->getKey());

        $materialCount = TreatmentMaterial::query()
            ->whereHas('session.treatmentPlan', fn ($query) => $query->where('patient_id', $patient->id))
            ->count();

        $factoryOrdersCount = (int) ($countedPatient->factory_orders_count ?? 0);
        $materialIssueNotesCount = (int) ($countedPatient->material_issue_notes_count ?? 0);
        $appointmentsCount = (int) ($countedPatient->appointments_count ?? 0);
        $treatmentPlansCount = (int) ($countedPatient->treatment_plans_count ?? 0);
        $invoicesCount = (int) ($countedPatient->invoices_count ?? 0);
        $paymentsCount = (int) ($countedPatient->payments_count ?? 0);
        $notesCount = (int) ($countedPatient->notes_count ?? 0);

        return [
            'treatment_plans' => $treatmentPlansCount,
            'invoices' => $invoicesCount,
            'appointments' => $appointmentsCount,
            'notes' => $notesCount,
            'clinical_notes' => (int) ($countedPatient->clinical_notes_count ?? 0),
            'exam_sessions' => (int) ($countedPatient->exam_sessions_count ?? 0),
            'photos' => (int) ($countedPatient->photos_count ?? 0),
            'prescriptions' => (int) ($countedPatient->prescriptions_count ?? 0),
            'payments' => $paymentsCount,
            'materials' => $materialCount + $factoryOrdersCount + $materialIssueNotesCount,
            'activity' => $appointmentsCount
                + $treatmentPlansCount
                + $invoicesCount
                + $paymentsCount
                + $notesCount
                + (int) ($countedPatient->branch_logs_count ?? 0),
        ];
    }

    /**
     * @return Collection<int, FactoryOrder>
     */
    public function factoryOrders(Patient $patient): Collection
    {
        return $patient->factoryOrders()
            ->withCount('items')
            ->latest('ordered_at')
            ->latest('id')
            ->limit(20)
            ->get([
                'id',
                'order_no',
                'patient_id',
                'status',
                'priority',
                'ordered_at',
                'due_at',
                'delivered_at',
                'notes',
            ]);
    }

    /**
     * @return Collection<int, MaterialIssueNote>
     */
    public function materialIssueNotes(Patient $patient): Collection
    {
        return $patient->materialIssueNotes()
            ->withCount('items')
            ->withSum('items as total_cost', 'total_cost')
            ->latest('issued_at')
            ->latest('id')
            ->limit(20)
            ->get([
                'id',
                'note_no',
                'patient_id',
                'status',
                'issued_at',
                'posted_at',
                'reason',
                'notes',
            ]);
    }

    /**
     * @return Collection<int, TreatmentMaterial>
     */
    public function materialUsages(Patient $patient): Collection
    {
        return TreatmentMaterial::query()
            ->with(['session', 'material', 'user'])
            ->whereHas('session.treatmentPlan', fn ($query) => $query->where('patient_id', $patient->id))
            ->latest('created_at')
            ->limit(100)
            ->get();
    }

    /**
     * @return Collection<int, Prescription>
     */
    public function latestPrescriptions(Patient $patient): Collection
    {
        return $patient->prescriptions()
            ->latest('created_at')
            ->limit(5)
            ->get([
                'id',
                'patient_id',
                'prescription_code',
                'treatment_date',
                'created_at',
            ]);
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function latestInvoices(Patient $patient): Collection
    {
        return $patient->invoices()
            ->latest('created_at')
            ->limit(5)
            ->get([
                'id',
                'patient_id',
                'invoice_no',
                'issued_at',
                'created_at',
            ]);
    }
}
