<?php

use App\Models\Note;
use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('maps legacy patient_approved flag into approval workflow', function () {
    $plan = makeTreatmentPlanForApprovalTests();

    $item = makePlanItemForApprovalTests($plan, [
        'approval_status' => null,
        'patient_approved' => true,
    ]);

    expect($item->fresh()->approval_status)->toBe(PlanItem::APPROVAL_APPROVED)
        ->and($item->fresh()->patient_approved)->toBeTrue();
});

it('blocks invalid approval status transition after approved', function () {
    $plan = makeTreatmentPlanForApprovalTests();
    $item = makePlanItemForApprovalTests($plan, [
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'patient_approved' => true,
    ]);

    expect(fn () => $item->update([
        'approval_status' => PlanItem::APPROVAL_PROPOSED,
    ]))->toThrow(ValidationException::class, 'PLAN_ITEM_APPROVAL_STATE_INVALID');
});

it('requires decline reason and creates follow-up queue ticket for coordinator', function () {
    $plan = makeTreatmentPlanForApprovalTests();
    $item = makePlanItemForApprovalTests($plan, [
        'approval_status' => PlanItem::APPROVAL_PROPOSED,
    ]);

    expect(fn () => $item->update([
        'approval_status' => PlanItem::APPROVAL_DECLINED,
    ]))->toThrow(ValidationException::class, 'lý do bệnh nhân từ chối');

    $item->update([
        'approval_status' => PlanItem::APPROVAL_DECLINED,
        'approval_decline_reason' => 'Bệnh nhân cần thêm thời gian cân đối ngân sách',
    ]);

    $ticket = Note::query()
        ->where('source_type', PlanItem::class)
        ->where('source_id', $item->id)
        ->where('care_type', 'treatment_plan_follow_up')
        ->first();

    expect($ticket)->not->toBeNull()
        ->and($ticket->care_status)->toBe(Note::CARE_STATUS_NOT_STARTED)
        ->and($ticket->content)->toContain('cân đối ngân sách');

    $item->update([
        'approval_status' => PlanItem::APPROVAL_APPROVED,
    ]);

    expect($ticket->fresh()->care_status)->toBe(Note::CARE_STATUS_FAILED);
});

it('blocks treatment progress when item is not approved', function () {
    $plan = makeTreatmentPlanForApprovalTests();
    $item = makePlanItemForApprovalTests($plan, [
        'approval_status' => PlanItem::APPROVAL_PROPOSED,
    ]);

    expect(fn () => $item->update([
        'status' => PlanItem::STATUS_IN_PROGRESS,
    ]))->toThrow(ValidationException::class, 'chưa được bệnh nhân duyệt');

    expect(fn () => $item->completeVisit())
        ->toThrow(ValidationException::class, 'chưa được bệnh nhân duyệt');
});

it('blocks treatment phase progression when parent plan is still draft', function () {
    $plan = makeTreatmentPlanForApprovalTests([
        'status' => TreatmentPlan::STATUS_DRAFT,
    ]);

    $item = makePlanItemForApprovalTests($plan, [
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'patient_approved' => true,
    ]);

    expect(fn () => $item->update([
        'status' => PlanItem::STATUS_IN_PROGRESS,
    ]))->toThrow(ValidationException::class, 'Kế hoạch điều trị chưa được duyệt');
});

function makeTreatmentPlanForApprovalTests(array $overrides = []): TreatmentPlan
{
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();

    return TreatmentPlan::create(array_merge([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'title' => 'Kế hoạch điều trị test approval',
        'status' => TreatmentPlan::STATUS_APPROVED,
    ], $overrides));
}

function makePlanItemForApprovalTests(TreatmentPlan $plan, array $overrides = []): PlanItem
{
    return PlanItem::create(array_merge([
        'treatment_plan_id' => $plan->id,
        'name' => 'Điều trị test',
        'quantity' => 1,
        'price' => 1000000,
        'estimated_cost' => 1000000,
        'actual_cost' => 0,
        'required_visits' => 2,
        'completed_visits' => 0,
        'status' => PlanItem::STATUS_PENDING,
        'approval_status' => PlanItem::APPROVAL_PROPOSED,
        'patient_approved' => false,
    ], $overrides));
}
