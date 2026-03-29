<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PlanItem;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Services\PatientOverviewReadModelService;
use Illuminate\Support\Carbon;

if (! function_exists('createPatientOverviewInvoice')) {
    function createPatientOverviewInvoice(
        Patient $patient,
        User $doctor,
        User $receiver,
        ?TreatmentPlan $plan = null,
        array $invoiceAttributes = [],
        array $payments = [],
    ): Invoice {
        $plan ??= TreatmentPlan::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'branch_id' => $patient->first_branch_id,
        ]);

        $planItem = PlanItem::factory()->create([
            'treatment_plan_id' => $plan->id,
        ]);

        $session = TreatmentSession::factory()->create([
            'treatment_plan_id' => $plan->id,
            'plan_item_id' => $planItem->id,
            'doctor_id' => $doctor->id,
            'status' => 'scheduled',
        ]);

        if (array_key_exists('total_amount', $invoiceAttributes) && ! array_key_exists('subtotal', $invoiceAttributes)) {
            $discountAmount = (float) ($invoiceAttributes['discount_amount'] ?? 0);
            $taxAmount = (float) ($invoiceAttributes['tax_amount'] ?? 0);

            $invoiceAttributes['subtotal'] = (float) $invoiceAttributes['total_amount'] + $discountAmount - $taxAmount;
        }

        $invoice = Invoice::factory()->create(array_merge([
            'treatment_session_id' => $session->id,
            'treatment_plan_id' => $plan->id,
            'patient_id' => $patient->id,
            'branch_id' => $patient->first_branch_id,
            'status' => Invoice::STATUS_ISSUED,
            'subtotal' => 1_000_000,
            'total_amount' => 1_000_000,
            'paid_amount' => 0,
            'due_date' => now()->addDays(7)->toDateString(),
        ], $invoiceAttributes));

        foreach ($payments as $paymentAttributes) {
            Payment::factory()->create(array_merge([
                'invoice_id' => $invoice->id,
                'branch_id' => $patient->first_branch_id,
                'received_by' => $receiver->id,
                'direction' => 'receipt',
                'payment_source' => 'patient',
            ], $paymentAttributes));
        }

        return $invoice;
    }
}

it('summarizes patient overview metrics through a shared read model', function (): void {
    $now = Carbon::parse('2026-03-19 10:00:00');
    Carbon::setTestNow($now);

    $branch = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);

    TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);

    TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'status' => TreatmentPlan::STATUS_IN_PROGRESS,
    ]);

    $completedPlan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'status' => TreatmentPlan::STATUS_COMPLETED,
    ]);

    createPatientOverviewInvoice($patient, $doctor, $manager, $completedPlan, [
        'invoice_no' => 'INV-PO-001',
        'status' => Invoice::STATUS_PAID,
        'total_amount' => 1_200_000,
        'paid_amount' => 1_200_000,
    ], [
        [
            'amount' => 1_200_000,
            'method' => 'cash',
            'paid_at' => $now->copy()->subDays(2),
        ],
    ]);

    createPatientOverviewInvoice($patient, $doctor, $manager, $completedPlan, [
        'invoice_no' => 'INV-PO-002',
        'status' => Invoice::STATUS_PARTIAL,
        'total_amount' => 2_000_000,
        'paid_amount' => 500_000,
    ], [
        [
            'amount' => 500_000,
            'method' => 'card',
            'paid_at' => $now->copy()->subDay(),
        ],
    ]);

    createPatientOverviewInvoice($patient, $doctor, $manager, $completedPlan, [
        'invoice_no' => 'INV-PO-003',
        'status' => Invoice::STATUS_OVERDUE,
        'total_amount' => 1_500_000,
        'paid_amount' => 0,
        'due_date' => $now->copy()->subDays(3)->toDateString(),
    ]);

    Appointment::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => $now->copy()->subDay()->setTime(9, 0),
        'status' => Appointment::STATUS_COMPLETED,
    ]);

    Appointment::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => $now->copy()->addDay()->setTime(8, 30),
        'status' => Appointment::STATUS_CONFIRMED,
    ]);

    Appointment::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => $now->copy()->addDays(2)->setTime(11, 0),
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    Appointment::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => $now->copy()->addDays(3)->setTime(14, 0),
        'status' => Appointment::STATUS_CANCELLED,
        'cancellation_reason' => 'Patient requested another day',
    ]);

    $otherCustomer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $otherPatient = Patient::factory()->create([
        'customer_id' => $otherCustomer->id,
        'first_branch_id' => $branch->id,
        'full_name' => $otherCustomer->full_name,
        'phone' => $otherCustomer->phone,
        'email' => $otherCustomer->email,
    ]);

    createPatientOverviewInvoice($otherPatient, $doctor, $manager, null, [
        'invoice_no' => 'INV-PO-OTHER',
        'status' => Invoice::STATUS_PAID,
        'total_amount' => 9_999_000,
        'paid_amount' => 9_999_000,
    ]);

    $overview = app(PatientOverviewReadModelService::class)->overview($patient);

    expect($overview)->toMatchArray([
        'treatment_plans_count' => 3,
        'active_treatment_plans_count' => 2,
        'invoices_count' => 3,
        'unpaid_invoices_count' => 2,
        'total_owed' => 3000000.0,
        'appointments_count' => 4,
        'upcoming_appointments_count' => 2,
        'total_spent' => 1200000.0,
        'total_paid' => 1700000.0,
    ]);

    expect($overview['last_visit_at']?->format('Y-m-d H:i:s'))->toBe('2026-03-18 09:00:00')
        ->and($overview['next_appointment_at']?->format('Y-m-d H:i:s'))->toBe('2026-03-20 08:30:00');

    Carbon::setTestNow();
});

it('summarizes patient payment balances through the shared read model', function (): void {
    $branch = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);

    $completedPlan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'status' => TreatmentPlan::STATUS_COMPLETED,
    ]);

    createPatientOverviewInvoice($patient, $doctor, $manager, $completedPlan, [
        'invoice_no' => 'INV-PAYMENT-001',
        'status' => Invoice::STATUS_PAID,
        'total_amount' => 1_000_000,
        'discount_amount' => 100_000,
        'paid_amount' => 1_000_000,
    ], [
        [
            'amount' => 1_000_000,
            'method' => 'cash',
            'paid_at' => now()->subDay(),
        ],
    ]);

    $latestInvoice = createPatientOverviewInvoice($patient, $doctor, $manager, $completedPlan, [
        'invoice_no' => 'INV-PAYMENT-002',
        'status' => Invoice::STATUS_PARTIAL,
        'total_amount' => 2_500_000,
        'discount_amount' => 0,
        'paid_amount' => 500_000,
    ], [
        [
            'amount' => 500_000,
            'method' => 'card',
            'paid_at' => now(),
        ],
        [
            'amount' => 200_000,
            'direction' => 'refund',
            'method' => 'bank_transfer',
            'paid_at' => now()->addHour(),
        ],
    ]);

    $summary = app(PatientOverviewReadModelService::class)->paymentSummary($patient);

    expect($summary)->toMatchArray([
        'total_treatment_amount' => 3500000.0,
        'total_discount_amount' => 100000.0,
        'must_pay_amount' => 3500000.0,
        'net_collected_amount' => 1300000.0,
        'remaining_amount' => 2200000.0,
        'balance_amount' => -2200000.0,
        'balance_is_positive' => false,
        'latest_invoice_id' => $latestInvoice->id,
    ]);
});
