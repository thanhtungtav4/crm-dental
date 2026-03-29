<?php

namespace App\Services;

use App\Models\ClinicalNote;
use App\Models\ExamSession;
use App\Models\Patient;
use App\Models\User;

class PatientExamClinicalNoteWorkflowService
{
    public function __construct(
        protected EncounterService $encounterService,
        protected ClinicalNoteVersioningService $clinicalNoteVersioningService,
    ) {}

    public function draftForSession(Patient $patient, ExamSession $session, ?User $actor = null): ClinicalNote
    {
        $defaultDoctorId = $this->resolveDefaultDoctorIdForSession($session, $actor);

        return new ClinicalNote([
            'exam_session_id' => $session->id,
            'patient_id' => $patient->id,
            'visit_episode_id' => $session->visit_episode_id,
            'doctor_id' => $session->doctor_id ?: $defaultDoctorId,
            'branch_id' => $session->branch_id ?: $patient->first_branch_id,
            'date' => $session->session_date?->toDateString() ?? now()->toDateString(),
            'examining_doctor_id' => $defaultDoctorId,
            'treating_doctor_id' => $defaultDoctorId,
            'indications' => [],
            'indication_images' => [],
            'tooth_diagnosis_data' => [],
            'other_diagnosis' => '',
        ]);
    }

    /**
     * @param  array{
     *     examining_doctor_id?: ?int,
     *     treating_doctor_id?: ?int,
     *     general_exam_notes?: ?string,
     *     treatment_plan_note?: ?string,
     *     indications?: array<int, string>,
     *     indication_images?: array<string, array<int, string>>,
     *     tooth_diagnosis_data?: array<mixed>,
     *     other_diagnosis?: ?string,
     *     updated_by?: ?int
     * }  $data
     * @return array<string, mixed>
     */
    public function buildPayload(
        Patient $patient,
        ?ClinicalNote $clinicalNote,
        ?ExamSession $session,
        array $data,
        ?int $actorId = null,
    ): array {
        $noteDate = $clinicalNote?->date?->toDateString()
            ?? $session?->session_date?->toDateString()
            ?? now()->toDateString();

        $visitEpisodeId = $clinicalNote?->visit_episode_id
            ?: $session?->visit_episode_id;

        if (! $visitEpisodeId) {
            $visitEpisodeId = $this->resolveEncounterIdForDate(
                patient: $patient,
                date: $noteDate,
                doctorId: isset($data['examining_doctor_id']) && is_numeric($data['examining_doctor_id'])
                    ? (int) $data['examining_doctor_id']
                    : $actorId,
            );
        }

        return [
            'visit_episode_id' => $visitEpisodeId,
            'examining_doctor_id' => $data['examining_doctor_id'] ?? null,
            'treating_doctor_id' => $data['treating_doctor_id'] ?? null,
            'general_exam_notes' => $data['general_exam_notes'] ?? null,
            'treatment_plan_note' => $data['treatment_plan_note'] ?? null,
            'indications' => array_values($data['indications'] ?? []),
            'indication_images' => $data['indication_images'] ?? [],
            'tooth_diagnosis_data' => $data['tooth_diagnosis_data'] ?? [],
            'other_diagnosis' => $data['other_diagnosis'] ?? null,
            'updated_by' => $data['updated_by'] ?? $actorId,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function ensurePersisted(
        Patient $patient,
        ExamSession $session,
        ?ClinicalNote $clinicalNote,
        array $payload,
        ?User $actor = null,
        ?int $actorId = null,
    ): ?ClinicalNote {
        if ($clinicalNote?->exists) {
            return $clinicalNote;
        }

        $existingNote = $patient->clinicalNotes()
            ->where('exam_session_id', $session->id)
            ->first();

        if ($existingNote instanceof ClinicalNote) {
            return $existingNote;
        }

        return $patient->clinicalNotes()->create(array_merge(
            [
                'exam_session_id' => $session->id,
                'patient_id' => $patient->id,
                'doctor_id' => $session->doctor_id ?: $this->resolveDefaultDoctorIdForSession($session, $actor),
                'branch_id' => $session->branch_id ?: $patient->first_branch_id,
                'date' => $session->session_date?->toDateString() ?? now()->toDateString(),
                'created_by' => $actorId,
            ],
            $payload,
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(
        ClinicalNote $clinicalNote,
        array $payload,
        int $expectedVersion,
        ?int $actorId = null,
        string $operation = 'update',
        ?string $reason = null,
    ): ClinicalNote {
        return $this->clinicalNoteVersioningService->updateWithOptimisticLock(
            clinicalNote: $clinicalNote,
            attributes: $payload,
            expectedVersion: $expectedVersion,
            actorId: $actorId,
            operation: $operation,
            reason: $reason,
        );
    }

    /**
     * @param  array{
     *     examining_doctor_id?: ?int,
     *     treating_doctor_id?: ?int,
     *     general_exam_notes?: ?string,
     *     treatment_plan_note?: ?string,
     *     indications?: array<int, string>,
     *     indication_images?: array<string, array<int, string>>,
     *     tooth_diagnosis_data?: array<mixed>,
     *     other_diagnosis?: ?string,
     *     updated_by?: ?int
     * }  $data
     * @return array{clinicalNote:?ClinicalNote,operation:'created'|'updated'}
     */
    public function saveForSession(
        Patient $patient,
        ExamSession $session,
        ?ClinicalNote $clinicalNote,
        array $data,
        int $expectedVersion = 1,
        ?User $actor = null,
        ?int $actorId = null,
    ): array {
        $payload = $this->buildPayload(
            patient: $patient,
            clinicalNote: $clinicalNote,
            session: $session,
            data: $data,
            actorId: $actorId,
        );

        if (! $clinicalNote?->exists) {
            return [
                'clinicalNote' => $this->ensurePersisted(
                    patient: $patient,
                    session: $session,
                    clinicalNote: $clinicalNote,
                    payload: $payload,
                    actor: $actor,
                    actorId: $actorId,
                ),
                'operation' => 'created',
            ];
        }

        return [
            'clinicalNote' => $this->update(
                clinicalNote: $clinicalNote,
                payload: $payload,
                expectedVersion: $expectedVersion,
                actorId: $actorId,
            ),
            'operation' => 'updated',
        ];
    }

    protected function resolveDefaultDoctorIdForSession(ExamSession $session, ?User $actor = null): ?int
    {
        if (is_numeric($session->doctor_id)) {
            return (int) $session->doctor_id;
        }

        if ($actor instanceof User && $actor->hasRole('Doctor')) {
            return $actor->getKey();
        }

        return null;
    }

    protected function resolveEncounterIdForDate(Patient $patient, string $date, ?int $doctorId = null): ?int
    {
        $encounter = $this->encounterService->resolveForPatientOnDate(
            patientId: (int) $patient->id,
            branchId: is_numeric($patient->first_branch_id) ? (int) $patient->first_branch_id : null,
            date: $date,
            doctorId: $doctorId,
            createIfMissing: true,
        );

        return is_numeric($encounter?->id) ? (int) $encounter->id : null;
    }
}
