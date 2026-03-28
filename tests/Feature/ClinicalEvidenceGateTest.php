<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\ClinicalNote;
use App\Models\ClinicalOrder;
use App\Models\ClinicalResult;
use App\Models\Customer;
use App\Models\DoctorBranchAssignment;
use App\Models\EmrAuditLog;
use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\Service;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Models\VisitEpisode;
use Illuminate\Validation\ValidationException;

it('blocks clinical result finalize when required evidence is missing and no override reason', function (): void {
    $branch = Branch::factory()->create();
    $doctor = makeClinicalEvidenceDoctor($branch);
    $patient = makeClinicalEvidencePatient($branch);

    $this->actingAs($doctor);

    $encounter = VisitEpisode::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'status' => VisitEpisode::STATUS_IN_PROGRESS,
        'scheduled_at' => '2026-03-12 08:00:00',
        'in_chair_at' => '2026-03-12 08:10:00',
        'planned_duration_minutes' => 45,
    ]);

    $note = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'visit_episode_id' => $encounter->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => '2026-03-12',
        'examination_note' => 'Đau nhiều răng hàm trên.',
    ]);

    $order = ClinicalOrder::query()->create([
        'clinical_note_id' => $note->id,
        'order_type' => 'xray',
        'status' => ClinicalOrder::STATUS_IN_PROGRESS,
        'payload' => ['modality' => 'panorama'],
    ]);

    $result = ClinicalResult::query()->create([
        'clinical_order_id' => $order->id,
        'status' => ClinicalResult::STATUS_DRAFT,
        'payload' => ['modality' => 'panorama'],
    ]);

    expect(fn () => $result->finalize(
        verifiedBy: $doctor->id,
        interpretation: 'Nghi viêm quanh chóp răng 26.',
    ))->toThrow(ValidationException::class, 'Thiếu chứng cứ hình ảnh bắt buộc');

    expect($result->fresh()?->status)->toBe(ClinicalResult::STATUS_DRAFT)
        ->and($result->fresh()?->evidence_override_reason)->toBeNull()
        ->and($result->fresh()?->evidence_override_by)->toBeNull()
        ->and($result->fresh()?->evidence_override_at)->toBeNull();
});

it('allows clinical result finalize override for authorized role and keeps override audit context', function (): void {
    $branch = Branch::factory()->create();
    $doctor = makeClinicalEvidenceDoctor($branch);
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $patient = makeClinicalEvidencePatient($branch);

    $this->actingAs($manager);

    $encounter = VisitEpisode::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'status' => VisitEpisode::STATUS_IN_PROGRESS,
        'scheduled_at' => '2026-03-12 09:00:00',
        'in_chair_at' => '2026-03-12 09:05:00',
        'planned_duration_minutes' => 30,
    ]);

    $note = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'visit_episode_id' => $encounter->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => '2026-03-12',
        'examination_note' => 'Đề nghị kiểm tra phim.',
    ]);

    $order = ClinicalOrder::query()->create([
        'clinical_note_id' => $note->id,
        'order_type' => 'xray',
        'status' => ClinicalOrder::STATUS_IN_PROGRESS,
    ]);

    $result = ClinicalResult::query()->create([
        'clinical_order_id' => $order->id,
        'status' => ClinicalResult::STATUS_DRAFT,
    ]);

    $result->finalize(
        verifiedBy: $manager->id,
        interpretation: 'Máy chụp lỗi, chưa có phim ngay.',
        evidenceOverrideReason: 'Máy X-ray bảo trì, cho phép chốt tạm và bổ sung ảnh sau.',
    );

    $result->refresh();

    expect($result->status)->toBe(ClinicalResult::STATUS_FINAL)
        ->and($result->evidence_override_reason)->toContain('Máy X-ray bảo trì')
        ->and((int) $result->evidence_override_by)->toBe((int) $manager->id)
        ->and($result->evidence_override_at)->not->toBeNull();

    $audit = EmrAuditLog::query()
        ->where('entity_type', EmrAuditLog::ENTITY_CLINICAL_RESULT)
        ->where('entity_id', $result->id)
        ->where('action', EmrAuditLog::ACTION_FINALIZE)
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and((string) data_get($audit?->context, 'evidence_override_reason'))->toContain('Máy X-ray bảo trì');
});

it('requires evidence for protocol treatment session completion and supports authorized override with audit', function (): void {
    $branch = Branch::factory()->create();
    $doctor = makeClinicalEvidenceDoctor($branch);
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $patient = makeClinicalEvidencePatient($branch);

    $service = Service::query()->create([
        'name' => 'Implant protocol',
        'code' => 'SRV-IMPLANT-PROTOCOL',
        'workflow_type' => 'protocol',
        'protocol_id' => 'implant_v1',
        'branch_id' => $branch->id,
        'default_price' => 12000000,
        'active' => true,
    ]);

    $plan = TreatmentPlan::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'doctor_id' => $doctor->id,
        'title' => 'Kế hoạch implant PM-66',
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);

    $planItem = PlanItem::query()->create([
        'treatment_plan_id' => $plan->id,
        'service_id' => $service->id,
        'name' => 'Đặt trụ implant răng 26',
        'quantity' => 1,
        'price' => 12000000,
        'final_amount' => 12000000,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'status' => PlanItem::STATUS_IN_PROGRESS,
    ]);

    $session = TreatmentSession::query()->create([
        'treatment_plan_id' => $plan->id,
        'plan_item_id' => $planItem->id,
        'doctor_id' => $doctor->id,
        'status' => 'scheduled',
        'performed_at' => now(),
        'images' => [],
    ]);

    $this->actingAs($doctor);

    expect(fn () => $session->update([
        'status' => 'done',
    ]))->toThrow(ValidationException::class, 'Thiếu chứng cứ hình ảnh bắt buộc');

    $this->actingAs($manager);

    $session->update([
        'status' => 'done',
        'evidence_override_reason' => 'Bệnh nhân cần xử trí gấp, ảnh hậu thủ thuật sẽ bổ sung cuối ngày.',
    ]);

    $session->refresh();

    expect($session->status)->toBe('done')
        ->and($session->evidence_override_reason)->toContain('xử trí gấp')
        ->and((int) $session->evidence_override_by)->toBe((int) $manager->id)
        ->and($session->evidence_override_at)->not->toBeNull();

    $audit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_TREATMENT_SESSION)
        ->where('entity_id', $session->id)
        ->where('action', AuditLog::ACTION_COMPLETE)
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and((string) data_get($audit?->metadata, 'evidence_override_reason'))->toContain('xử trí gấp')
        ->and((string) data_get($audit?->metadata, 'reason'))->toContain('xử trí gấp')
        ->and(data_get($audit?->metadata, 'patient_id'))->toBe($patient->id)
        ->and(data_get($audit?->metadata, 'branch_id'))->toBe($branch->id)
        ->and(data_get($audit?->metadata, 'status_from'))->toBe('scheduled')
        ->and(data_get($audit?->metadata, 'status_to'))->toBe('done');
});

it('allows protocol treatment session completion without override when inline evidence exists', function (): void {
    $branch = Branch::factory()->create();
    $doctor = makeClinicalEvidenceDoctor($branch);
    $patient = makeClinicalEvidencePatient($branch);

    $service = Service::query()->create([
        'name' => 'Nội nha protocol',
        'code' => 'SRV-ENDO-PROTOCOL',
        'workflow_type' => 'protocol',
        'protocol_id' => 'endo_v1',
        'branch_id' => $branch->id,
        'default_price' => 1800000,
        'active' => true,
    ]);

    $plan = TreatmentPlan::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'doctor_id' => $doctor->id,
        'title' => 'Kế hoạch nội nha PM-66',
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);

    $planItem = PlanItem::query()->create([
        'treatment_plan_id' => $plan->id,
        'service_id' => $service->id,
        'name' => 'Nội nha răng 16',
        'quantity' => 1,
        'price' => 1800000,
        'final_amount' => 1800000,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'status' => PlanItem::STATUS_IN_PROGRESS,
    ]);

    $this->actingAs($doctor);

    $session = TreatmentSession::query()->create([
        'treatment_plan_id' => $plan->id,
        'plan_item_id' => $planItem->id,
        'doctor_id' => $doctor->id,
        'status' => 'scheduled',
        'performed_at' => now(),
        'images' => [],
    ]);

    $session->update([
        'status' => 'done',
        'images' => [
            'post_op' => 'storage/clinical/session-1-post-op.jpg',
        ],
    ]);

    $session->refresh();

    expect($session->status)->toBe('done')
        ->and($session->evidence_override_reason)->toBeNull()
        ->and($session->evidence_override_by)->toBeNull()
        ->and($session->evidence_override_at)->toBeNull();

    $audit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_TREATMENT_SESSION)
        ->where('entity_id', $session->id)
        ->where('action', AuditLog::ACTION_COMPLETE)
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and(data_get($audit?->metadata, 'patient_id'))->toBe($patient->id)
        ->and(data_get($audit?->metadata, 'branch_id'))->toBe($branch->id)
        ->and(data_get($audit?->metadata, 'status_from'))->toBe('scheduled')
        ->and(data_get($audit?->metadata, 'status_to'))->toBe('done')
        ->and(data_get($audit?->metadata, 'reason'))->toBeNull();
});

function makeClinicalEvidenceDoctor(Branch $branch): User
{
    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    DoctorBranchAssignment::query()->updateOrCreate(
        [
            'user_id' => $doctor->id,
            'branch_id' => $branch->id,
        ],
        [
            'is_active' => true,
            'is_primary' => true,
        ],
    );

    return $doctor;
}

function makeClinicalEvidencePatient(Branch $branch): Patient
{
    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    return Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);
}
