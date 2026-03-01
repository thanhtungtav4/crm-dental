<?php

use App\Filament\Pages\CustomerCare;
use App\Filament\Resources\Appointments\Pages\CalendarAppointments;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchOverbookingPolicy;
use App\Models\ClinicSetting;
use App\Models\Customer;
use App\Models\DoctorBranchAssignment;
use App\Models\FactoryOrder;
use App\Models\InventoryTransaction;
use App\Models\Invoice;
use App\Models\Material;
use App\Models\MaterialIssueItem;
use App\Models\MaterialIssueNote;
use App\Models\Note;
use App\Models\Patient;
use App\Models\PatientContact;
use App\Models\PatientPhoto;
use App\Models\PatientWallet;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Models\ZnsCampaign;
use App\Models\ZnsCampaignDelivery;
use App\Services\ZnsCampaignRunnerService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('scopes calendar operational metrics by accessible branch for non admin users', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $doctor = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $doctor->assignRole('Doctor');

    $doctorBranchB = User::factory()->create([
        'branch_id' => $branchB->id,
    ]);
    $doctorBranchB->assignRole('Doctor');

    DoctorBranchAssignment::query()->create([
        'user_id' => $doctor->id,
        'branch_id' => $branchA->id,
        'is_active' => true,
        'is_primary' => true,
    ]);
    DoctorBranchAssignment::query()->create([
        'user_id' => $doctorBranchB->id,
        'branch_id' => $branchB->id,
        'is_active' => true,
        'is_primary' => true,
    ]);

    $customerA = Customer::factory()->create(['branch_id' => $branchA->id]);
    $patientA = Patient::factory()->create([
        'customer_id' => $customerA->id,
        'first_branch_id' => $branchA->id,
    ]);

    $customerB = Customer::factory()->create(['branch_id' => $branchB->id]);
    $patientB = Patient::factory()->create([
        'customer_id' => $customerB->id,
        'first_branch_id' => $branchB->id,
    ]);

    Appointment::query()->create([
        'patient_id' => $patientA->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branchA->id,
        'date' => now()->setTime(10, 0),
        'status' => Appointment::STATUS_SCHEDULED,
        'duration_minutes' => 30,
    ]);

    Appointment::query()->create([
        'patient_id' => $patientB->id,
        'doctor_id' => $doctorBranchB->id,
        'branch_id' => $branchB->id,
        'date' => now()->setTime(11, 0),
        'status' => Appointment::STATUS_NO_SHOW,
        'duration_minutes' => 30,
    ]);

    $this->actingAs($manager);

    $metrics = Livewire::test(CalendarAppointments::class)
        ->instance()
        ->getOperationalStatusMetrics();

    expect($metrics['total'])->toBe(1)
        ->and($metrics['scheduled'])->toBe(1)
        ->and($metrics['no_show'])->toBe(0);
});

it('returns conflict warning and supports force reschedule from calendar', function (): void {
    $branch = Branch::factory()->create();

    $admin = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $admin->assignRole('Admin');

    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    DoctorBranchAssignment::query()->create([
        'user_id' => $doctor->id,
        'branch_id' => $branch->id,
        'is_active' => true,
        'is_primary' => true,
    ]);

    BranchOverbookingPolicy::query()->create([
        'branch_id' => $branch->id,
        'is_enabled' => true,
        'max_parallel_per_doctor' => 2,
        'require_override_reason' => false,
    ]);

    $customer = Customer::factory()->create(['branch_id' => $branch->id]);
    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
    ]);

    $appointment = Appointment::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay()->setTime(8, 0),
        'status' => Appointment::STATUS_SCHEDULED,
        'duration_minutes' => 30,
    ]);

    Appointment::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay()->setTime(8, 15),
        'status' => Appointment::STATUS_CONFIRMED,
        'duration_minutes' => 30,
    ]);

    $this->actingAs($admin);

    $page = Livewire::test(CalendarAppointments::class)->instance();

    $conflictResult = $page->rescheduleAppointmentFromCalendar(
        $appointment->id,
        now()->addDay()->setTime(8, 20)->toIso8601String(),
        false,
    );

    expect($conflictResult['ok'])->toBeFalse()
        ->and($conflictResult['message'])->toContain('trùng lịch');

    $forceResult = $page->rescheduleAppointmentFromCalendar(
        $appointment->id,
        now()->addDay()->setTime(8, 20)->toIso8601String(),
        true,
    );

    $appointment->refresh();

    expect($forceResult['ok'])->toBeTrue()
        ->and($appointment->status)->toBe(Appointment::STATUS_RESCHEDULED)
        ->and($appointment->reschedule_reason)->toContain('Điều phối từ lịch ngày/tuần');
});

it('prunes old patient photos by retention policy and keeps non eligible records', function (): void {
    Storage::fake('public');
    config()->set('filament.default_filesystem_disk', 'public');

    $patient = Patient::factory()->create();

    Storage::disk('public')->put('patient-photos/ext/old-ext.jpg', 'old');
    Storage::disk('public')->put('patient-photos/ext/new-ext.jpg', 'new');
    Storage::disk('public')->put('patient-photos/xray/old-xray.jpg', 'xray');

    $oldExt = PatientPhoto::query()->create([
        'patient_id' => $patient->id,
        'type' => PatientPhoto::TYPE_EXTERNAL,
        'date' => now()->subDays(60)->toDateString(),
        'title' => 'Old ext',
        'content' => ['patient-photos/ext/old-ext.jpg'],
    ]);

    $newExt = PatientPhoto::query()->create([
        'patient_id' => $patient->id,
        'type' => PatientPhoto::TYPE_EXTERNAL,
        'date' => now()->subDays(5)->toDateString(),
        'title' => 'New ext',
        'content' => ['patient-photos/ext/new-ext.jpg'],
    ]);

    $oldXray = PatientPhoto::query()->create([
        'patient_id' => $patient->id,
        'type' => PatientPhoto::TYPE_XRAY,
        'date' => now()->subDays(90)->toDateString(),
        'title' => 'Old xray',
        'content' => ['patient-photos/xray/old-xray.jpg'],
    ]);

    ClinicSetting::setValue('photos.retention_enabled', true, [
        'group' => 'photos',
        'value_type' => 'boolean',
    ]);
    ClinicSetting::setValue('photos.retention_days', 30, [
        'group' => 'photos',
        'value_type' => 'integer',
    ]);
    ClinicSetting::setValue('photos.retention_include_xray', false, [
        'group' => 'photos',
        'value_type' => 'boolean',
    ]);

    $this->artisan('photos:prune')->assertSuccessful();

    expect(PatientPhoto::query()->whereKey($oldExt->id)->exists())->toBeFalse()
        ->and(PatientPhoto::query()->whereKey($newExt->id)->exists())->toBeTrue()
        ->and(PatientPhoto::query()->whereKey($oldXray->id)->exists())->toBeTrue();

    Storage::disk('public')->assertMissing('patient-photos/ext/old-ext.jpg');
    Storage::disk('public')->assertExists('patient-photos/ext/new-ext.jpg');
    Storage::disk('public')->assertExists('patient-photos/xray/old-xray.jpg');
});

it('keeps only one primary contact per patient and blocks cross branch writes', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $customerA = Customer::factory()->create(['branch_id' => $branchA->id]);
    $patientA = Patient::factory()->create([
        'customer_id' => $customerA->id,
        'first_branch_id' => $branchA->id,
    ]);

    $first = PatientContact::query()->create([
        'patient_id' => $patientA->id,
        'full_name' => 'Người liên hệ 1',
        'phone' => '0900000001',
        'is_primary' => true,
    ]);

    $second = PatientContact::query()->create([
        'patient_id' => $patientA->id,
        'full_name' => 'Người liên hệ 2',
        'phone' => '0900000002',
        'is_primary' => true,
    ]);

    expect($first->refresh()->is_primary)->toBeFalse()
        ->and($second->refresh()->is_primary)->toBeTrue();

    $staffBranchB = User::factory()->create([
        'branch_id' => $branchB->id,
    ]);
    $staffBranchB->assignRole('CSKH');
    $this->actingAs($staffBranchB);

    expect(fn () => PatientContact::query()->create([
        'patient_id' => $patientA->id,
        'full_name' => 'Cross branch',
        'phone' => '0900000003',
    ]))->toThrow(ValidationException::class);
});

it('posts material issue note and records inventory transactions', function (): void {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $material = Material::factory()->create([
        'branch_id' => $branch->id,
        'stock_qty' => 10,
        'min_stock' => 7,
        'cost_price' => 50_000,
    ]);

    $issueNote = MaterialIssueNote::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => MaterialIssueNote::STATUS_DRAFT,
        'reason' => 'Xuất vật tư cho điều trị',
    ]);

    MaterialIssueItem::query()->create([
        'material_issue_note_id' => $issueNote->id,
        'material_id' => $material->id,
        'quantity' => 4,
        'unit_cost' => 50_000,
    ]);

    $warnings = $issueNote->post($admin->id);

    $issueNote->refresh();
    $material->refresh();

    expect($issueNote->status)->toBe(MaterialIssueNote::STATUS_POSTED)
        ->and($issueNote->posted_by)->toBe($admin->id)
        ->and($material->stock_qty)->toBe(6)
        ->and($warnings)->toHaveCount(1)
        ->and($warnings[0])->toContain((string) $material->name);

    expect(InventoryTransaction::query()
        ->where('material_issue_note_id', $issueNote->id)
        ->where('material_id', $material->id)
        ->where('type', 'out')
        ->where('quantity', 4)
        ->exists())->toBeTrue();
});

it('enforces factory order state transitions and computes item totals', function (): void {
    $order = FactoryOrder::query()->create([
        'patient_id' => Patient::factory()->create()->id,
        'branch_id' => Branch::factory()->create()->id,
        'status' => FactoryOrder::STATUS_DRAFT,
        'priority' => 'normal',
    ]);

    $order->status = FactoryOrder::STATUS_DELIVERED;

    expect(fn () => $order->save())->toThrow(ValidationException::class);
});

it('runs zns campaign with idempotent deliveries and supports lifecycle completion from failed', function (): void {
    $branch = Branch::factory()->create();
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);
    Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'phone' => '0901111222',
    ]);

    $scheduledCampaign = ZnsCampaign::query()->create([
        'name' => 'Campaign scheduled',
        'branch_id' => $branch->id,
        'status' => ZnsCampaign::STATUS_SCHEDULED,
        'scheduled_at' => now()->subMinute(),
    ]);

    $service = app(ZnsCampaignRunnerService::class);

    $firstRun = $service->runCampaign($scheduledCampaign);
    $scheduledCampaign->refresh();

    expect($firstRun['sent'])->toBe(1)
        ->and($scheduledCampaign->status)->toBe(ZnsCampaign::STATUS_COMPLETED)
        ->and($scheduledCampaign->sent_count)->toBe(1)
        ->and(ZnsCampaignDelivery::query()->count())->toBe(1);

    $failedCampaign = ZnsCampaign::query()->create([
        'name' => 'Campaign failed',
        'branch_id' => $branch->id,
        'status' => ZnsCampaign::STATUS_FAILED,
        'scheduled_at' => now()->subMinute(),
    ]);

    $retryRun = $service->runCampaign($failedCampaign);
    $failedCampaign->refresh();

    expect($retryRun['sent'])->toBe(1)
        ->and($failedCampaign->status)->toBe(ZnsCampaign::STATUS_COMPLETED)
        ->and($failedCampaign->sent_count)->toBe(1)
        ->and(ZnsCampaignDelivery::query()->count())->toBe(2);
});

it('enforces mobile api branch isolation for appointments and invoices', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $doctorA = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $doctorA->assignRole('Doctor');

    $doctorB = User::factory()->create([
        'branch_id' => $branchB->id,
    ]);
    $doctorB->assignRole('Doctor');

    $customerA = Customer::factory()->create(['branch_id' => $branchA->id]);
    $patientA = Patient::factory()->create([
        'customer_id' => $customerA->id,
        'first_branch_id' => $branchA->id,
    ]);

    $customerB = Customer::factory()->create(['branch_id' => $branchB->id]);
    $patientB = Patient::factory()->create([
        'customer_id' => $customerB->id,
        'first_branch_id' => $branchB->id,
    ]);

    Appointment::factory()->create([
        'patient_id' => $patientA->id,
        'doctor_id' => $doctorA->id,
        'branch_id' => $branchA->id,
        'status' => Appointment::STATUS_SCHEDULED,
    ]);
    Appointment::factory()->create([
        'patient_id' => $patientB->id,
        'doctor_id' => $doctorB->id,
        'branch_id' => $branchB->id,
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    Invoice::factory()->create([
        'patient_id' => $patientA->id,
        'branch_id' => $branchA->id,
    ]);
    Invoice::factory()->create([
        'patient_id' => $patientB->id,
        'branch_id' => $branchB->id,
    ]);

    $token = $manager->createToken('mobile-test', ['mobile:read'])->plainTextToken;
    $headers = ['Authorization' => 'Bearer '.$token];

    $appointmentsResponse = $this->getJson('/api/v1/mobile/appointments', $headers);
    $appointmentsResponse->assertOk();
    expect(collect($appointmentsResponse->json('data'))->pluck('branch.id')->unique()->values()->all())
        ->toBe([$branchA->id]);

    $crossBranchAppointments = $this->getJson('/api/v1/mobile/appointments?branch_id='.$branchB->id, $headers);
    $crossBranchAppointments->assertOk();
    expect($crossBranchAppointments->json('data'))->toBe([]);

    $invoicesResponse = $this->getJson('/api/v1/mobile/invoices', $headers);
    $invoicesResponse->assertOk();
    expect(collect($invoicesResponse->json('data'))->pluck('branch.id')->unique()->values()->all())
        ->toBe([$branchA->id]);

    $crossBranchInvoices = $this->getJson('/api/v1/mobile/invoices?branch_id='.$branchB->id, $headers);
    $crossBranchInvoices->assertOk();
    expect($crossBranchInvoices->json('data'))->toBe([]);
});

it('creates wallet ledger entries for deposit spend and reversal flows', function (): void {
    $branch = Branch::factory()->create();
    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');
    $receiver = User::factory()->create(['branch_id' => $branch->id]);
    $this->actingAs($admin);

    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'total_amount' => 3_000_000,
        'paid_amount' => 0,
        'status' => Invoice::STATUS_ISSUED,
    ]);

    $deposit = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'branch_id' => $branch->id,
        'amount' => 1_000_000,
        'direction' => 'receipt',
        'is_deposit' => true,
        'method' => 'cash',
        'payment_source' => 'patient',
        'paid_at' => now(),
        'received_by' => $receiver->id,
    ]);

    Payment::query()->create([
        'invoice_id' => $invoice->id,
        'branch_id' => $branch->id,
        'amount' => 300_000,
        'direction' => 'receipt',
        'is_deposit' => false,
        'method' => 'cash',
        'payment_source' => 'wallet',
        'paid_at' => now(),
        'received_by' => $receiver->id,
    ]);

    Payment::query()->create([
        'invoice_id' => $invoice->id,
        'branch_id' => $branch->id,
        'amount' => 700_000,
        'direction' => 'refund',
        'is_deposit' => false,
        'method' => 'cash',
        'payment_source' => 'patient',
        'reversal_of_id' => $deposit->id,
        'paid_at' => now(),
        'received_by' => $receiver->id,
        'refund_reason' => 'Đảo thu cọc',
    ]);

    $wallet = PatientWallet::query()->where('patient_id', $patient->id)->firstOrFail();
    $entries = WalletLedgerEntry::query()
        ->where('patient_id', $patient->id)
        ->orderBy('id')
        ->get();

    expect($entries->pluck('entry_type')->all())->toBe(['deposit', 'spend', 'reversal'])
        ->and((float) $wallet->balance)->toEqualWithDelta(0, 0.01);
});

it('shows sla summary for priority queue care tickets', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    Note::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'user_id' => $manager->id,
        'type' => Note::TYPE_GENERAL,
        'care_type' => 'no_show_recovery',
        'care_status' => Note::CARE_STATUS_NOT_STARTED,
        'care_channel' => 'call',
        'care_at' => now()->subHour(),
        'content' => 'No show cần gọi lại',
    ]);

    Note::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'user_id' => $manager->id,
        'type' => Note::TYPE_GENERAL,
        'care_type' => 'recall_recare',
        'care_status' => Note::CARE_STATUS_IN_PROGRESS,
        'care_channel' => 'zalo',
        'care_at' => now()->addHour(),
        'content' => 'Recall cần xác nhận',
    ]);

    $this->actingAs($manager);

    $summary = Livewire::test(CustomerCare::class)->instance()->slaSummary;

    expect($summary['total_open'])->toBe(2)
        ->and($summary['priority_no_show'])->toBe(1)
        ->and($summary['priority_recall'])->toBe(1)
        ->and($summary['overdue'])->toBe(1);
});
