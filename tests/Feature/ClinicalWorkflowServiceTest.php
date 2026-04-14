<?php

use App\Models\Branch;
use App\Models\ClinicalNote;
use App\Models\ClinicalOrder;
use App\Models\ClinicalResult;
use App\Models\Customer;
use App\Models\DoctorBranchAssignment;
use App\Models\EmrAuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Models\VisitEpisode;
use App\Services\ClinicalOrderWorkflowService;
use App\Services\ClinicalResultWorkflowService;
use Illuminate\Validation\ValidationException;

it('records managed audit context when clinical order workflow service completes an order', function (): void {
    [$order, $doctor] = makeClinicalWorkflowFixture();

    $this->actingAs($doctor);

    $completedOrder = app(ClinicalOrderWorkflowService::class)->markCompleted(
        order: $order,
        actorId: $doctor->id,
        reason: 'Ket qua da san sang',
    );

    $auditLog = EmrAuditLog::query()
        ->where('entity_type', EmrAuditLog::ENTITY_CLINICAL_ORDER)
        ->where('entity_id', $order->id)
        ->where('action', EmrAuditLog::ACTION_COMPLETE)
        ->latest('id')
        ->first();

    expect($completedOrder->status)->toBe(ClinicalOrder::STATUS_COMPLETED)
        ->and($completedOrder->completed_at)->not->toBeNull()
        ->and($auditLog)->not->toBeNull()
        ->and($auditLog?->actor_id)->toBe($doctor->id)
        ->and(data_get($auditLog?->context, 'trigger'))->toBe('manual_complete')
        ->and(data_get($auditLog?->context, 'reason'))->toBe('Ket qua da san sang')
        ->and(data_get($auditLog?->context, 'status_from'))->toBe(ClinicalOrder::STATUS_PENDING)
        ->and(data_get($auditLog?->context, 'status_to'))->toBe(ClinicalOrder::STATUS_COMPLETED);
});

it('records managed audit context when clinical order is marked in progress through the model boundary', function (): void {
    [$order, $doctor] = makeClinicalWorkflowFixture();

    $this->actingAs($doctor);

    $order->markInProgress($doctor->id, 'Bat dau thuc hien', 'model_in_progress');

    $auditLog = EmrAuditLog::query()
        ->where('entity_type', EmrAuditLog::ENTITY_CLINICAL_ORDER)
        ->where('entity_id', $order->id)
        ->where('action', EmrAuditLog::ACTION_UPDATE)
        ->latest('id')
        ->first();

    expect($order->fresh()->status)->toBe(ClinicalOrder::STATUS_IN_PROGRESS)
        ->and($auditLog)->not->toBeNull()
        ->and(data_get($auditLog?->context, 'trigger'))->toBe('model_in_progress')
        ->and(data_get($auditLog?->context, 'reason'))->toBe('Bat dau thuc hien')
        ->and(data_get($auditLog?->context, 'status_from'))->toBe(ClinicalOrder::STATUS_PENDING)
        ->and(data_get($auditLog?->context, 'status_to'))->toBe(ClinicalOrder::STATUS_IN_PROGRESS);
});

it('returns transitioned clinical orders from model workflow boundaries', function (): void {
    [$order, $doctor] = makeClinicalWorkflowFixture();
    [$cancelOrder, $cancelDoctor] = makeClinicalWorkflowFixture([
        'status' => ClinicalOrder::STATUS_IN_PROGRESS,
    ]);

    $this->actingAs($doctor);

    $inProgressOrder = $order->markInProgress($doctor->id, 'Bat dau', 'model_in_progress');
    $completedOrder = $inProgressOrder->markCompleted($doctor->id, 'Hoan tat', 'model_complete');

    $this->actingAs($cancelDoctor);

    $cancelledOrder = $cancelOrder->cancel('Dung chi dinh', $cancelDoctor->id);

    expect($inProgressOrder)->toBeInstanceOf(ClinicalOrder::class)
        ->and($inProgressOrder->status)->toBe(ClinicalOrder::STATUS_IN_PROGRESS)
        ->and($completedOrder)->toBeInstanceOf(ClinicalOrder::class)
        ->and($completedOrder->status)->toBe(ClinicalOrder::STATUS_COMPLETED)
        ->and($cancelledOrder)->toBeInstanceOf(ClinicalOrder::class)
        ->and($cancelledOrder->status)->toBe(ClinicalOrder::STATUS_CANCELLED);
});

it('records managed audit context when clinical result workflow service finalizes a result', function (): void {
    [$order, $doctor] = makeClinicalWorkflowFixture([
        'status' => ClinicalOrder::STATUS_IN_PROGRESS,
    ]);

    $this->actingAs($doctor);

    $result = ClinicalResult::query()->create([
        'clinical_order_id' => $order->id,
        'status' => ClinicalResult::STATUS_DRAFT,
        'payload' => ['attachment' => 'xray/workflow-finalize.jpg'],
    ]);

    $finalizedResult = app(ClinicalResultWorkflowService::class)->finalize(
        result: $result,
        verifiedBy: $doctor->id,
        interpretation: 'Khong thay ton thuong them.',
        reason: 'Bac si da review xong',
    );

    $auditLog = EmrAuditLog::query()
        ->where('entity_type', EmrAuditLog::ENTITY_CLINICAL_RESULT)
        ->where('entity_id', $result->id)
        ->where('action', EmrAuditLog::ACTION_FINALIZE)
        ->latest('id')
        ->first();

    $orderAuditLog = EmrAuditLog::query()
        ->where('entity_type', EmrAuditLog::ENTITY_CLINICAL_ORDER)
        ->where('entity_id', $order->id)
        ->where('action', EmrAuditLog::ACTION_COMPLETE)
        ->latest('id')
        ->first();

    expect($finalizedResult->status)->toBe(ClinicalResult::STATUS_FINAL)
        ->and($finalizedResult->verified_by)->toBe($doctor->id)
        ->and($auditLog)->not->toBeNull()
        ->and($auditLog?->actor_id)->toBe($doctor->id)
        ->and(data_get($auditLog?->context, 'trigger'))->toBe('manual_finalize')
        ->and(data_get($auditLog?->context, 'reason'))->toBe('Bac si da review xong')
        ->and(data_get($auditLog?->context, 'status_from'))->toBe(ClinicalResult::STATUS_DRAFT)
        ->and(data_get($auditLog?->context, 'status_to'))->toBe(ClinicalResult::STATUS_FINAL)
        ->and($order->fresh()->status)->toBe(ClinicalOrder::STATUS_COMPLETED)
        ->and($orderAuditLog)->not->toBeNull()
        ->and(data_get($orderAuditLog?->context, 'trigger'))->toBe('result_finalized')
        ->and(data_get($orderAuditLog?->context, 'reason'))->toBe('Ket qua chi dinh da duoc chot.');
});

it('blocks raw clinical order and result status changes outside workflow services', function (): void {
    [$order, $doctor] = makeClinicalWorkflowFixture([
        'status' => ClinicalOrder::STATUS_IN_PROGRESS,
    ]);

    $this->actingAs($doctor);

    $result = ClinicalResult::query()->create([
        'clinical_order_id' => $order->id,
        'status' => ClinicalResult::STATUS_DRAFT,
    ]);

    expect(fn () => $order->update([
        'status' => ClinicalOrder::STATUS_COMPLETED,
    ]))->toThrow(ValidationException::class, 'ClinicalOrderWorkflowService');

    expect(fn () => $result->update([
        'status' => ClinicalResult::STATUS_FINAL,
    ]))->toThrow(ValidationException::class, 'ClinicalResultWorkflowService');
});

/**
 * @param  array<string, mixed>  $orderOverrides
 * @return array{0: ClinicalOrder, 1: User}
 */
function makeClinicalWorkflowFixture(array $orderOverrides = []): array
{
    $branch = Branch::factory()->create();
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

    $encounter = VisitEpisode::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'status' => VisitEpisode::STATUS_IN_PROGRESS,
        'scheduled_at' => '2026-03-15 09:00:00',
        'in_chair_at' => '2026-03-15 09:05:00',
        'planned_duration_minutes' => 30,
    ]);

    $note = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'visit_episode_id' => $encounter->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => '2026-03-15',
        'examination_note' => 'Kiem tra ket qua X-ray',
    ]);

    $order = ClinicalOrder::query()->create(array_merge([
        'clinical_note_id' => $note->id,
        'order_type' => 'xray',
        'status' => ClinicalOrder::STATUS_PENDING,
        'payload' => ['modality' => 'panorama'],
        'ordered_by' => $doctor->id,
    ], $orderOverrides));

    return [$order, $doctor];
}
