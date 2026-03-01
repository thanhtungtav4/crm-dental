<?php

use App\Models\ExamSession;
use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\Prescription;
use App\Models\TreatmentPlan;
use App\Models\TreatmentProgressDay;
use App\Models\TreatmentProgressItem;
use App\Models\TreatmentSession;
use App\Models\User;
use Illuminate\Support\Carbon;

it('syncs treatment session into treatment progress day and item then marks exam session completed', function (): void {
    $doctor = User::factory()->create();
    $doctor->assignRole('Doctor');

    $patient = Patient::factory()->create();

    $plan = TreatmentPlan::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'doctor_id' => $doctor->id,
        'title' => 'Kế hoạch điều trị PM-48',
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);

    $planItem = PlanItem::query()->create([
        'treatment_plan_id' => $plan->id,
        'name' => 'Điều trị tủy răng 26',
        'tooth_number' => '26',
        'quantity' => 1,
        'price' => 1200000,
        'final_amount' => 1200000,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'status' => PlanItem::STATUS_IN_PROGRESS,
    ]);

    $sessionDateTime = Carbon::parse('2026-03-01 09:30:00');

    $treatmentSession = TreatmentSession::query()->create([
        'treatment_plan_id' => $plan->id,
        'plan_item_id' => $planItem->id,
        'doctor_id' => $doctor->id,
        'performed_at' => $sessionDateTime,
        'status' => 'done',
        'notes' => 'Hoàn tất phiên điều trị đầu tiên.',
    ]);

    $treatmentSession->refresh();

    $progressItem = TreatmentProgressItem::query()
        ->where('treatment_session_id', $treatmentSession->id)
        ->first();

    $progressDay = TreatmentProgressDay::query()
        ->where('id', $progressItem?->treatment_progress_day_id)
        ->first();

    $examSession = ExamSession::query()->find($treatmentSession->exam_session_id);

    expect($treatmentSession->exam_session_id)->not->toBeNull()
        ->and($progressItem)->not->toBeNull()
        ->and($progressItem?->status)->toBe(TreatmentProgressItem::STATUS_COMPLETED)
        ->and((float) ($progressItem?->total_amount ?? 0))->toBe(1200000.0)
        ->and($progressDay)->not->toBeNull()
        ->and($progressDay?->status)->toBe(TreatmentProgressDay::STATUS_COMPLETED)
        ->and($examSession)->not->toBeNull()
        ->and($examSession?->status)->toBe(ExamSession::STATUS_COMPLETED);
});

it('locks exam session and progress day when prescription is created for that session', function (): void {
    $doctor = User::factory()->create();
    $doctor->assignRole('Doctor');

    $patient = Patient::factory()->create();

    $examSession = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'doctor_id' => $doctor->id,
        'session_date' => '2026-03-05',
        'status' => ExamSession::STATUS_COMPLETED,
    ]);

    $progressDay = TreatmentProgressDay::query()->create([
        'patient_id' => $patient->id,
        'exam_session_id' => $examSession->id,
        'branch_id' => $patient->first_branch_id,
        'progress_date' => '2026-03-05',
        'status' => TreatmentProgressDay::STATUS_COMPLETED,
    ]);

    Prescription::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'exam_session_id' => $examSession->id,
        'doctor_id' => $doctor->id,
        'prescription_code' => Prescription::generatePrescriptionCode(),
        'prescription_name' => 'Kháng sinh sau điều trị',
        'treatment_date' => '2026-03-05',
    ]);

    $examSession->refresh();
    $progressDay->refresh();

    expect($examSession->status)->toBe(ExamSession::STATUS_LOCKED)
        ->and($progressDay->status)->toBe(TreatmentProgressDay::STATUS_LOCKED)
        ->and($progressDay->locked_at)->not->toBeNull();
});
