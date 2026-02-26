<?php

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MasterDataSyncLog;
use App\Models\MasterPatientIdentity;
use App\Models\Material;
use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\ReportSnapshot;
use App\Models\TreatmentPlan;
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

    expect($dedupeLog)->not->toBeNull();
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

    expect(fn () => $appointment->applyOperationalOverride(
        Appointment::OVERRIDE_EMERGENCY,
        'Đau cấp cần chen lịch',
        $unauthorizedUser->id,
    ))->toThrow(ValidationException::class, 'không có quyền override vận hành lịch hẹn');

    expect(fn () => $invoice->recordPayment(
        amount: 100000,
        method: 'cash',
        notes: 'Hoàn nhầm',
        paidAt: now(),
        direction: 'refund',
    ))->toThrow(ValidationException::class, 'không có quyền thực hiện hoàn tiền');

    expect(fn () => $planItem->update([
        'approval_status' => PlanItem::APPROVAL_APPROVED,
    ]))->toThrow(ValidationException::class, 'không có quyền thay đổi trạng thái duyệt');
});

it('allows users with action permission to execute protected actions', function () {
    $doctor = User::factory()->create();
    $doctor->assignRole('Doctor');

    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $appointment = Appointment::factory()->create([
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    $plan = TreatmentPlan::factory()->create([
        'status' => TreatmentPlan::STATUS_APPROVED,
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

    $invoice = Invoice::factory()->create([
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
