<?php

use App\Models\ExamSession;
use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\TreatmentPlan;
use App\Models\TreatmentProgressDay;
use App\Models\TreatmentProgressItem;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Services\TreatmentProgressSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('keeps treatment progress sync idempotent when called multiple times for the same session', function (): void {
    $doctor = User::factory()->create();
    $doctor->assignRole('Doctor');
    $patient = Patient::factory()->create();

    $plan = TreatmentPlan::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'doctor_id' => $doctor->id,
        'title' => 'Ke hoach dieu tri TRT-003',
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);

    $planItem = PlanItem::query()->create([
        'treatment_plan_id' => $plan->id,
        'name' => 'Lay cao rang',
        'quantity' => 1,
        'price' => 450000,
        'final_amount' => 450000,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'status' => PlanItem::STATUS_IN_PROGRESS,
    ]);

    $session = TreatmentSession::query()->create([
        'treatment_plan_id' => $plan->id,
        'plan_item_id' => $planItem->id,
        'doctor_id' => $doctor->id,
        'performed_at' => Carbon::parse('2026-03-06 09:00:00'),
        'status' => 'done',
        'notes' => 'Sync lan 1',
    ]);

    $service = app(TreatmentProgressSyncService::class);
    $first = $service->syncFromTreatmentSession($session->fresh());
    $second = $service->syncFromTreatmentSession($session->fresh());

    expect($first?->id)->toBe($second?->id)
        ->and(TreatmentProgressDay::query()->count())->toBe(1)
        ->and(TreatmentProgressItem::query()->count())->toBe(1)
        ->and(ExamSession::query()->count())->toBe(1);
});

it('reuses an existing exam session for the same patient date and branch', function (): void {
    $doctor = User::factory()->create();
    $doctor->assignRole('Doctor');
    $patient = Patient::factory()->create();

    $existingExamSession = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'doctor_id' => $doctor->id,
        'session_date' => '2026-03-06',
        'status' => ExamSession::STATUS_PLANNED,
    ]);

    $plan = TreatmentPlan::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'doctor_id' => $doctor->id,
        'title' => 'Ke hoach dieu tri TRT-003 reuse exam session',
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);

    $planItem = PlanItem::query()->create([
        'treatment_plan_id' => $plan->id,
        'name' => 'Dieu tri rang 26',
        'quantity' => 1,
        'price' => 1200000,
        'final_amount' => 1200000,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'status' => PlanItem::STATUS_IN_PROGRESS,
    ]);

    $session = TreatmentSession::query()->create([
        'treatment_plan_id' => $plan->id,
        'plan_item_id' => $planItem->id,
        'doctor_id' => $doctor->id,
        'performed_at' => Carbon::parse('2026-03-06 14:30:00'),
        'status' => 'done',
    ]);

    $session->refresh();

    expect((int) $session->exam_session_id)->toBe($existingExamSession->id)
        ->and(ExamSession::query()->count())->toBe(1)
        ->and(TreatmentProgressDay::query()->where('exam_session_id', $existingExamSession->id)->count())->toBe(1)
        ->and(TreatmentProgressItem::query()->where('treatment_session_id', $session->id)->count())->toBe(1);
});
