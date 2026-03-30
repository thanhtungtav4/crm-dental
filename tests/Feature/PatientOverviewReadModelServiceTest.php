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

use function Pest\Laravel\actingAs;

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

it('builds patient identity header payload through the shared read model', function (): void {
    $branch = Branch::factory()->create();

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'full_name' => 'Nguyen Van An',
        'phone' => '0909123456',
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'gender' => 'male',
        'patient_code' => 'BN-000123',
    ]);

    $service = app(PatientOverviewReadModelService::class);
    $payload = $service->identityHeaderPayload($patient);

    expect($payload)->toMatchArray([
        'avatar_initials' => 'NA',
        'full_name' => 'Nguyen Van An',
        'gender_label' => 'Nam',
        'gender_badge_class' => 'is-male',
        'patient_code' => 'BN-000123',
        'patient_code_copy_label' => 'Mã bệnh nhân',
        'patient_code_copy_action_label' => 'Sao chép mã bệnh nhân',
        'phone' => '0909123456',
        'phone_href' => 'tel:0909123456',
        'phone_copy_label' => 'Số điện thoại',
        'phone_copy_action_label' => 'Sao chép số điện thoại',
    ]);

    $page = new class extends ViewPatient
    {
        public function forceRecord(Patient $patient): void
        {
            $this->record = $patient;
        }
    };

    $page->forceRecord($patient->fresh());

    expect($page->getIdentityHeaderProperty())->toMatchArray($payload);
});

it('builds patient basic info grid payload through the shared read model', function (): void {
    $branch = Branch::factory()->create([
        'name' => 'Chi nhánh Q1',
    ]);

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'full_name' => 'Tran Thi Binh',
        'phone' => '0911222333',
        'email' => 'binh@example.test',
        'address' => '123 Nguyen Hue, Quan 1',
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
        'address' => $customer->address,
        'birthday' => '1996-04-01',
    ]);

    $service = app(PatientOverviewReadModelService::class);
    $payload = $service->basicInfoGridPayload($patient->fresh('branch'));

    expect($payload)->toMatchArray([
        'phone' => '0911222333',
        'phone_href' => 'tel:0911222333',
        'email' => 'binh@example.test',
        'email_href' => 'mailto:binh@example.test',
        'birthday_label' => '01/04/1996',
        'age_label' => '('.\Carbon\Carbon::parse('1996-04-01')->age.' tuổi)',
        'branch_name' => 'Chi nhánh Q1',
        'address' => '123 Nguyen Hue, Quan 1',
    ]);

    expect($payload['cards'])->toHaveCount(4)
        ->and($payload['cards'][0])->toMatchArray([
            'key' => 'phone',
            'label' => 'Điện thoại',
            'icon' => 'heroicon-o-phone',
            'card_class' => 'is-phone',
            'value' => '0911222333',
            'href' => 'tel:0911222333',
            'copy_value' => '0911222333',
            'copy_label' => 'Số điện thoại',
            'copy_action_label' => 'Sao chép số điện thoại',
            'is_muted' => false,
            'is_truncate' => false,
        ])
        ->and($payload['cards'][1])->toMatchArray([
            'key' => 'email',
            'label' => 'Email',
            'icon' => 'heroicon-o-envelope',
            'card_class' => 'is-email',
            'value' => 'binh@example.test',
            'href' => 'mailto:binh@example.test',
            'title' => 'binh@example.test',
            'copy_action_label' => null,
            'is_muted' => false,
            'is_truncate' => true,
        ])
        ->and($payload['cards'][2])->toMatchArray([
            'key' => 'birthday',
            'label' => 'Ngày sinh',
            'icon' => 'heroicon-o-calendar-days',
            'card_class' => 'is-birthday',
            'value' => '01/04/1996',
            'meta' => '('.\Carbon\Carbon::parse('1996-04-01')->age.' tuổi)',
            'copy_action_label' => null,
            'is_muted' => false,
        ])
        ->and($payload['cards'][3])->toMatchArray([
            'key' => 'branch',
            'label' => 'Chi nhánh',
            'icon' => 'heroicon-o-building-office-2',
            'card_class' => 'is-branch',
            'value' => 'Chi nhánh Q1',
            'copy_action_label' => null,
            'is_muted' => false,
        ])
        ->and($payload['address_card'])->toMatchArray([
            'label' => 'Địa chỉ',
            'icon' => 'heroicon-o-map-pin',
            'value' => '123 Nguyen Hue, Quan 1',
        ]);

    $page = new class extends ViewPatient
    {
        public function forceRecord(Patient $patient): void
        {
            $this->record = $patient;
        }
    };

    $page->forceRecord($patient->fresh('branch'));

    expect($page->getBasicInfoGridProperty())->toMatchArray($payload);
});

it('builds patient basic info supporting panels through the shared read model', function (): void {
    $service = app(PatientOverviewReadModelService::class);
    $payload = $service->basicInfoPanelsPayload();

    expect($payload)->toMatchArray([
        'contacts' => [
            'title' => 'Người liên hệ',
            'description' => 'Tách danh sách người liên hệ để lễ tân/CSKH thao tác nhanh theo từng bệnh nhân.',
        ],
        'activity_log' => [
            'title' => 'Lịch sử thao tác',
            'description' => 'Xem timeline cập nhật ở tab chuyên biệt để tránh trùng lặp nội dung.',
            'action' => [
                'label' => 'Mở lịch sử thao tác',
                'tab' => 'activity-log',
                'button_class' => 'crm-btn crm-btn-primary crm-btn-md',
            ],
        ],
        'empty_state_text' => 'Không thể tải dữ liệu bệnh nhân',
    ]);

    $page = new class extends ViewPatient
    {
        public function forceRecord(Patient $patient): void
        {
            $this->record = $patient;
        }
    };

    $page->forceRecord(Patient::factory()->create());

    expect($page->getBasicInfoPanelsProperty())->toMatchArray($payload);
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
    $panel = app(PatientOverviewReadModelService::class)->paymentPanelPayload($patient, $manager);

    expect($summary)->toMatchArray([
        'total_treatment_amount' => 3500000.0,
        'total_treatment_amount_formatted' => '3.500.000',
        'total_discount_amount' => 100000.0,
        'total_discount_amount_formatted' => '100.000',
        'must_pay_amount' => 3500000.0,
        'must_pay_amount_formatted' => '3.500.000',
        'net_collected_amount' => 1300000.0,
        'net_collected_amount_formatted' => '1.300.000',
        'remaining_amount' => 2200000.0,
        'remaining_amount_formatted' => '2.200.000',
        'balance_amount' => -2200000.0,
        'balance_amount_formatted' => '-2.200.000',
        'balance_is_positive' => false,
        'latest_invoice_id' => $latestInvoice->id,
        'create_payment_url' => route('filament.admin.resources.payments.create', [
            'invoice_id' => $latestInvoice->id,
        ]),
    ]);

    expect($panel['summary'])->toMatchArray($summary)
        ->and($panel['title'])->toBe('Thông tin thanh toán')
        ->and($panel['balance_class'])->toBe('is-negative')
        ->and($panel['balance_amount_formatted'])->toBe('-2.200.000')
        ->and($panel['balance_text'])->toBe('Số dư:')
        ->and($panel['can_view_invoices'])->toBeBool()
        ->and($panel['can_view_payments'])->toBeBool()
        ->and($panel['can_create_payment'])->toBeBool()
        ->and($panel['metrics'])->toBe([
            [
                'label' => 'Tổng tiền điều trị',
                'value' => '3.500.000',
                'value_class' => null,
            ],
            [
                'label' => 'Giảm giá',
                'value' => '100.000',
                'value_class' => null,
            ],
            [
                'label' => 'Phải thanh toán',
                'value' => '3.500.000',
                'value_class' => null,
            ],
            [
                'label' => 'Đã thu',
                'value' => '1.300.000',
                'value_class' => 'is-positive',
            ],
            [
                'label' => 'Còn lại',
                'value' => '2.200.000',
                'value_class' => 'is-negative',
            ],
        ])
        ->and($panel['sections'])->toBe([
            [
                'key' => 'invoices',
                'title' => 'HÓA ĐƠN ĐIỀU TRỊ',
            ],
            [
                'key' => 'payments',
                'title' => 'DANH SÁCH PHIẾU THU - HOÀN ỨNG',
            ],
        ])
        ->and($panel['sections_by_key']['invoices'])->toMatchArray([
            'key' => 'invoices',
            'title' => 'HÓA ĐƠN ĐIỀU TRỊ',
        ])
        ->and($panel['sections_by_key']['payments'])->toMatchArray([
            'key' => 'payments',
            'title' => 'DANH SÁCH PHIẾU THU - HOÀN ỨNG',
        ]);

    if ($panel['can_create_payment']) {
        expect($panel['primary_payment_action'])->toMatchArray([
            'label' => 'Phiếu thu',
            'url' => route('filament.admin.resources.payments.create', [
                'invoice_id' => $latestInvoice->id,
            ]),
            'style' => 'primary',
            'button_class' => 'crm-btn-primary',
        ])->and($panel['secondary_payment_action'])->toMatchArray([
            'label' => 'Thanh toán',
            'url' => route('filament.admin.resources.payments.create', [
                'invoice_id' => $latestInvoice->id,
            ]),
            'style' => 'outline',
            'button_class' => 'crm-btn-outline',
        ])->and($panel['actions'])->toBe([
            $panel['primary_payment_action'],
            $panel['secondary_payment_action'],
        ]);
    } else {
        expect($panel['primary_payment_action'])->toBeNull()
            ->and($panel['secondary_payment_action'])->toBeNull()
            ->and($panel['actions'])->toBe([]);
    }

    $page = new class extends ViewPatient
    {
        public function forceRecord(Patient $patient): void
        {
            $this->record = $patient;
        }
    };

    actingAs($manager);
    $page->forceRecord($patient->fresh());

    expect($page->getRenderedPaymentBlocksProperty())->toBe([
        [
            'key' => 'invoices',
            'title' => 'HÓA ĐƠN ĐIỀU TRỊ',
            'relation_manager' => \App\Filament\Resources\Patients\RelationManagers\InvoicesRelationManager::class,
        ],
        [
            'key' => 'payments',
            'title' => 'DANH SÁCH PHIẾU THU - HOÀN ỨNG',
            'relation_manager' => \App\Filament\Resources\Patients\RelationManagers\PatientPaymentsRelationManager::class,
        ],
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

    $materialUsages = $service->materialUsages($patient, $admin);
    $factoryOrders = $service->factoryOrders($patient, $admin);
    $materialIssueNotes = $service->materialIssueNotes($patient, $admin);
    $panel = $service->labMaterialsPanelPayload($patient, $admin);

    expect($materialUsages)->toHaveCount(1)
        ->and($materialUsages->pluck('id')->all())->toBe([$usage->id])
        ->and($materialUsages->first()['record']->is($usage))->toBeTrue()
        ->and($materialUsages->first())->toMatchArray([
            'id' => $usage->id,
            'treatment_session_id' => $session->id,
            'treatment_session_label' => '#'.$session->id,
            'material_name' => $material->name,
            'quantity_formatted' => '2',
            'unit_cost_formatted' => '125.000',
            'total_cost_formatted' => '250.000',
            'user_name' => $admin->name,
        ]);

    expect($factoryOrders)->toHaveCount(1)
        ->and($factoryOrders->pluck('id')->all())->toBe([$factoryOrder->id])
        ->and($factoryOrders->first()['record']->is($factoryOrder))->toBeTrue()
        ->and($factoryOrders->first())->toMatchArray([
            'id' => $factoryOrder->id,
            'order_no' => $factoryOrder->order_no,
            'status' => FactoryOrder::STATUS_DRAFT,
            'status_label' => 'Nháp',
            'status_class' => 'is-default',
            'ordered_at_formatted' => $factoryOrder->ordered_at?->format('d/m/Y H:i') ?? '-',
            'due_at_formatted' => $factoryOrder->due_at?->format('d/m/Y H:i') ?? '-',
            'items_count' => 1,
            'items_count_formatted' => '1',
            'detail_url' => route('filament.admin.resources.factory-orders.edit', ['record' => $factoryOrder->id]),
            'detail_action_label' => 'Chi tiết',
            'detail_action_class' => 'text-sm font-medium text-primary-600 hover:underline',
            'detail_action' => [
                'url' => route('filament.admin.resources.factory-orders.edit', ['record' => $factoryOrder->id]),
                'label' => 'Chi tiết',
                'class' => 'text-sm font-medium text-primary-600 hover:underline',
            ],
        ]);

    expect($materialIssueNotes)->toHaveCount(1)
        ->and($materialIssueNotes->pluck('id')->all())->toBe([$issueNote->id])
        ->and($materialIssueNotes->first()['record']->is($issueNote))->toBeTrue()
        ->and($materialIssueNotes->first())->toMatchArray([
            'id' => $issueNote->id,
            'note_no' => $issueNote->note_no,
            'status' => MaterialIssueNote::STATUS_DRAFT,
            'status_label' => 'Nháp',
            'status_class' => 'is-default',
            'issued_at_formatted' => $issueNote->issued_at?->format('d/m/Y H:i') ?? '-',
            'items_count' => 1,
            'items_count_formatted' => '1',
            'total_cost_formatted' => '125.000',
            'detail_url' => route('filament.admin.resources.material-issue-notes.edit', ['record' => $issueNote->id]),
            'detail_action_label' => 'Chi tiết',
            'detail_action_class' => 'text-sm font-medium text-primary-600 hover:underline',
            'detail_action' => [
                'url' => route('filament.admin.resources.material-issue-notes.edit', ['record' => $issueNote->id]),
                'label' => 'Chi tiết',
                'class' => 'text-sm font-medium text-primary-600 hover:underline',
            ],
        ])
        ->and((float) ($materialIssueNotes->first()['total_cost'] ?? 0))->toEqualWithDelta(125000.0, 0.01);

    expect($panel)->toMatchArray([
        'create_factory_order_url' => route('filament.admin.resources.factory-orders.create', [
            'patient_id' => $patient->id,
            'branch_id' => $patient->first_branch_id,
        ]),
        'create_material_issue_note_url' => route('filament.admin.resources.material-issue-notes.create', [
            'patient_id' => $patient->id,
            'branch_id' => $patient->first_branch_id,
        ]),
        'create_treatment_material_url' => route('filament.admin.resources.treatment-materials.create'),
    ])
        ->and($panel['sections'])->toHaveCount(3)
        ->and($panel['sections'][0])->toMatchArray([
            'key' => 'factory_orders',
            'title' => 'Xưởng/Labo',
            'description' => 'Theo dõi lệnh labo theo hồ sơ bệnh nhân và tiến độ giao hàng.',
            'empty_text' => 'Chưa có lệnh labo cho bệnh nhân này.',
            'action' => [
                'label' => 'Tạo lệnh labo',
                'url' => route('filament.admin.resources.factory-orders.create', [
                    'patient_id' => $patient->id,
                    'branch_id' => $patient->first_branch_id,
                ]),
                'style' => 'primary',
                'button_class' => 'crm-btn-primary',
            ],
        ])
        ->and($panel['sections'][1])->toMatchArray([
            'key' => 'material_issue_notes',
            'title' => 'Phiếu xuất vật tư',
            'description' => 'Xuất kho theo bệnh nhân, đồng bộ tồn kho và chi phí vật tư.',
            'empty_text' => 'Chưa có phiếu xuất vật tư cho bệnh nhân này.',
            'action' => [
                'label' => 'Tạo phiếu xuất',
                'url' => route('filament.admin.resources.material-issue-notes.create', [
                    'patient_id' => $patient->id,
                    'branch_id' => $patient->first_branch_id,
                ]),
                'style' => 'primary',
                'button_class' => 'crm-btn-primary',
            ],
        ])
        ->and($panel['sections'][2])->toMatchArray([
            'key' => 'treatment_materials',
            'title' => 'Vật tư đã dùng trong phiên điều trị',
            'description' => 'Đối soát vật tư đã sử dụng trực tiếp theo từng phiên điều trị.',
            'empty_text' => 'Chưa có dữ liệu vật tư cho bệnh nhân này.',
            'action' => [
                'label' => 'Thêm vật tư phiên',
                'url' => route('filament.admin.resources.treatment-materials.create'),
                'style' => 'outline',
                'button_class' => 'crm-btn-outline',
            ],
        ])
        ->and($panel['sections_by_key']['factory_orders'])->toMatchArray([
            'key' => 'factory_orders',
            'title' => 'Xưởng/Labo',
            'empty_text' => 'Chưa có lệnh labo cho bệnh nhân này.',
        ])
        ->and($panel['sections_by_key']['material_issue_notes'])->toMatchArray([
            'key' => 'material_issue_notes',
            'title' => 'Phiếu xuất vật tư',
            'empty_text' => 'Chưa có phiếu xuất vật tư cho bệnh nhân này.',
        ])
        ->and($panel['sections_by_key']['treatment_materials'])->toMatchArray([
            'key' => 'treatment_materials',
            'title' => 'Vật tư đã dùng trong phiên điều trị',
            'empty_text' => 'Chưa có dữ liệu vật tư cho bệnh nhân này.',
        ]);

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
        ->and($page->getMaterialIssueNotesProperty()->pluck('id')->all())->toBe([$issueNote->id])
        ->and($page->getLabMaterialSectionsProperty()['factory_orders'])->toMatchArray([
            'key' => 'factory_orders',
            'title' => 'Xưởng/Labo',
        ])
        ->and($page->getLabMaterialSectionsProperty()['material_issue_notes'])->toMatchArray([
            'key' => 'material_issue_notes',
            'title' => 'Phiếu xuất vật tư',
        ])
        ->and($page->getLabMaterialSectionsProperty()['treatment_materials'])->toMatchArray([
            'key' => 'treatment_materials',
            'title' => 'Vật tư đã dùng trong phiên điều trị',
        ]);
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
            'prescription_code' => sprintf('RX-FORM-%03d', $index),
            'treatment_date' => now()->subDays(7 - $index)->toDateString(),
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
        'prescription_code' => 'RX-FORM-OTHER',
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
    $formsPanel = $service->formsPanelPayload($patient, $admin);

    expect($latestPrescriptions)->toHaveCount(5)
        ->and($latestPrescriptions->pluck('id')->all())->toBe($prescriptionIds->reverse()->take(5)->values()->all())
        ->and($latestPrescriptions->first())->toMatchArray([
            'id' => $prescriptionIds->last(),
            'title' => 'RX-FORM-006 - '.now()->subDays(1)->format('d/m/Y'),
            'print_url' => route('prescriptions.print', $latestPrescriptions->first()['record']),
        ]);

    expect($latestInvoices)->toHaveCount(5)
        ->and($latestInvoices->pluck('id')->all())->toBe($invoiceIds->reverse()->take(5)->values()->all())
        ->and($latestInvoices->first())->toMatchArray([
            'id' => $invoiceIds->last(),
            'title' => '#INV-FORM-006 - '.now()->subDay()->format('d/m/Y'),
            'print_url' => route('invoices.print', $latestInvoices->first()['record']),
        ]);

    expect($formsPanel)->toMatchArray([
        'can_view_prescriptions' => true,
        'can_view_invoices' => true,
        'title' => 'Biểu mẫu & tài liệu',
        'description' => 'Truy cập nhanh biểu mẫu in theo hồ sơ bệnh nhân (đơn thuốc, hóa đơn, phiếu thu).',
    ])
        ->and($formsPanel['prescriptions']->pluck('id')->all())->toBe($prescriptionIds->reverse()->take(5)->values()->all())
        ->and($formsPanel['invoices']->pluck('id')->all())->toBe($invoiceIds->reverse()->take(5)->values()->all())
        ->and($formsPanel['sections'])->toHaveCount(2)
        ->and($formsPanel['sections'][0])->toMatchArray([
            'key' => 'prescriptions',
            'title' => 'Đơn thuốc gần nhất',
            'empty_text' => 'Chưa có đơn thuốc để in.',
            'action_label' => 'In',
        ])
        ->and($formsPanel['sections'][1])->toMatchArray([
            'key' => 'invoices',
            'title' => 'Hóa đơn gần nhất',
            'empty_text' => 'Chưa có hóa đơn để in.',
            'action_label' => 'In',
        ])
        ->and($formsPanel['sections_by_key']['prescriptions'])->toMatchArray([
            'key' => 'prescriptions',
            'title' => 'Đơn thuốc gần nhất',
            'empty_text' => 'Chưa có đơn thuốc để in.',
            'action_label' => 'In',
        ])
        ->and($formsPanel['sections_by_key']['invoices'])->toMatchArray([
            'key' => 'invoices',
            'title' => 'Hóa đơn gần nhất',
            'empty_text' => 'Chưa có hóa đơn để in.',
            'action_label' => 'In',
        ])
        ->and($formsPanel['sections'][0]['links']->all())->toBe([
            ...$formsPanel['sections'][0]['items']->map(fn (array $item): array => [
                'id' => $item['id'],
                'title' => $item['title'],
                'url' => $item['print_url'],
                'action_label' => 'In',
                'target' => '_blank',
            ])->all(),
        ])
        ->and($formsPanel['sections'][1]['links']->all())->toBe([
            ...$formsPanel['sections'][1]['items']->map(fn (array $item): array => [
                'id' => $item['id'],
                'title' => $item['title'],
                'url' => $item['print_url'],
                'action_label' => 'In',
                'target' => '_blank',
            ])->all(),
        ])
        ->and($formsPanel['sections'][0]['items']->pluck('id')->all())->toBe($prescriptionIds->reverse()->take(5)->values()->all())
        ->and($formsPanel['sections'][1]['items']->pluck('id')->all())->toBe($invoiceIds->reverse()->take(5)->values()->all())
        ->and($formsPanel['sections_by_key']['prescriptions']['items']->pluck('id')->all())->toBe($prescriptionIds->reverse()->take(5)->values()->all())
        ->and($formsPanel['sections_by_key']['invoices']['items']->pluck('id')->all())->toBe($invoiceIds->reverse()->take(5)->values()->all())
        ->and($formsPanel['sections_by_key']['prescriptions']['links']->pluck('id')->all())->toBe($prescriptionIds->reverse()->take(5)->values()->all())
        ->and($formsPanel['sections_by_key']['invoices']['links']->pluck('id')->all())->toBe($invoiceIds->reverse()->take(5)->values()->all());

    $page = new class extends ViewPatient
    {
        public function forceRecord(Patient $patient): void
        {
            $this->record = $patient;
        }
    };

    $page->forceRecord($patient->fresh());

    expect($page->getFormsPanelProperty())->toMatchArray([
        'can_view_prescriptions' => true,
        'can_view_invoices' => true,
    ])
        ->and($page->getFormsPanelProperty()['prescriptions']->pluck('id')->all())->toBe($prescriptionIds->reverse()->take(5)->values()->all())
        ->and($page->getFormsPanelProperty()['invoices']->pluck('id')->all())->toBe($invoiceIds->reverse()->take(5)->values()->all())
        ->and($page->getFormsPanelProperty()['sections'])->toHaveCount(2)
        ->and($page->getRenderedFormSectionsProperty())->toHaveCount(2)
        ->and($page->getRenderedFormSectionsProperty()[0]['key'])->toBe('prescriptions')
        ->and($page->getRenderedFormSectionsProperty()[1]['key'])->toBe('invoices');
});

it('builds treatment progress panel actions through the shared read model', function (): void {
    $branch = Branch::factory()->create();

    $admin = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $admin->assignRole('Admin');

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
    ]);

    $workspaceReturnUrl = route('filament.admin.resources.patients.view', [
        'record' => $patient->id,
        'tab' => 'exam-treatment',
    ]);

    $service = app(PatientOverviewReadModelService::class);
    $panel = $service->treatmentProgressPanelPayload(
        $patient,
        $admin,
        $workspaceReturnUrl,
        collect(),
        collect(),
    );

    expect($panel)->toMatchArray([
        'create_treatment_session_url' => route('filament.admin.resources.treatment-sessions.create', [
            'patient_id' => $patient->id,
            'return_url' => $workspaceReturnUrl,
        ]),
        'sessions_count' => 0,
        'days_count' => 0,
        'has_day_summaries' => false,
        'total_amount' => 0.0,
        'total_amount_formatted' => '0',
        'summary_badge' => '0 ngày · 0 phiên',
        'total_amount_text' => 'Tổng chi phí phiên: 0đ',
        'primary_action' => [
            'label' => 'Thêm ngày điều trị',
            'url' => route('filament.admin.resources.treatment-sessions.create', [
                'patient_id' => $patient->id,
                'return_url' => $workspaceReturnUrl,
            ]),
            'button_class' => 'crm-btn-primary',
        ],
    ]);

    $page = new class extends ViewPatient
    {
        public function forceRecord(Patient $patient): void
        {
            $this->record = $patient;
        }

        public function forceWorkspaceReturnUrl(string $url): void
        {
            $this->workspaceReturnUrl = $url;
        }
    };

    $page->forceRecord($patient->fresh());
    $page->forceWorkspaceReturnUrl($workspaceReturnUrl);
    actingAs($admin);

    expect($page->getTreatmentProgressPanelProperty())->toMatchArray($panel);
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

it('builds patient workspace header actions through the shared read model', function (): void {
    $branch = Branch::factory()->create();

    $admin = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $admin->assignRole('Admin');

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
    ]);

    $workspaceReturnUrl = route('filament.admin.resources.patients.view', [
        'record' => $patient->id,
        'tab' => 'exam-treatment',
    ]);

    $service = app(PatientOverviewReadModelService::class);

    $createActions = $service->workspaceHeaderActions($patient, $admin, $workspaceReturnUrl);

    expect($createActions['create_treatment_plan'])->toMatchArray([
        'label' => 'Tạo kế hoạch điều trị',
        'url' => route('filament.admin.resources.treatment-plans.create', [
            'patient_id' => $patient->id,
            'return_url' => $workspaceReturnUrl,
        ]),
        'visible' => true,
        'icon' => 'heroicon-o-clipboard-document-list',
        'color' => 'success',
        'open_in_new_tab' => true,
    ])->and($createActions['create_invoice'])->toMatchArray([
        'label' => 'Tạo hóa đơn',
        'url' => route('filament.admin.resources.invoices.create', [
            'patient_id' => $patient->id,
        ]),
        'visible' => true,
        'icon' => 'heroicon-o-document-text',
        'color' => 'warning',
        'open_in_new_tab' => true,
    ])->and($createActions['create_appointment'])->toMatchArray([
        'label' => 'Đặt lịch hẹn',
        'url' => route('filament.admin.resources.appointments.create', [
            'patient_id' => $patient->id,
        ]),
        'visible' => true,
        'icon' => 'heroicon-o-calendar',
        'color' => 'info',
        'open_in_new_tab' => true,
    ])->and($createActions['medical_record'])->toMatchArray([
        'label' => 'Tạo bệnh án điện tử',
        'url' => route('filament.admin.resources.patient-medical-records.create', [
            'patient_id' => $patient->id,
        ]),
        'visible' => true,
        'mode' => 'create',
        'record_id' => null,
        'icon' => 'heroicon-o-clipboard-document-check',
        'color' => 'primary',
        'open_in_new_tab' => false,
    ]);

    $medicalRecord = PatientMedicalRecord::query()->create([
        'patient_id' => $patient->id,
        'updated_by' => $admin->id,
    ]);

    $editActions = $service->workspaceHeaderActions($patient->fresh(), $admin, $workspaceReturnUrl);

    expect($editActions['medical_record'])->toMatchArray([
        'label' => 'Mở bệnh án điện tử',
        'url' => route('filament.admin.resources.patient-medical-records.edit', [
            'record' => $medicalRecord->id,
            'patient_id' => $patient->id,
        ]),
        'visible' => true,
        'mode' => 'edit',
        'record_id' => $medicalRecord->id,
        'icon' => 'heroicon-o-clipboard-document-check',
        'color' => 'primary',
        'open_in_new_tab' => false,
    ]);
});
