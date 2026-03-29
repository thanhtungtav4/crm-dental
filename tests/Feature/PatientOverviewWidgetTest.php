<?php

use App\Filament\Resources\Patients\Pages\ViewPatient;
use App\Filament\Resources\Patients\Widgets\PatientOverviewWidget;
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
use Illuminate\Support\Carbon;
use Livewire\Livewire;

if (! function_exists('createPatientOverviewWidgetInvoice')) {
    function createPatientOverviewWidgetInvoice(
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

it('renders patient overview widget through the shared read model service', function (): void {
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

    $completedPlan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'status' => TreatmentPlan::STATUS_COMPLETED,
    ]);

    createPatientOverviewWidgetInvoice($patient, $doctor, $manager, $completedPlan, [
        'invoice_no' => 'INV-POW-001',
        'status' => Invoice::STATUS_PARTIAL,
        'total_amount' => 1_000_000,
        'paid_amount' => 200_000,
    ], [
        [
            'amount' => 200_000,
            'method' => 'cash',
            'paid_at' => $now->copy()->subDay(),
        ],
    ]);

    Appointment::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => $now->copy()->addDays(2)->setTime(9, 30),
        'status' => Appointment::STATUS_CONFIRMED,
    ]);

    Appointment::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => $now->copy()->subDays(4)->setTime(14, 0),
        'status' => Appointment::STATUS_COMPLETED,
    ]);

    $this->actingAs($manager);

    Livewire::test(PatientOverviewWidget::class, ['record' => $patient])
        ->assertSee('Kế hoạch điều trị')
        ->assertSee('1 đang thực hiện')
        ->assertSee('Hóa đơn')
        ->assertSee('Còn nợ: 800.000đ')
        ->assertSee('Lịch hẹn')
        ->assertSee('1 lịch sắp tới')
        ->assertSee('Lịch hẹn tiếp theo')
        ->assertSee('21/03/2026 09:30');

    Carbon::setTestNow();
});

it('renders patient overview widget on the patient workspace page', function (): void {
    $branch = Branch::factory()->create();

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

    $this->actingAs($manager)
        ->get(ViewPatient::getUrl([
            'record' => $patient,
            'tab' => 'basic-info',
        ]))
        ->assertSuccessful()
        ->assertSeeLivewire(PatientOverviewWidget::class)
        ->assertSee('Kế hoạch điều trị')
        ->assertSee('Hóa đơn')
        ->assertSee('Lịch hẹn tiếp theo');
});

it('renders patient payment summary through the shared read model on the workspace page', function (): void {
    $branch = Branch::factory()->create();

    $admin = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $admin->assignRole('Admin');

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

    $latestInvoice = createPatientOverviewWidgetInvoice($patient, $doctor, $admin, $completedPlan, [
        'invoice_no' => 'INV-PAGE-001',
        'status' => Invoice::STATUS_PARTIAL,
        'total_amount' => 1_800_000,
        'discount_amount' => 150_000,
        'paid_amount' => 400_000,
    ], [
        [
            'amount' => 400_000,
            'method' => 'cash',
            'paid_at' => now(),
        ],
    ]);

    $paymentCreateUrl = route('filament.admin.resources.payments.create', [
        'invoice_id' => $latestInvoice->id,
    ]);

    $this->actingAs($admin)
        ->get(ViewPatient::getUrl([
            'record' => $patient,
            'tab' => 'payments',
        ]))
        ->assertSuccessful()
        ->assertSee('1.800.000')
        ->assertSee('150.000')
        ->assertSee('400.000')
        ->assertSee('1.400.000')
        ->assertSee($paymentCreateUrl);
});
