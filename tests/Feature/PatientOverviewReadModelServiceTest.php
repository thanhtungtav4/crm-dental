<?php

use App\Filament\Resources\Patients\Pages\ViewPatient;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\FactoryOrder;
use App\Models\FactoryOrderItem;
use App\Models\Invoice;
use App\Models\Material;
use App\Models\MaterialBatch;
use App\Models\MaterialIssueItem;
use App\Models\MaterialIssueNote;
use App\Models\Patient;
use App\Models\PatientMedicalRecord;
use App\Models\Payment;
use App\Models\PlanItem;
use App\Models\Prescription;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Services\PatientOverviewReadModelService;
use App\Services\TreatmentMaterialUsageService;
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

it('builds patient workspace lab material surfaces through the shared read model', function (): void {
    $branch = Branch::factory()->create();

    $admin = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $admin->assignRole('Admin');
    $this->actingAs($admin);

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

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);

    $planItem = PlanItem::factory()->create([
        'treatment_plan_id' => $plan->id,
    ]);

    $session = TreatmentSession::factory()->create([
        'treatment_plan_id' => $plan->id,
        'plan_item_id' => $planItem->id,
        'doctor_id' => $doctor->id,
        'performed_at' => now(),
        'status' => 'done',
    ]);

    $material = Material::factory()->create([
        'branch_id' => $branch->id,
        'stock_qty' => 100,
        'cost_price' => 125000,
    ]);

    $batch = MaterialBatch::query()->create([
        'material_id' => $material->id,
        'batch_number' => 'LOT-POV-001',
        'expiry_date' => now()->addMonths(6)->toDateString(),
        'quantity' => 100,
        'purchase_price' => 125000,
        'received_date' => today()->toDateString(),
        'status' => 'active',
    ]);

    $usage = app(TreatmentMaterialUsageService::class)->create([
        'treatment_session_id' => $session->id,
        'material_id' => $material->id,
        'batch_id' => $batch->id,
        'quantity' => 2,
        'cost' => 0,
        'used_by' => $manager->id,
    ]);

    $supplier = \App\Models\Supplier::query()->create([
        'name' => 'Lab Scope Test',
        'code' => 'LAB-SCOPE',
        'active' => true,
        'created_by' => $admin->id,
        'updated_by' => $admin->id,
    ]);

    $factoryOrder = FactoryOrder::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'doctor_id' => $doctor->id,
        'supplier_id' => $supplier->id,
        'requested_by' => $admin->id,
        'status' => FactoryOrder::STATUS_DRAFT,
        'priority' => 'high',
        'ordered_at' => now()->subDay(),
        'due_at' => now()->addDays(2),
        'notes' => 'Scope test order',
    ]);

    FactoryOrderItem::query()->create([
        'factory_order_id' => $factoryOrder->id,
        'item_name' => 'Abutment zirconia',
        'quantity' => 1,
        'unit_price' => 450000,
        'status' => 'ordered',
    ]);

    $issueNote = MaterialIssueNote::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'issued_by' => $manager->id,
        'issued_at' => now()->subHours(2),
        'status' => MaterialIssueNote::STATUS_DRAFT,
        'reason' => 'Chairside support',
        'notes' => 'Workspace scope test',
    ]);

    MaterialIssueItem::query()->create([
        'material_issue_note_id' => $issueNote->id,
        'material_id' => $material->id,
        'material_batch_id' => $batch->id,
        'quantity' => 1,
        'unit_cost' => 125000,
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

    $otherPlan = TreatmentPlan::factory()->create([
        'patient_id' => $otherPatient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
    ]);

    $otherPlanItem = PlanItem::factory()->create([
        'treatment_plan_id' => $otherPlan->id,
    ]);

    $otherSession = TreatmentSession::factory()->create([
        'treatment_plan_id' => $otherPlan->id,
        'plan_item_id' => $otherPlanItem->id,
        'doctor_id' => $doctor->id,
        'performed_at' => now(),
        'status' => 'done',
    ]);

    app(TreatmentMaterialUsageService::class)->create([
        'treatment_session_id' => $otherSession->id,
        'material_id' => $material->id,
        'batch_id' => $batch->id,
        'quantity' => 1,
        'cost' => 0,
        'used_by' => $manager->id,
    ]);

    FactoryOrder::query()->create([
        'patient_id' => $otherPatient->id,
        'branch_id' => $branch->id,
        'doctor_id' => $doctor->id,
        'supplier_id' => $supplier->id,
        'requested_by' => $admin->id,
        'status' => FactoryOrder::STATUS_DRAFT,
        'priority' => 'normal',
        'ordered_at' => now(),
        'notes' => 'Other patient order',
    ]);

    MaterialIssueNote::query()->create([
        'patient_id' => $otherPatient->id,
        'branch_id' => $branch->id,
        'issued_by' => $manager->id,
        'issued_at' => now(),
        'status' => MaterialIssueNote::STATUS_DRAFT,
        'reason' => 'Other patient issue note',
    ]);

    $service = app(PatientOverviewReadModelService::class);

    $materialUsages = $service->materialUsages($patient);
    $factoryOrders = $service->factoryOrders($patient);
    $materialIssueNotes = $service->materialIssueNotes($patient);

    expect($materialUsages)->toHaveCount(1)
        ->and($materialUsages->pluck('id')->all())->toBe([$usage->id])
        ->and($materialUsages->first()?->material?->id)->toBe($material->id)
        ->and($materialUsages->first()?->user?->id)->toBe($admin->id);

    expect($factoryOrders)->toHaveCount(1)
        ->and($factoryOrders->pluck('id')->all())->toBe([$factoryOrder->id])
        ->and((int) ($factoryOrders->first()?->items_count ?? 0))->toBe(1);

    expect($materialIssueNotes)->toHaveCount(1)
        ->and($materialIssueNotes->pluck('id')->all())->toBe([$issueNote->id])
        ->and((int) ($materialIssueNotes->first()?->items_count ?? 0))->toBe(1)
        ->and((float) ($materialIssueNotes->first()?->total_cost ?? 0))->toEqualWithDelta(125000.0, 0.01);

    $page = new class extends ViewPatient
    {
        public function forceRecord(Patient $patient): void
        {
            $this->record = $patient;
        }
    };

    $page->forceRecord($patient->fresh());

    expect($page->getMaterialUsagesProperty()->pluck('id')->all())->toBe([$usage->id])
        ->and($page->getFactoryOrdersProperty()->pluck('id')->all())->toBe([$factoryOrder->id])
        ->and($page->getMaterialIssueNotesProperty()->pluck('id')->all())->toBe([$issueNote->id]);
});

it('builds latest printable forms through the shared read model', function (): void {
    $branch = Branch::factory()->create();

    $admin = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

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

    $prescriptionIds = collect();

    foreach (range(1, 6) as $index) {
        $prescription = Prescription::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'created_at' => now()->subMinutes(10 - $index),
            'updated_at' => now()->subMinutes(10 - $index),
        ]);

        $prescriptionIds->push($prescription->id);
    }

    $invoiceIds = collect();

    foreach (range(1, 6) as $index) {
        $invoice = createPatientOverviewInvoice($patient, $doctor, $manager, $completedPlan, [
            'invoice_no' => sprintf('INV-FORM-%03d', $index),
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 500000 + ($index * 10000),
            'paid_amount' => 0,
            'issued_at' => now()->subDays(7 - $index),
            'created_at' => now()->subMinutes(10 - $index),
            'updated_at' => now()->subMinutes(10 - $index),
        ]);

        $invoiceIds->push($invoice->id);
    }

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

    Prescription::factory()->create([
        'patient_id' => $otherPatient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
    ]);

    createPatientOverviewInvoice($otherPatient, $doctor, $manager, null, [
        'invoice_no' => 'INV-FORM-OTHER',
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 999999,
        'paid_amount' => 0,
    ]);

    $service = app(PatientOverviewReadModelService::class);

    $latestPrescriptions = $service->latestPrescriptions($patient);
    $latestInvoices = $service->latestInvoices($patient);

    expect($latestPrescriptions)->toHaveCount(5)
        ->and($latestPrescriptions->pluck('id')->all())->toBe($prescriptionIds->reverse()->take(5)->values()->all());

    expect($latestInvoices)->toHaveCount(5)
        ->and($latestInvoices->pluck('id')->all())->toBe($invoiceIds->reverse()->take(5)->values()->all());

    $page = new class extends ViewPatient
    {
        public function forceRecord(Patient $patient): void
        {
            $this->record = $patient;
        }
    };

    $page->forceRecord($patient->fresh());

    expect($page->getLatestPrescriptionsProperty()->pluck('id')->all())->toBe($prescriptionIds->reverse()->take(5)->values()->all())
        ->and($page->getLatestInvoicesProperty()->pluck('id')->all())->toBe($invoiceIds->reverse()->take(5)->values()->all());
});

it('builds patient medical record actions through the shared read model', function (): void {
    $branch = Branch::factory()->create();

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
        'primary_doctor_id' => $doctor->id,
    ]);

    $service = app(PatientOverviewReadModelService::class);

    $createAction = $service->medicalRecordAction($patient, $doctor, includePatientContextOnEditUrl: true);

    expect($createAction)->toMatchArray([
        'label' => 'Tạo bệnh án điện tử',
        'mode' => 'create',
        'record_id' => null,
        'url' => route('filament.admin.resources.patient-medical-records.create', [
            'patient_id' => $patient->id,
        ]),
    ]);

    $medicalRecord = PatientMedicalRecord::query()->create([
        'patient_id' => $patient->id,
        'updated_by' => $doctor->id,
    ]);

    $editAction = $service->medicalRecordAction($patient->fresh(), $doctor, includePatientContextOnEditUrl: true);

    expect($editAction)->toMatchArray([
        'label' => 'Mở bệnh án điện tử',
        'mode' => 'edit',
        'record_id' => $medicalRecord->id,
        'url' => route('filament.admin.resources.patient-medical-records.edit', [
            'record' => $medicalRecord->id,
            'patient_id' => $patient->id,
        ]),
    ]);

    expect($service->medicalRecordAction($patient->fresh(), null))->toBeNull();
});
