<?php

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\Consent;
use App\Models\Customer;
use App\Models\InsuranceClaim;
use App\Models\Invoice;
use App\Models\MasterDataSyncLog;
use App\Models\MasterPatientDuplicate;
use App\Models\MasterPatientIdentity;
use App\Models\Material;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PlanItem;
use App\Models\RecallRule;
use App\Models\ReportSnapshot;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('creates operational KPI snapshot with lineage payload', function () {
    $branch = Branch::factory()->create();

    $this->artisan('reports:snapshot-operational-kpis', [
        '--date' => now()->toDateString(),
        '--branch_id' => $branch->id,
    ])->assertSuccessful();

    $snapshot = ReportSnapshot::query()
        ->where('snapshot_key', 'operational_kpi_pack')
        ->whereDate('snapshot_date', now()->toDateString())
        ->where('branch_id', $branch->id)
        ->first();

    expect($snapshot)->not->toBeNull()
        ->and($snapshot->status)->toBe(ReportSnapshot::STATUS_SUCCESS)
        ->and($snapshot->payload)->toBeArray()
        ->and($snapshot->payload)->toHaveKey('booking_count')
        ->and($snapshot->lineage)->toBeArray()
        ->and($snapshot->lineage)->toHaveKey('window');
});

it('renders operational kpi pack report page from snapshot data', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    ReportSnapshot::query()->create([
        'snapshot_key' => 'operational_kpi_pack',
        'snapshot_date' => now()->toDateString(),
        'status' => ReportSnapshot::STATUS_SUCCESS,
        'sla_status' => ReportSnapshot::SLA_ON_TIME,
        'generated_at' => now(),
        'sla_due_at' => now()->addHour(),
        'payload' => [
            'booking_to_visit_rate' => 72.5,
            'no_show_rate' => 7.5,
            'treatment_acceptance_rate' => 61.25,
            'chair_utilization_rate' => 84.33,
            'recall_rate' => 40.1,
            'revenue_per_patient' => 1200000,
            'ltv_patient' => 4500000,
        ],
        'lineage' => [
            'generated_at' => now()->toIso8601String(),
            'window' => [
                'from' => now()->startOfDay()->toDateTimeString(),
                'to' => now()->endOfDay()->toDateTimeString(),
            ],
            'sources' => [],
        ],
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin);

    $this->get(route('filament.admin.pages.operational-kpi-pack'))
        ->assertSuccessful()
        ->assertSee('KPI vận hành nha khoa')
        ->assertSee('72.50%');
});

it('marks missing snapshot sla when no snapshot exists', function () {
    $this->artisan('reports:check-snapshot-sla', [
        '--date' => now()->toDateString(),
        '--key' => 'missing_ops_snapshot',
    ])->assertSuccessful();

    $snapshot = ReportSnapshot::query()
        ->where('snapshot_key', 'missing_ops_snapshot')
        ->whereDate('snapshot_date', now()->toDateString())
        ->first();

    expect($snapshot)->not->toBeNull()
        ->and($snapshot->sla_status)->toBe(ReportSnapshot::SLA_MISSING)
        ->and($snapshot->status)->toBe(ReportSnapshot::STATUS_FAILED);
});

it('marks snapshot as late when generated after sla due', function () {
    ReportSnapshot::query()->create([
        'snapshot_key' => 'operational_kpi_pack',
        'snapshot_date' => now()->toDateString(),
        'status' => ReportSnapshot::STATUS_SUCCESS,
        'sla_status' => ReportSnapshot::SLA_ON_TIME,
        'generated_at' => now(),
        'sla_due_at' => now()->subHour(),
        'payload' => [],
        'lineage' => ['generated_at' => now()->toIso8601String()],
    ]);

    $this->artisan('reports:check-snapshot-sla', [
        '--date' => now()->toDateString(),
        '--key' => 'operational_kpi_pack',
    ])->assertSuccessful();

    $snapshot = ReportSnapshot::query()
        ->where('snapshot_key', 'operational_kpi_pack')
        ->whereDate('snapshot_date', now()->toDateString())
        ->first();

    expect($snapshot)->not->toBeNull()
        ->and($snapshot->sla_status)->toBe(ReportSnapshot::SLA_LATE);
});

it('syncs materials between branches and records sync logs', function () {
    $sourceBranch = Branch::factory()->create();
    $targetBranch = Branch::factory()->create();

    Material::query()->create([
        'branch_id' => $sourceBranch->id,
        'name' => 'Composite A2',
        'sku' => 'MAT-COMPOSITE-A2',
        'unit' => 'tube',
        'stock_qty' => 25,
        'sale_price' => 180000,
        'cost_price' => 90000,
        'min_stock' => 5,
    ]);

    $this->artisan('master-data:sync', [
        'source_branch_id' => $sourceBranch->id,
        'target_branch_ids' => [$targetBranch->id],
    ])->assertSuccessful();

    $syncedMaterial = Material::query()
        ->where('branch_id', $targetBranch->id)
        ->where('sku', 'MAT-COMPOSITE-A2')
        ->first();

    $syncLog = MasterDataSyncLog::query()
        ->where('source_branch_id', $sourceBranch->id)
        ->where('target_branch_id', $targetBranch->id)
        ->latest('id')
        ->first();

    expect($syncedMaterial)->not->toBeNull()
        ->and((int) $syncedMaterial->stock_qty)->toBe(0)
        ->and($syncLog)->not->toBeNull()
        ->and($syncLog->status)->toBe(MasterDataSyncLog::STATUS_SUCCESS);

    $auditLog = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_MASTER_DATA_SYNC)
        ->where('action', AuditLog::ACTION_SYNC)
        ->first();

    expect($auditLog)->not->toBeNull();
});

it('applies conflict policy when syncing master data', function () {
    $sourceBranch = Branch::factory()->create();
    $targetBranch = Branch::factory()->create();

    Material::query()->create([
        'branch_id' => $sourceBranch->id,
        'name' => 'Bonding Agent',
        'sku' => 'MAT-BOND-01',
        'unit' => 'chai',
        'stock_qty' => 20,
        'sale_price' => 250000,
        'cost_price' => 120000,
        'min_stock' => 3,
    ]);

    $targetMaterial = Material::query()->create([
        'branch_id' => $targetBranch->id,
        'name' => 'Bonding Agent',
        'sku' => 'MAT-BOND-01',
        'unit' => 'chai',
        'stock_qty' => 5,
        'sale_price' => 190000,
        'cost_price' => 100000,
        'min_stock' => 2,
    ]);

    $this->artisan('master-data:sync', [
        'source_branch_id' => $sourceBranch->id,
        'target_branch_ids' => [$targetBranch->id],
        '--conflict-policy' => 'skip',
    ])->assertSuccessful();

    $skipLog = MasterDataSyncLog::query()
        ->where('source_branch_id', $sourceBranch->id)
        ->where('target_branch_id', $targetBranch->id)
        ->latest('id')
        ->first();

    expect($targetMaterial->fresh()->sale_price)->toEqualWithDelta(190000.00, 0.01)
        ->and($skipLog)->not->toBeNull()
        ->and($skipLog->status)->toBe(MasterDataSyncLog::STATUS_PARTIAL)
        ->and((int) $skipLog->conflict_count)->toBe(1)
        ->and(data_get($skipLog->metadata, 'conflict_policy'))->toBe('skip');

    $this->artisan('master-data:sync', [
        'source_branch_id' => $sourceBranch->id,
        'target_branch_ids' => [$targetBranch->id],
        '--conflict-policy' => 'overwrite',
    ])->assertSuccessful();

    $overwriteLog = MasterDataSyncLog::query()
        ->where('source_branch_id', $sourceBranch->id)
        ->where('target_branch_id', $targetBranch->id)
        ->latest('id')
        ->first();

    expect($targetMaterial->fresh()->sale_price)->toEqualWithDelta(250000.00, 0.01)
        ->and($overwriteLog)->not->toBeNull()
        ->and((int) $overwriteLog->conflict_count)->toBe(0)
        ->and(data_get($overwriteLog->metadata, 'conflict_policy'))->toBe('overwrite');
});

it('syncs expanded multi-entity master data pack and keeps idempotency', function () {
    $sourceBranch = Branch::factory()->create();
    $targetBranch = Branch::factory()->create();

    $category = ServiceCategory::query()->create([
        'name' => 'Phẫu thuật',
        'code' => 'CAT-SURGERY',
        'active' => true,
    ]);

    $sourceService = Service::query()->create([
        'category_id' => $category->id,
        'name' => 'Cấy implant',
        'code' => 'SRV-IMPLANT',
        'description' => 'Dịch vụ implant chuẩn',
        'unit' => 'răng',
        'duration_minutes' => 90,
        'tooth_specific' => true,
        'requires_consent' => true,
        'doctor_commission_rate' => 12.5,
        'branch_id' => $sourceBranch->id,
        'default_price' => 13000000,
        'vat_rate' => 8,
        'active' => true,
    ]);

    $targetService = Service::query()->create([
        'category_id' => $category->id,
        'name' => 'Cấy implant',
        'code' => 'SRV-IMPLANT',
        'branch_id' => $targetBranch->id,
        'duration_minutes' => 45,
        'requires_consent' => false,
        'default_price' => 9000000,
        'vat_rate' => 0,
        'active' => true,
    ]);

    RecallRule::query()->create([
        'branch_id' => $sourceBranch->id,
        'service_id' => $sourceService->id,
        'name' => 'Recall implant 6 tháng',
        'offset_days' => 180,
        'care_channel' => 'call',
        'priority' => 2,
        'is_active' => true,
        'rules' => ['phase' => 'maintenance'],
    ]);

    ClinicSetting::query()->create([
        'group' => 'consent',
        'key' => 'consent.template.'.$sourceBranch->id.'.implant_v1',
        'label' => 'Consent implant v1',
        'value' => 'Mẫu consent implant',
        'value_type' => 'text',
        'is_secret' => false,
        'is_active' => true,
        'sort_order' => 10,
    ]);

    $entities = 'service_categories,service_catalog,price_book,recall_rules,consent_templates';

    $this->artisan('master-data:sync', [
        'source_branch_id' => $sourceBranch->id,
        'target_branch_ids' => [$targetBranch->id],
        '--entity' => $entities,
        '--conflict-policy' => 'overwrite',
    ])->assertSuccessful();

    $syncedService = Service::query()
        ->where('code', 'SRV-IMPLANT')
        ->where('branch_id', $targetBranch->id)
        ->first();

    $syncedRecallRule = RecallRule::query()
        ->where('branch_id', $targetBranch->id)
        ->where('name', 'Recall implant 6 tháng')
        ->first();

    $syncedTemplate = ClinicSetting::query()
        ->where('key', 'consent.template.'.$targetBranch->id.'.implant_v1')
        ->first();

    expect($syncedService)->not->toBeNull()
        ->and((int) $syncedService->duration_minutes)->toBe(90)
        ->and((bool) $syncedService->requires_consent)->toBeTrue()
        ->and((float) $syncedService->default_price)->toEqualWithDelta(13000000.00, 0.01)
        ->and((int) $syncedService->vat_rate)->toBe(8)
        ->and($syncedRecallRule)->not->toBeNull()
        ->and((int) $syncedRecallRule->offset_days)->toBe(180)
        ->and($syncedTemplate)->not->toBeNull()
        ->and((string) $syncedTemplate->value)->toBe('Mẫu consent implant');

    $this->artisan('master-data:sync', [
        'source_branch_id' => $sourceBranch->id,
        'target_branch_ids' => [$targetBranch->id],
        '--entity' => $entities,
        '--conflict-policy' => 'overwrite',
    ])->assertSuccessful();

    $loggedEntities = MasterDataSyncLog::query()
        ->where('source_branch_id', $sourceBranch->id)
        ->where('target_branch_id', $targetBranch->id)
        ->pluck('entity')
        ->unique()
        ->values()
        ->all();

    expect($loggedEntities)->toContain(
        'service_categories',
        'service_catalog',
        'price_book',
        'recall_rules',
        'consent_templates',
    );

    expect(Service::query()
        ->where('code', 'SRV-IMPLANT')
        ->where('branch_id', $targetBranch->id)
        ->count())->toBe(1);

    expect(RecallRule::query()
        ->where('branch_id', $targetBranch->id)
        ->where('name', 'Recall implant 6 tháng')
        ->count())->toBe(1);

    expect($targetService->fresh()?->id)->toBe($syncedService?->id);
});

it('syncs mpi identities and detects cross branch duplicates', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $customerA = Customer::factory()->create([
        'branch_id' => $branchA->id,
        'phone' => '0912345678',
        'email' => 'mpi-a@example.test',
    ]);

    $customerB = Customer::factory()->create([
        'branch_id' => $branchB->id,
        'phone' => '+84 912345678',
        'email' => 'mpi-b@example.test',
    ]);

    $patientA = Patient::factory()->create([
        'customer_id' => $customerA->id,
        'first_branch_id' => $branchA->id,
        'phone' => '0912345678',
        'email' => 'mpi-a@example.test',
        'cccd' => '079203001234',
    ]);

    $patientB = Patient::factory()->create([
        'customer_id' => $customerB->id,
        'first_branch_id' => $branchB->id,
        'phone' => '+84 912345678',
        'email' => 'mpi-b@example.test',
    ]);

    $this->artisan('mpi:sync', [
        '--show-duplicates' => true,
    ])->assertSuccessful();

    $duplicateHash = MasterPatientIdentity::query()
        ->where('identity_type', MasterPatientIdentity::TYPE_PHONE)
        ->groupBy('identity_hash')
        ->havingRaw('COUNT(DISTINCT patient_id) > 1')
        ->value('identity_hash');

    expect($duplicateHash)->not->toBeNull()
        ->and(MasterPatientIdentity::query()->where('patient_id', $patientA->id)->exists())->toBeTrue()
        ->and(MasterPatientIdentity::query()->where('patient_id', $patientB->id)->exists())->toBeTrue();

    $dedupeLog = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_MASTER_PATIENT_INDEX)
        ->where('action', AuditLog::ACTION_DEDUPE)
        ->where('entity_id', $patientB->id)
        ->first();

    $duplicateCase = MasterPatientDuplicate::query()
        ->where('identity_type', MasterPatientIdentity::TYPE_PHONE)
        ->where('status', MasterPatientDuplicate::STATUS_OPEN)
        ->first();

    expect($dedupeLog)->not->toBeNull();
    expect($duplicateCase)->not->toBeNull()
        ->and($duplicateCase->matched_patient_ids)->toContain($patientA->id, $patientB->id);
});

it('enforces action level rbac for appointment override payment reversal and plan approval', function () {
    $unauthorizedUser = User::factory()->create();

    $appointment = Appointment::factory()->create([
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    $invoice = Invoice::factory()->create([
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 300000,
        'paid_amount' => 0,
    ]);

    $plan = TreatmentPlan::factory()->create([
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);

    $planItem = PlanItem::query()->create([
        'treatment_plan_id' => $plan->id,
        'name' => 'Hạng mục cần duyệt',
        'status' => PlanItem::STATUS_PENDING,
        'approval_status' => PlanItem::APPROVAL_PROPOSED,
    ]);

    $this->actingAs($unauthorizedUser);

    $unauthorizedUser->update([
        'branch_id' => $appointment->branch_id,
    ]);

    expect(fn () => $appointment->applyOperationalOverride(
        Appointment::OVERRIDE_EMERGENCY,
        'Đau cấp cần chen lịch',
        $unauthorizedUser->id,
    ))->toThrow(ValidationException::class, 'không có quyền override vận hành lịch hẹn');

    $unauthorizedUser->update([
        'branch_id' => $invoice->resolveBranchId(),
    ]);

    expect(fn () => $invoice->recordPayment(
        amount: 100000,
        method: 'cash',
        notes: 'Hoàn nhầm',
        paidAt: now(),
        direction: 'refund',
    ))->toThrow(ValidationException::class, 'không có quyền thực hiện hoàn tiền');

    $unauthorizedUser->update([
        'branch_id' => $plan->branch_id,
    ]);

    expect(fn () => $planItem->update([
        'approval_status' => PlanItem::APPROVAL_APPROVED,
    ]))->toThrow(ValidationException::class, 'không có quyền thay đổi trạng thái duyệt');
});

it('blocks direct refund payment and insurance decision without action permission', function () {
    $unauthorizedUser = User::factory()->create();

    $invoice = Invoice::factory()->create([
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 300000,
        'paid_amount' => 0,
    ]);

    $claim = InsuranceClaim::query()->create([
        'invoice_id' => $invoice->id,
        'patient_id' => $invoice->patient_id,
        'amount_claimed' => 200000,
        'status' => InsuranceClaim::STATUS_DRAFT,
    ]);

    $this->actingAs($unauthorizedUser);

    $unauthorizedUser->update([
        'branch_id' => $invoice->resolveBranchId(),
    ]);

    expect(fn () => Payment::query()->create([
        'invoice_id' => $invoice->id,
        'amount' => 50000,
        'direction' => 'refund',
        'method' => 'cash',
        'paid_at' => now(),
        'received_by' => $unauthorizedUser->id,
    ]))->toThrow(ValidationException::class, 'không có quyền thực hiện hoàn tiền');

    expect(fn () => $claim->update([
        'status' => InsuranceClaim::STATUS_SUBMITTED,
    ]))->toThrow(ValidationException::class, 'không có quyền phê duyệt/từ chối hồ sơ bảo hiểm');
});

it('allows users with action permission to execute protected actions', function () {
    $doctor = User::factory()->create();
    $doctor->assignRole('Doctor');

    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $appointment = Appointment::factory()->create([
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    $doctor->update([
        'branch_id' => $appointment->branch_id,
    ]);

    $plan = TreatmentPlan::factory()->create([
        'status' => TreatmentPlan::STATUS_APPROVED,
        'branch_id' => $appointment->branch_id,
    ]);

    $planItem = PlanItem::query()->create([
        'treatment_plan_id' => $plan->id,
        'name' => 'Chỉnh nha',
        'status' => PlanItem::STATUS_PENDING,
        'approval_status' => PlanItem::APPROVAL_PROPOSED,
    ]);

    $this->actingAs($doctor);

    $appointment->applyOperationalOverride(
        Appointment::OVERRIDE_WALK_IN,
        'Khách walk-in',
        $doctor->id,
    );

    $planItem->update([
        'approval_status' => PlanItem::APPROVAL_APPROVED,
    ]);

    $this->actingAs($manager);
    $manager->update([
        'branch_id' => $appointment->branch_id,
    ]);

    $invoice = Invoice::factory()->create([
        'branch_id' => $appointment->branch_id,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 500000,
        'paid_amount' => 0,
    ]);

    $refund = $invoice->recordPayment(
        amount: 100000,
        method: 'cash',
        notes: 'Hoàn theo chính sách',
        paidAt: now(),
        direction: 'refund',
    );

    expect($appointment->fresh()->is_walk_in)->toBeTrue()
        ->and($planItem->fresh()->approval_status)->toBe(PlanItem::APPROVAL_APPROVED)
        ->and($refund->direction)->toBe('refund');
});

it('records expanded audit logs for consent insurance claim and treatment session', function () {
    $patient = Patient::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $patient->first_branch_id,
    ]);
    $manager->assignRole('Manager');

    $this->actingAs($manager);

    $consent = Consent::query()->create([
        'patient_id' => $patient->id,
        'consent_type' => 'high_risk',
        'consent_version' => 'v2',
        'status' => Consent::STATUS_PENDING,
    ]);

    $consent->update([
        'status' => Consent::STATUS_SIGNED,
        'signed_by' => $manager->id,
    ]);

    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 500000,
        'paid_amount' => 0,
    ]);

    $claim = InsuranceClaim::query()->create([
        'invoice_id' => $invoice->id,
        'patient_id' => $patient->id,
        'amount_claimed' => 300000,
        'status' => InsuranceClaim::STATUS_DRAFT,
    ]);

    $claim->submit();
    $claim->approve(280000);

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
    ]);

    $session = TreatmentSession::query()->create([
        'treatment_plan_id' => $plan->id,
        'status' => 'scheduled',
        'created_by' => $manager->id,
    ]);

    $session->update([
        'status' => 'done',
        'performed_at' => now(),
        'updated_by' => $manager->id,
    ]);

    expect(AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_CONSENT)
        ->where('entity_id', $consent->id)
        ->where('action', AuditLog::ACTION_APPROVE)
        ->exists())->toBeTrue();

    expect(AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_INSURANCE_CLAIM)
        ->where('entity_id', $claim->id)
        ->where('action', AuditLog::ACTION_APPROVE)
        ->exists())->toBeTrue();

    expect(AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_TREATMENT_SESSION)
        ->where('entity_id', $session->id)
        ->where('action', AuditLog::ACTION_COMPLETE)
        ->exists())->toBeTrue();
});
