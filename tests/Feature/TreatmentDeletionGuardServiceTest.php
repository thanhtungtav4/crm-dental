<?php

use App\Models\Branch;
use App\Models\ExamSession;
use App\Models\Note;
use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Services\TreatmentDeletionGuardService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows deleting an empty treatment plan', function (): void {
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create(['branch_id' => $branch->id]);
    $doctor->assignRole('Doctor');
    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'doctor_id' => $doctor->id,
    ]);

    expect(app(TreatmentDeletionGuardService::class)->canDeleteTreatmentPlan($plan))->toBeTrue();
});

it('blocks deleting a treatment plan once it has child records', function (): void {
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create(['branch_id' => $branch->id]);
    $doctor->assignRole('Doctor');
    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'doctor_id' => $doctor->id,
    ]);

    PlanItem::factory()->create([
        'treatment_plan_id' => $plan->id,
    ]);

    expect(app(TreatmentDeletionGuardService::class)->canDeleteTreatmentPlan($plan->fresh()))->toBeFalse();
});

it('allows deleting a standalone plan item before treatment starts', function (): void {
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create(['branch_id' => $branch->id]);
    $doctor->assignRole('Doctor');
    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'doctor_id' => $doctor->id,
    ]);

    $planItem = PlanItem::factory()->create([
        'treatment_plan_id' => $plan->id,
    ]);

    expect(app(TreatmentDeletionGuardService::class)->canDeletePlanItem($planItem))->toBeTrue();
});

it('blocks deleting a plan item once treatment sessions exist', function (): void {
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create(['branch_id' => $branch->id]);
    $doctor->assignRole('Doctor');
    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'doctor_id' => $doctor->id,
    ]);
    $planItem = PlanItem::factory()->create([
        'treatment_plan_id' => $plan->id,
    ]);

    TreatmentSession::withoutEvents(function () use ($plan, $planItem, $doctor): void {
        TreatmentSession::factory()->create([
            'treatment_plan_id' => $plan->id,
            'plan_item_id' => $planItem->id,
            'doctor_id' => $doctor->id,
        ]);
    });

    expect(app(TreatmentDeletionGuardService::class)->canDeletePlanItem($planItem->fresh()))->toBeFalse();
});

it('blocks deleting a treatment session once clinical follow-up history exists', function (): void {
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create(['branch_id' => $branch->id]);
    $doctor->assignRole('Doctor');
    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'doctor_id' => $doctor->id,
    ]);
    $planItem = PlanItem::factory()->create([
        'treatment_plan_id' => $plan->id,
    ]);
    $examSession = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'doctor_id' => $doctor->id,
        'session_date' => today()->toDateString(),
        'status' => ExamSession::STATUS_PLANNED,
    ]);

    $session = TreatmentSession::withoutEvents(fn (): TreatmentSession => TreatmentSession::factory()->create([
        'treatment_plan_id' => $plan->id,
        'plan_item_id' => $planItem->id,
        'doctor_id' => $doctor->id,
        'exam_session_id' => $examSession->id,
    ]));

    Note::query()->create([
        'patient_id' => $patient->id,
        'user_id' => $doctor->id,
        'type' => Note::TYPE_GENERAL,
        'content' => 'Theo doi sau dieu tri',
        'source_type' => TreatmentSession::class,
        'source_id' => $session->id,
    ]);

    expect(app(TreatmentDeletionGuardService::class)->canDeleteTreatmentSession($session))->toBeFalse();
});

it('allows deleting an unsynced treatment session without downstream history', function (): void {
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create(['branch_id' => $branch->id]);
    $doctor->assignRole('Doctor');
    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'doctor_id' => $doctor->id,
    ]);
    $planItem = PlanItem::factory()->create([
        'treatment_plan_id' => $plan->id,
    ]);

    $session = TreatmentSession::withoutEvents(fn (): TreatmentSession => TreatmentSession::factory()->create([
        'treatment_plan_id' => $plan->id,
        'plan_item_id' => $planItem->id,
        'doctor_id' => $doctor->id,
        'exam_session_id' => null,
    ]));

    expect(app(TreatmentDeletionGuardService::class)->canDeleteTreatmentSession($session))->toBeTrue();
});
