<?php

use App\Models\ClinicalNote;
use App\Models\ExamSession;
use App\Models\Patient;
use App\Models\User;
use App\Services\PatientExamClinicalNoteWorkflowService;

it('builds an unsaved draft clinical note with fallback doctor context', function (): void {
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create([
        'branch_id' => $patient->first_branch_id,
    ]);
    $doctor->assignRole('Doctor');

    $session = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'session_date' => '2026-04-02',
        'status' => ExamSession::STATUS_DRAFT,
    ]);

    $draft = app(PatientExamClinicalNoteWorkflowService::class)->draftForSession(
        patient: $patient,
        session: $session,
        actor: $doctor,
    );

    expect($draft->exists)->toBeFalse()
        ->and($draft->patient_id)->toBe($patient->id)
        ->and($draft->exam_session_id)->toBe($session->id)
        ->and($draft->doctor_id)->toBe($doctor->id)
        ->and($draft->examining_doctor_id)->toBe($doctor->id)
        ->and($draft->treating_doctor_id)->toBe($doctor->id)
        ->and($draft->date?->toDateString())->toBe('2026-04-02');
});

it('persists an exam clinical note with resolved encounter payload', function (): void {
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create([
        'branch_id' => $patient->first_branch_id,
    ]);
    $doctor->assignRole('Doctor');
    $this->actingAs($doctor);

    $session = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'session_date' => '2026-04-03',
        'status' => ExamSession::STATUS_IN_PROGRESS,
    ]);

    $service = app(PatientExamClinicalNoteWorkflowService::class);
    $payload = $service->buildPayload(
        patient: $patient,
        clinicalNote: null,
        session: $session,
        data: [
            'examining_doctor_id' => $doctor->id,
            'treating_doctor_id' => $doctor->id,
            'general_exam_notes' => 'Khám kiểm tra tổng quát',
            'treatment_plan_note' => 'Theo dõi thêm',
            'indications' => ['panorama'],
            'indication_images' => ['panorama' => ['patients/demo/pano.png']],
            'tooth_diagnosis_data' => ['11' => ['Caries']],
            'other_diagnosis' => 'Theo dõi viêm tủy.',
        ],
        actorId: $doctor->id,
    );

    $note = $service->ensurePersisted(
        patient: $patient,
        session: $session,
        clinicalNote: null,
        payload: $payload,
        actor: $doctor,
        actorId: $doctor->id,
    );

    expect($note)->not->toBeNull()
        ->and($note?->exists)->toBeTrue()
        ->and($note?->visit_episode_id)->not->toBeNull()
        ->and((string) $note?->general_exam_notes)->toBe('Khám kiểm tra tổng quát')
        ->and((string) $note?->other_diagnosis)->toBe('Theo dõi viêm tủy.')
        ->and($note?->examining_doctor_id)->toBe($doctor->id)
        ->and($note?->treating_doctor_id)->toBe($doctor->id)
        ->and($note?->indications)->toBe(['panorama']);
});

it('reuses the persisted clinical note for the same exam session', function (): void {
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create([
        'branch_id' => $patient->first_branch_id,
    ]);
    $doctor->assignRole('Doctor');
    $this->actingAs($doctor);

    $session = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'session_date' => '2026-04-04',
        'status' => ExamSession::STATUS_IN_PROGRESS,
    ]);

    $existingNote = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'exam_session_id' => $session->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'date' => '2026-04-04',
        'general_exam_notes' => 'Đã tồn tại',
        'indications' => [],
        'indication_images' => [],
        'tooth_diagnosis_data' => [],
    ]);

    $note = app(PatientExamClinicalNoteWorkflowService::class)->ensurePersisted(
        patient: $patient,
        session: $session,
        clinicalNote: null,
        payload: [
            'general_exam_notes' => 'Không được ghi đè',
        ],
        actor: $doctor,
        actorId: $doctor->id,
    );

    expect($note?->getKey())->toBe($existingNote->id)
        ->and((string) $existingNote->fresh()?->general_exam_notes)->toBe('Đã tồn tại');
});

it('updates a persisted clinical note through optimistic lock workflow', function (): void {
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create([
        'branch_id' => $patient->first_branch_id,
    ]);
    $doctor->assignRole('Doctor');
    $this->actingAs($doctor);

    $session = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'session_date' => '2026-04-05',
        'status' => ExamSession::STATUS_IN_PROGRESS,
    ]);

    $note = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'exam_session_id' => $session->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'date' => '2026-04-05',
        'general_exam_notes' => 'Phiên bản 1',
        'indications' => [],
        'indication_images' => [],
        'tooth_diagnosis_data' => [],
        'lock_version' => 1,
    ]);

    $updated = app(PatientExamClinicalNoteWorkflowService::class)->update(
        clinicalNote: $note,
        payload: [
            'general_exam_notes' => 'Phiên bản 2',
            'updated_by' => $doctor->id,
        ],
        expectedVersion: 1,
        actorId: $doctor->id,
    );

    expect((string) $updated->general_exam_notes)->toBe('Phiên bản 2')
        ->and((int) $updated->lock_version)->toBe(2);
});

it('saves a session clinical note through the workflow service and reports whether it created or updated', function (): void {
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create([
        'branch_id' => $patient->first_branch_id,
    ]);
    $doctor->assignRole('Doctor');

    $session = ExamSession::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'session_date' => '2026-04-10',
        'status' => ExamSession::STATUS_IN_PROGRESS,
    ]);

    $service = app(PatientExamClinicalNoteWorkflowService::class);

    $created = $service->saveForSession(
        patient: $patient,
        session: $session,
        clinicalNote: null,
        data: [
            'examining_doctor_id' => $doctor->id,
            'treating_doctor_id' => $doctor->id,
            'general_exam_notes' => 'Initial exam notes',
            'treatment_plan_note' => null,
            'indications' => ['panorama'],
            'indication_images' => ['panorama' => []],
            'tooth_diagnosis_data' => [],
            'other_diagnosis' => null,
            'updated_by' => $doctor->id,
        ],
        expectedVersion: 1,
        actor: $doctor,
        actorId: $doctor->id,
    );

    $createdNote = $created['clinicalNote'] ?? null;

    expect($created['operation'])->toBe('created')
        ->and($createdNote)->toBeInstanceOf(ClinicalNote::class)
        ->and($createdNote?->exists)->toBeTrue()
        ->and((string) $createdNote?->general_exam_notes)->toBe('Initial exam notes');

    $updated = $service->saveForSession(
        patient: $patient,
        session: $session,
        clinicalNote: $createdNote,
        data: [
            'examining_doctor_id' => $doctor->id,
            'treating_doctor_id' => $doctor->id,
            'general_exam_notes' => 'Updated exam notes',
            'treatment_plan_note' => 'Updated plan note',
            'indications' => ['panorama'],
            'indication_images' => ['panorama' => []],
            'tooth_diagnosis_data' => [],
            'other_diagnosis' => null,
            'updated_by' => $doctor->id,
        ],
        expectedVersion: (int) ($createdNote?->lock_version ?: 1),
        actor: $doctor,
        actorId: $doctor->id,
    );

    $updatedNote = $updated['clinicalNote'] ?? null;

    expect($updated['operation'])->toBe('updated')
        ->and((string) $updatedNote?->general_exam_notes)->toBe('Updated exam notes')
        ->and((string) $updatedNote?->treatment_plan_note)->toBe('Updated plan note');
});
