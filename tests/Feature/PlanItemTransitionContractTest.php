<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\PlanItemWorkflowService;
use Illuminate\Validation\ValidationException;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makePlanItemContractFixture(array $overrides = []): array
{
    $branch = Branch::factory()->create();

    $doctor = User::factory()->create(['branch_id' => $branch->id]);
    $doctor->givePermissionTo('Update:PlanItem');
    $doctor->assignRole('Doctor');

    $customer = Customer::factory()->create(['branch_id' => $branch->id]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
    ]);

    $plan = TreatmentPlan::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'doctor_id' => $doctor->id,
        'title' => 'Contract test plan',
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);

    $planItem = PlanItem::query()->create(array_merge([
        'treatment_plan_id' => $plan->id,
        'name' => 'Contract test item',
        'quantity' => 1,
        'price' => 5000000,
        'final_amount' => 5000000,
        'required_visits' => 2,
        'completed_visits' => 0,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'status' => PlanItem::STATUS_PENDING,
    ], $overrides));

    return [$planItem, $patient, $branch, $doctor];
}

function planItemSvc(): PlanItemWorkflowService
{
    return app(PlanItemWorkflowService::class);
}

// ---------------------------------------------------------------------------
// Status guard
// ---------------------------------------------------------------------------

describe('PlanItem — status guard', function (): void {
    it('blocks edit-form payload from changing status directly via prepareEditablePayload', function (): void {
        [$planItem, , , $doctor] = makePlanItemContractFixture();
        $this->actingAs($doctor);

        expect(fn () => planItemSvc()->prepareEditablePayload($planItem, [
            'name' => $planItem->name,
            'status' => PlanItem::STATUS_COMPLETED,
        ]))->toThrow(ValidationException::class);
    });

    it('allows status to remain unchanged via prepareEditablePayload', function (): void {
        [$planItem, , , $doctor] = makePlanItemContractFixture();
        $this->actingAs($doctor);

        $result = planItemSvc()->prepareEditablePayload($planItem, [
            'name' => $planItem->name,
            'status' => PlanItem::STATUS_PENDING,
        ]);

        expect($result['status'])->toBe(PlanItem::STATUS_PENDING);
    });
});

// ---------------------------------------------------------------------------
// startTreatment
// ---------------------------------------------------------------------------

describe('PlanItem — startTreatment', function (): void {
    it('transitions pending → in_progress and writes audit with trigger manual_start', function (): void {
        [$planItem, , , $doctor] = makePlanItemContractFixture();
        $this->actingAs($doctor);

        $result = planItemSvc()->startTreatment($planItem, reason: 'begin_protocol');

        expect($result->status)->toBe(PlanItem::STATUS_IN_PROGRESS)
            ->and($result->started_at)->not->toBeNull();

        $log = AuditLog::query()
            ->where('entity_type', AuditLog::ENTITY_PLAN_ITEM)
            ->where('entity_id', $planItem->id)
            ->where('action', AuditLog::ACTION_UPDATE)
            ->latest('id')->first();

        expect($log)->not->toBeNull()
            ->and($log->metadata['trigger'])->toBe('manual_start')
            ->and($log->metadata['status_from'])->toBe(PlanItem::STATUS_PENDING)
            ->and($log->metadata['status_to'])->toBe(PlanItem::STATUS_IN_PROGRESS)
            ->and($log->metadata['reason'])->toBe('begin_protocol');
    });

    it('blocks startTreatment on a non-pending item', function (): void {
        [$planItem, , , $doctor] = makePlanItemContractFixture([
            'status' => PlanItem::STATUS_IN_PROGRESS,
            'started_at' => now()->toDateString(),
        ]);
        $this->actingAs($doctor);

        expect(fn () => planItemSvc()->startTreatment($planItem))
            ->toThrow(ValidationException::class);
    });

    it('blocks startTreatment when plan item is not patient-approved', function (): void {
        [$planItem, , , $doctor] = makePlanItemContractFixture([
            'approval_status' => PlanItem::APPROVAL_PROPOSED,
        ]);
        $this->actingAs($doctor);

        expect(fn () => planItemSvc()->startTreatment($planItem))
            ->toThrow(ValidationException::class);
    });
});

// ---------------------------------------------------------------------------
// completeVisit
// ---------------------------------------------------------------------------

describe('PlanItem — completeVisit', function (): void {
    it('increments visit count and writes audit with trigger complete_visit', function (): void {
        [$planItem, , , $doctor] = makePlanItemContractFixture(['required_visits' => 3]);
        $this->actingAs($doctor);

        // Start first
        planItemSvc()->startTreatment($planItem, reason: 'start');
        $planItem->refresh();

        $result = planItemSvc()->completeVisit($planItem, reason: 'visit_1');

        expect((int) $result->completed_visits)->toBeGreaterThanOrEqual(1);

        $log = AuditLog::query()
            ->where('entity_type', AuditLog::ENTITY_PLAN_ITEM)
            ->where('entity_id', $planItem->id)
            ->whereIn('action', [AuditLog::ACTION_UPDATE, AuditLog::ACTION_COMPLETE])
            ->orderByDesc('id')->first();

        expect($log)->not->toBeNull()
            ->and($log->metadata['trigger'])->toBe('complete_visit')
            ->and($log->metadata['completed_visits_from'])->toBeLessThan((int) $log->metadata['completed_visits_to'])
            ->and($log->metadata['reason'])->toBe('visit_1');
    });

    it('transitions to completed on final visit and writes ACTION_COMPLETE audit', function (): void {
        [$planItem, , , $doctor] = makePlanItemContractFixture(['required_visits' => 1]);
        $this->actingAs($doctor);

        $result = planItemSvc()->completeVisit($planItem, reason: 'final_visit');

        expect($result->status)->toBe(PlanItem::STATUS_COMPLETED)
            ->and($result->progress_percentage)->toBe(100);

        $log = AuditLog::query()
            ->where('entity_type', AuditLog::ENTITY_PLAN_ITEM)
            ->where('entity_id', $planItem->id)
            ->where('action', AuditLog::ACTION_COMPLETE)
            ->latest('id')->first();

        expect($log)->not->toBeNull()
            ->and($log->metadata['status_to'])->toBe(PlanItem::STATUS_COMPLETED)
            ->and($log->metadata['progress_to'])->toBe(100);
    });

    it('blocks completeVisit on a cancelled item', function (): void {
        [$planItem, , , $doctor] = makePlanItemContractFixture([
            'status' => PlanItem::STATUS_CANCELLED,
        ]);
        $this->actingAs($doctor);

        expect(fn () => planItemSvc()->completeVisit($planItem))
            ->toThrow(ValidationException::class);
    });
});

// ---------------------------------------------------------------------------
// completeTreatment
// ---------------------------------------------------------------------------

describe('PlanItem — completeTreatment', function (): void {
    it('transitions in_progress → completed and writes ACTION_COMPLETE audit', function (): void {
        [$planItem, , , $doctor] = makePlanItemContractFixture([
            'status' => PlanItem::STATUS_IN_PROGRESS,
            'started_at' => now()->toDateString(),
        ]);
        $this->actingAs($doctor);

        $result = planItemSvc()->completeTreatment($planItem, reason: 'force_close');

        expect($result->status)->toBe(PlanItem::STATUS_COMPLETED)
            ->and($result->progress_percentage)->toBe(100)
            ->and($result->completed_at)->not->toBeNull();

        $log = AuditLog::query()
            ->where('entity_type', AuditLog::ENTITY_PLAN_ITEM)
            ->where('entity_id', $planItem->id)
            ->where('action', AuditLog::ACTION_COMPLETE)
            ->latest('id')->first();

        expect($log)->not->toBeNull()
            ->and($log->metadata['trigger'])->toBe('manual_complete')
            ->and($log->metadata['status_from'])->toBe(PlanItem::STATUS_IN_PROGRESS)
            ->and($log->metadata['status_to'])->toBe(PlanItem::STATUS_COMPLETED);
    });

    it('blocks completeTreatment on an already-completed item', function (): void {
        [$planItem, , , $doctor] = makePlanItemContractFixture([
            'status' => PlanItem::STATUS_COMPLETED,
            'progress_percentage' => 100,
            'completed_at' => now()->toDateString(),
        ]);
        $this->actingAs($doctor);

        expect(fn () => planItemSvc()->completeTreatment($planItem))
            ->toThrow(ValidationException::class);
    });
});

// ---------------------------------------------------------------------------
// cancel
// ---------------------------------------------------------------------------

describe('PlanItem — cancel', function (): void {
    it('transitions pending → cancelled and writes ACTION_CANCEL audit', function (): void {
        [$planItem, , , $doctor] = makePlanItemContractFixture();
        $this->actingAs($doctor);

        $result = planItemSvc()->cancel($planItem, reason: 'patient_withdrew');

        expect($result->status)->toBe(PlanItem::STATUS_CANCELLED);

        $log = AuditLog::query()
            ->where('entity_type', AuditLog::ENTITY_PLAN_ITEM)
            ->where('entity_id', $planItem->id)
            ->where('action', AuditLog::ACTION_CANCEL)
            ->latest('id')->first();

        expect($log)->not->toBeNull()
            ->and($log->metadata['trigger'])->toBe('manual_cancel')
            ->and($log->metadata['status_from'])->toBe(PlanItem::STATUS_PENDING)
            ->and($log->metadata['status_to'])->toBe(PlanItem::STATUS_CANCELLED)
            ->and($log->metadata['reason'])->toBe('patient_withdrew');
    });

    it('blocks cancel on a completed item', function (): void {
        [$planItem, , , $doctor] = makePlanItemContractFixture([
            'status' => PlanItem::STATUS_COMPLETED,
            'progress_percentage' => 100,
            'completed_at' => now()->toDateString(),
        ]);
        $this->actingAs($doctor);

        expect(fn () => planItemSvc()->cancel($planItem))
            ->toThrow(ValidationException::class);
    });
});
