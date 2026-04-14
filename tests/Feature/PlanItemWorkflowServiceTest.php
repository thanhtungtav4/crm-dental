<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\PlanItemWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('records structured audit when plan item is started through workflow service', function (): void {
    [$planItem, , , $doctor] = makePlanItemWorkflowFixture();
    $this->actingAs($doctor);

    $started = app(PlanItemWorkflowService::class)->startTreatment(
        planItem: $planItem,
        reason: 'operator_start',
    );

    expect($started->status)->toBe(PlanItem::STATUS_IN_PROGRESS)
        ->and($started->started_at)->not->toBeNull();

    $audit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_PLAN_ITEM)
        ->where('entity_id', $planItem->id)
        ->where('action', AuditLog::ACTION_UPDATE)
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit?->metadata)->toMatchArray([
            'status_from' => PlanItem::STATUS_PENDING,
            'status_to' => PlanItem::STATUS_IN_PROGRESS,
            'reason' => 'operator_start',
            'trigger' => 'manual_start',
        ]);
});

it('records completion audit and keeps patient timeline friendly metadata for completed visit', function (): void {
    [$planItem, , , $doctor] = makePlanItemWorkflowFixture([
        'required_visits' => 1,
    ]);
    $this->actingAs($doctor);

    $completed = app(PlanItemWorkflowService::class)->completeVisit(
        planItem: $planItem,
        reason: 'visit_done',
    );

    expect($completed->status)->toBe(PlanItem::STATUS_COMPLETED)
        ->and($completed->completed_visits)->toBe(1)
        ->and($completed->progress_percentage)->toBe(100);

    $audit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_PLAN_ITEM)
        ->where('entity_id', $planItem->id)
        ->where('action', AuditLog::ACTION_COMPLETE)
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit?->metadata)->toMatchArray([
            'status_from' => PlanItem::STATUS_PENDING,
            'status_to' => PlanItem::STATUS_COMPLETED,
            'reason' => 'visit_done',
            'trigger' => 'complete_visit',
            'completed_visits_from' => 0,
            'completed_visits_to' => 1,
            'progress_from' => 0,
            'progress_to' => 100,
            'required_visits' => 1,
        ]);
});

it('cancels plan items through the canonical model boundary', function (): void {
    [$planItem, , , $doctor] = makePlanItemWorkflowFixture();
    $this->actingAs($doctor);

    $planItem->cancel('model_boundary_cancel', $doctor->id);

    $cancelAudit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_PLAN_ITEM)
        ->where('entity_id', $planItem->id)
        ->where('action', AuditLog::ACTION_CANCEL)
        ->latest('id')
        ->first();

    expect($planItem->fresh()->status)->toBe(PlanItem::STATUS_CANCELLED)
        ->and($cancelAudit)->not->toBeNull()
        ->and(data_get($cancelAudit, 'metadata.reason'))->toBe('model_boundary_cancel')
        ->and(data_get($cancelAudit, 'metadata.status_to'))->toBe(PlanItem::STATUS_CANCELLED);
});

/**
 * @return array{0: PlanItem, 1: Patient, 2: Branch, 3: User}
 */
function makePlanItemWorkflowFixture(array $overrides = []): array
{
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->givePermissionTo('Update:PlanItem');
    $doctor->assignRole('Doctor');

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
    ]);
    $plan = TreatmentPlan::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'doctor_id' => $doctor->id,
        'title' => 'Plan item workflow fixture',
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);

    $planItem = PlanItem::query()->create(array_merge([
        'treatment_plan_id' => $plan->id,
        'name' => 'Implant 26',
        'quantity' => 1,
        'price' => 12000000,
        'final_amount' => 12000000,
        'required_visits' => 2,
        'completed_visits' => 0,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'status' => PlanItem::STATUS_PENDING,
    ], $overrides));

    return [$planItem, $patient, $branch, $doctor];
}
