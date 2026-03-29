<?php

use App\Models\ClinicalNote;
use App\Models\ClinicalOrder;
use App\Models\ExamSession;
use App\Models\Patient;
use App\Models\TreatmentProgressDay;
use App\Models\User;
use App\Models\VisitEpisode;
use App\Services\PatientExamSessionWorkflowService;
use Illuminate\Validation\ValidationException;

it('deletes an exam session together with its linked clinical notes when the session is still editable', function (): void {
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();

    $session = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'session_date' => '2026-03-26',
        'status' => ExamSession::STATUS_IN_PROGRESS,
    ]);

    $note = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'exam_session_id' => $session->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'date' => '2026-03-26',
        'indications' => [],
        'indication_images' => [],
        'tooth_diagnosis_data' => [],
    ]);

    $deleted = app(PatientExamSessionWorkflowService::class)->deleteSession($patient, $session->id);

    expect($deleted)->toBeTrue()
        ->and(ExamSession::query()->whereKey($session->id)->exists())->toBeFalse()
        ->and(ClinicalNote::query()->whereKey($note->id)->exists())->toBeFalse();
});

it('refuses to delete an exam session once treatment progress exists for the same date', function (): void {
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();

    $session = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'session_date' => '2026-03-27',
        'status' => ExamSession::STATUS_IN_PROGRESS,
    ]);

    TreatmentProgressDay::query()->create([
        'patient_id' => $patient->id,
        'exam_session_id' => $session->id,
        'branch_id' => $patient->first_branch_id,
        'progress_date' => '2026-03-27',
        'status' => TreatmentProgressDay::STATUS_PLANNED,
    ]);

    expect(fn () => app(PatientExamSessionWorkflowService::class)->deleteSession($patient, $session->id))
        ->toThrow(ValidationException::class, 'Ngày khám đã có tiến trình điều trị nên không thể xóa được.');

    expect(ExamSession::query()->whereKey($session->id)->exists())->toBeTrue();
});

it('refuses to delete an exam session that already spawned clinical orders', function (): void {
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();

    $session = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'session_date' => '2026-03-28',
        'status' => ExamSession::STATUS_IN_PROGRESS,
    ]);

    $note = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'exam_session_id' => $session->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'date' => '2026-03-28',
        'indications' => [],
        'indication_images' => [],
        'tooth_diagnosis_data' => [],
    ]);

    ClinicalOrder::query()->create([
        'clinical_note_id' => $note->id,
        'order_type' => 'xray',
        'status' => ClinicalOrder::STATUS_PENDING,
    ]);

    expect(fn () => app(PatientExamSessionWorkflowService::class)->deleteSession($patient, $session->id))
        ->toThrow(ValidationException::class, 'Phiếu khám đã phát sinh chỉ định/đơn thuốc nên không thể xóa.');

    expect(ExamSession::query()->whereKey($session->id)->exists())->toBeTrue();
});

it('opens an existing session and reports when it already has a persisted clinical note', function (): void {
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();

    $session = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'session_date' => '2026-03-29',
        'status' => ExamSession::STATUS_IN_PROGRESS,
    ]);

    $note = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'exam_session_id' => $session->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'date' => '2026-03-29',
        'indications' => [],
        'indication_images' => [],
        'tooth_diagnosis_data' => [],
    ]);

    $result = app(PatientExamSessionWorkflowService::class)->openSession(
        patient: $patient,
        sessionDate: '2026-03-29',
        doctorId: $doctor->id,
    );

    expect($result['status'])->toBe('existing')
        ->and($result['session']->getKey())->toBe($session->id)
        ->and($result['session']->clinicalNote?->getKey())->toBe($note->id);
});

it('reschedules an editable exam session and syncs its linked clinical note and standalone encounter date', function (): void {
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();

    $visitEpisode = VisitEpisode::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'status' => VisitEpisode::STATUS_SCHEDULED,
        'scheduled_at' => '2026-03-29 09:00:00',
        'planned_duration_minutes' => 30,
        'notes' => 'Exam workflow test',
    ]);

    $session = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'visit_episode_id' => $visitEpisode->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'session_date' => '2026-03-29',
        'status' => ExamSession::STATUS_IN_PROGRESS,
    ]);

    $note = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'exam_session_id' => $session->id,
        'visit_episode_id' => $visitEpisode->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'date' => '2026-03-29',
        'lock_version' => 1,
        'indications' => [],
        'indication_images' => [],
        'tooth_diagnosis_data' => [],
    ]);

    $result = app(PatientExamSessionWorkflowService::class)->rescheduleSession(
        patient: $patient,
        sessionId: $session->id,
        newDate: '2026-03-30',
        actorId: $doctor->id,
    );

    $session->refresh();
    $note->refresh();
    $visitEpisode->refresh();

    expect($result['status'])->toBe('updated')
        ->and($session->session_date?->toDateString())->toBe('2026-03-30')
        ->and($session->updated_by)->toBe($doctor->id)
        ->and($note->date?->toDateString())->toBe('2026-03-30')
        ->and($note->updated_by)->toBe($doctor->id)
        ->and($note->lock_version)->toBe(2)
        ->and($visitEpisode->scheduled_at?->toDateString())->toBe('2026-03-30');
});

it('returns a duplicate outcome when rescheduling an exam session onto an occupied date', function (): void {
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();

    $session = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'session_date' => '2026-03-29',
        'status' => ExamSession::STATUS_IN_PROGRESS,
    ]);

    ExamSession::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'session_date' => '2026-03-30',
        'status' => ExamSession::STATUS_DRAFT,
    ]);

    $result = app(PatientExamSessionWorkflowService::class)->rescheduleSession(
        patient: $patient,
        sessionId: $session->id,
        newDate: '2026-03-30',
        actorId: $doctor->id,
    );

    expect($result['status'])->toBe('duplicate')
        ->and($session->fresh()->session_date?->toDateString())->toBe('2026-03-29');
});

it('refuses to reschedule an exam session once treatment progress exists for the same date', function (): void {
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();

    $session = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'session_date' => '2026-03-31',
        'status' => ExamSession::STATUS_IN_PROGRESS,
    ]);

    TreatmentProgressDay::query()->create([
        'patient_id' => $patient->id,
        'exam_session_id' => $session->id,
        'branch_id' => $patient->first_branch_id,
        'progress_date' => '2026-03-31',
        'status' => TreatmentProgressDay::STATUS_PLANNED,
    ]);

    expect(fn () => app(PatientExamSessionWorkflowService::class)->rescheduleSession(
        patient: $patient,
        sessionId: $session->id,
        newDate: '2026-04-01',
        actorId: $doctor->id,
    ))->toThrow(ValidationException::class, 'Ngày khám đã có tiến trình điều trị nên không thể chỉnh sửa.');
});
