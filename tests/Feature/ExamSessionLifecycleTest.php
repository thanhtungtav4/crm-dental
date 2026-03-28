<?php

use App\Livewire\PatientExamForm;
use App\Models\ClinicalNote;
use App\Models\ClinicalOrder;
use App\Models\ExamSession;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('provisions exam session when a clinical note is created', function (): void {
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();

    $note = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'date' => '2026-03-01',
        'general_exam_notes' => 'Bệnh nhân đau răng hàm dưới.',
        'indications' => ['panorama'],
        'indication_images' => [],
        'tooth_diagnosis_data' => [],
        'created_by' => $doctor->id,
        'updated_by' => $doctor->id,
    ]);

    $note->refresh();
    $session = ExamSession::query()->find($note->exam_session_id);

    expect($note->exam_session_id)->not->toBeNull()
        ->and($session)->not->toBeNull()
        ->and((int) $session->patient_id)->toBe((int) $patient->id)
        ->and($session->session_date?->toDateString())->toBe('2026-03-01')
        ->and($session->status)->toBe(ExamSession::STATUS_IN_PROGRESS);
});

it('reuses the same exam session when clinical notes are created twice on the same day', function (): void {
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();

    $firstNote = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'date' => '2026-03-01',
        'general_exam_notes' => 'Phiếu khám đầu tiên.',
        'indications' => [],
        'indication_images' => [],
        'tooth_diagnosis_data' => [],
        'created_by' => $doctor->id,
        'updated_by' => $doctor->id,
    ]);

    $secondNote = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'date' => '2026-03-01',
        'general_exam_notes' => 'Phiếu khám bổ sung.',
        'indications' => [],
        'indication_images' => [],
        'tooth_diagnosis_data' => [],
        'created_by' => $doctor->id,
        'updated_by' => $doctor->id,
    ]);

    expect($firstNote->exam_session_id)->not->toBeNull()
        ->and((int) $secondNote->exam_session_id)->toBe((int) $firstNote->exam_session_id)
        ->and(ExamSession::query()->where('patient_id', $patient->id)->count())->toBe(1);
});

it('blocks direct exam session status mutations outside the workflow service', function (): void {
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();

    $session = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'doctor_id' => $doctor->id,
        'session_date' => '2026-03-08',
        'status' => ExamSession::STATUS_DRAFT,
    ]);

    expect(fn () => $session->forceFill([
        'status' => ExamSession::STATUS_LOCKED,
    ])->save())->toThrow(ValidationException::class, 'EXAM_SESSION_STATE_INVALID');
});

it('blocks deleting an exam session when clinical order exists', function (): void {
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create([
        'branch_id' => $patient->first_branch_id,
    ]);
    $doctor->assignRole('Doctor');

    $this->actingAs($doctor);

    Livewire::test(PatientExamForm::class, ['patient' => $patient])
        ->set('newSessionDate', '2026-03-02')
        ->call('createSession');

    $session = ExamSession::query()->where('patient_id', $patient->id)->firstOrFail();
    $note = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'exam_session_id' => $session->id,
        'visit_episode_id' => $session->visit_episode_id,
        'date' => '2026-03-02',
        'general_exam_notes' => 'Đã bắt đầu khám, không được xóa phiên khi có chỉ định.',
        'indications' => [],
        'indication_images' => [],
        'tooth_diagnosis_data' => [],
        'created_by' => $doctor->id,
        'updated_by' => $doctor->id,
    ]);

    ClinicalOrder::query()->create([
        'clinical_note_id' => $note->id,
        'order_type' => 'xray',
        'status' => ClinicalOrder::STATUS_PENDING,
    ]);

    Livewire::test(PatientExamForm::class, ['patient' => $patient])
        ->call('setActiveSession', $session->id)
        ->call('deleteSession', $session->id);

    expect(ExamSession::query()->whereKey($session->id)->exists())->toBeTrue();
});

it('infers exam session id for order and prescription records', function (): void {
    $doctor = User::factory()->create();
    $patient = Patient::factory()->create();

    $note = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'date' => '2026-03-03',
        'indications' => [],
        'indication_images' => [],
        'tooth_diagnosis_data' => [],
    ]);

    $note->refresh();

    $order = ClinicalOrder::query()->create([
        'clinical_note_id' => $note->id,
        'order_type' => 'lab',
        'status' => ClinicalOrder::STATUS_PENDING,
    ]);

    $prescription = Prescription::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'prescription_code' => Prescription::generatePrescriptionCode(),
        'prescription_name' => 'Đơn thuốc test',
        'treatment_date' => '2026-03-03',
    ]);

    expect((int) $order->exam_session_id)->toBe((int) $note->exam_session_id)
        ->and((int) $prescription->exam_session_id)->toBe((int) $note->exam_session_id);
});
