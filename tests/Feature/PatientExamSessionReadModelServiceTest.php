<?php

use App\Models\ExamSession;
use App\Models\Patient;
use App\Models\TreatmentProgressDay;
use App\Models\User;
use App\Services\PatientExamSessionReadModelService;

it('builds ordered exam sessions with consistent locked flags', function (): void {
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();

    $editableSession = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'session_date' => '2026-04-01',
        'status' => ExamSession::STATUS_DRAFT,
    ]);

    $progressLockedSession = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'session_date' => '2026-04-02',
        'status' => ExamSession::STATUS_IN_PROGRESS,
    ]);

    $statusLockedSession = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'session_date' => '2026-04-03',
        'status' => ExamSession::STATUS_LOCKED,
    ]);

    TreatmentProgressDay::query()->create([
        'patient_id' => $patient->id,
        'exam_session_id' => $progressLockedSession->id,
        'branch_id' => $patient->first_branch_id,
        'progress_date' => '2026-04-02',
        'status' => TreatmentProgressDay::STATUS_PLANNED,
    ]);

    $sessions = app(PatientExamSessionReadModelService::class)->sessions($patient);

    expect($sessions->modelKeys())->toBe([
        $statusLockedSession->id,
        $progressLockedSession->id,
        $editableSession->id,
    ])->and($sessions->pluck('is_locked', 'id')->all())->toBe([
        $statusLockedSession->id => true,
        $progressLockedSession->id => true,
        $editableSession->id => false,
    ]);
});

it('returns latest and targeted exam sessions with loaded clinical notes and lock flags', function (): void {
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();

    $editableSession = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'session_date' => '2026-04-01',
        'status' => ExamSession::STATUS_DRAFT,
    ]);

    $latestSession = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'session_date' => '2026-04-03',
        'status' => ExamSession::STATUS_LOCKED,
    ]);

    $latestSession->clinicalNote()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'date' => '2026-04-03',
        'general_exam_notes' => 'Da ton tai clinical note',
        'indications' => [],
        'indication_images' => [],
        'tooth_diagnosis_data' => [],
    ]);

    $service = app(PatientExamSessionReadModelService::class);

    $resolvedLatestSession = $service->latestSession($patient);
    $resolvedEditableSession = $service->findSession($patient, $editableSession->id);

    expect($resolvedLatestSession)->not->toBeNull()
        ->and($resolvedLatestSession?->id)->toBe($latestSession->id)
        ->and($resolvedLatestSession?->relationLoaded('clinicalNote'))->toBeTrue()
        ->and((bool) $resolvedLatestSession?->is_locked)->toBeTrue()
        ->and($resolvedLatestSession?->clinicalNote)->not->toBeNull();

    expect($resolvedEditableSession)->not->toBeNull()
        ->and($resolvedEditableSession?->id)->toBe($editableSession->id)
        ->and($resolvedEditableSession?->relationLoaded('clinicalNote'))->toBeTrue()
        ->and((bool) $resolvedEditableSession?->is_locked)->toBeFalse()
        ->and($service->findSession($patient, 999999))->toBeNull();
});
