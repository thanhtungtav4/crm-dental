<?php

use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\TreatmentPlan;
use App\Models\TreatmentProgressDay;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Services\PatientExamStatusReadModelService;

it('prefers persisted treatment progress dates over treatment session fallback dates', function (): void {
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();

    $plan = TreatmentPlan::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'doctor_id' => $doctor->id,
        'title' => 'Kế hoạch giữ chỗ',
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);

    $planItem = PlanItem::query()->create([
        'treatment_plan_id' => $plan->id,
        'name' => 'Điều trị răng 11',
        'tooth_number' => '11',
        'quantity' => 1,
        'price' => 500000,
        'final_amount' => 500000,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'status' => PlanItem::STATUS_PENDING,
    ]);

    TreatmentProgressDay::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'progress_date' => '2026-03-10',
        'status' => TreatmentProgressDay::STATUS_PLANNED,
    ]);

    TreatmentProgressDay::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'progress_date' => '2026-03-12',
        'status' => TreatmentProgressDay::STATUS_COMPLETED,
    ]);

    TreatmentSession::withoutEvents(function () use ($doctor, $plan, $planItem): void {
        TreatmentSession::query()->create([
            'treatment_plan_id' => $plan->id,
            'plan_item_id' => $planItem->id,
            'doctor_id' => $doctor->id,
            'performed_at' => '2026-03-20 09:00:00',
            'status' => 'done',
            'notes' => 'Phiên điều trị cũ để test fallback.',
        ]);
    });

    $dates = app(PatientExamStatusReadModelService::class)->treatmentProgressDates($patient);

    expect($dates)->toBe(['2026-03-10', '2026-03-12']);
});

it('falls back to treatment session timestamps when no progress days exist', function (): void {
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();

    $plan = TreatmentPlan::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'doctor_id' => $doctor->id,
        'title' => 'Kế hoạch fallback tiến trình',
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);

    $planItem = PlanItem::query()->create([
        'treatment_plan_id' => $plan->id,
        'name' => 'Điều trị răng 12',
        'tooth_number' => '12',
        'quantity' => 1,
        'price' => 650000,
        'final_amount' => 650000,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'status' => PlanItem::STATUS_PENDING,
    ]);

    TreatmentSession::withoutEvents(function () use ($doctor, $plan, $planItem): void {
        TreatmentSession::query()->create([
            'treatment_plan_id' => $plan->id,
            'plan_item_id' => $planItem->id,
            'doctor_id' => $doctor->id,
            'start_at' => '2026-03-15 08:30:00',
            'end_at' => '2026-03-16 10:15:00',
            'status' => 'scheduled',
            'notes' => 'Phiên điều trị lên lịch.',
        ]);

        TreatmentSession::query()->create([
            'treatment_plan_id' => $plan->id,
            'plan_item_id' => $planItem->id,
            'doctor_id' => $doctor->id,
            'performed_at' => '2026-03-17 14:00:00',
            'status' => 'follow_up',
            'notes' => 'Phiên theo dõi sau điều trị.',
        ]);
    });

    $dates = app(PatientExamStatusReadModelService::class)->treatmentProgressDates($patient);

    expect(collect($dates)->sort()->values()->all())
        ->toBe(['2026-03-15', '2026-03-16', '2026-03-17']);
});

it('maps tooth treatment states from plan items and treatment sessions using state precedence', function (): void {
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();

    $plan = TreatmentPlan::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'doctor_id' => $doctor->id,
        'title' => 'Kế hoạch trạng thái răng',
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);

    $pendingItem = PlanItem::query()->create([
        'treatment_plan_id' => $plan->id,
        'name' => 'Theo dõi răng 11',
        'tooth_number' => '11',
        'quantity' => 1,
        'price' => 300000,
        'final_amount' => 300000,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'status' => PlanItem::STATUS_PENDING,
    ]);

    $completedItem = PlanItem::query()->create([
        'treatment_plan_id' => $plan->id,
        'name' => 'Hoàn tất răng 12',
        'tooth_number' => '12',
        'quantity' => 1,
        'price' => 450000,
        'final_amount' => 450000,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'status' => PlanItem::STATUS_COMPLETED,
    ]);

    $inProgressItem = PlanItem::query()->create([
        'treatment_plan_id' => $plan->id,
        'name' => 'Đang điều trị răng 13',
        'tooth_number' => '13',
        'quantity' => 1,
        'price' => 800000,
        'final_amount' => 800000,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'status' => PlanItem::STATUS_IN_PROGRESS,
    ]);

    $scheduledOverrideItem = PlanItem::query()->create([
        'treatment_plan_id' => $plan->id,
        'name' => 'Răng 14 chờ phiên điều trị',
        'tooth_number' => '14',
        'quantity' => 1,
        'price' => 900000,
        'final_amount' => 900000,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'status' => PlanItem::STATUS_COMPLETED,
    ]);

    $doneItem = PlanItem::query()->create([
        'treatment_plan_id' => $plan->id,
        'name' => 'Răng 15 đã hoàn tất',
        'tooth_number' => '15',
        'quantity' => 1,
        'price' => 750000,
        'final_amount' => 750000,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'status' => PlanItem::STATUS_COMPLETED,
    ]);

    TreatmentSession::withoutEvents(function () use ($doctor, $plan, $pendingItem, $scheduledOverrideItem, $doneItem): void {
        TreatmentSession::query()->create([
            'treatment_plan_id' => $plan->id,
            'plan_item_id' => $pendingItem->id,
            'doctor_id' => $doctor->id,
            'start_at' => '2026-03-18 09:00:00',
            'status' => 'scheduled',
            'notes' => 'Bắt đầu điều trị răng 11.',
        ]);

        TreatmentSession::query()->create([
            'treatment_plan_id' => $plan->id,
            'plan_item_id' => $scheduledOverrideItem->id,
            'doctor_id' => $doctor->id,
            'start_at' => '2026-03-18 11:00:00',
            'status' => 'scheduled',
            'notes' => 'Răng 14 có phiên điều trị tiếp theo.',
        ]);

        TreatmentSession::query()->create([
            'treatment_plan_id' => $plan->id,
            'plan_item_id' => $doneItem->id,
            'doctor_id' => $doctor->id,
            'performed_at' => '2026-03-18 14:00:00',
            'status' => 'done',
            'notes' => 'Răng 15 đã hoàn tất.',
        ]);
    });

    $states = app(PatientExamStatusReadModelService::class)->toothTreatmentStates($patient);

    expect($states)->toMatchArray([
        '11' => 'in_treatment',
        '12' => 'completed',
        '13' => 'in_treatment',
        '14' => 'in_treatment',
        '15' => 'completed',
    ]);
});
