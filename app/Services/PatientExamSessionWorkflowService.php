<?php

namespace App\Services;

use App\Models\ClinicalNote;
use App\Models\ExamSession;
use App\Models\Patient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PatientExamSessionWorkflowService
{
    public function __construct(
        protected PatientExamStatusReadModelService $patientExamStatusReadModelService,
        protected EncounterService $encounterService,
        protected ExamSessionProvisioningService $examSessionProvisioningService,
        protected ClinicalNoteVersioningService $clinicalNoteVersioningService,
    ) {}

    /**
     * @return array{status: 'opened'|'existing'|'unavailable', session?: ExamSession}
     */
    public function openSession(Patient $patient, string $sessionDate, ?int $doctorId = null): array
    {
        $visitEpisodeId = $this->resolveEncounterIdForDate($patient, $sessionDate, $doctorId);

        $session = $this->examSessionProvisioningService->resolveForPatientOnDate(
            patientId: (int) $patient->getKey(),
            branchId: is_numeric($patient->first_branch_id) ? (int) $patient->first_branch_id : null,
            date: $sessionDate,
            doctorId: $doctorId,
            visitEpisodeId: $visitEpisodeId,
            createIfMissing: true,
        );

        if (! $session instanceof ExamSession) {
            return ['status' => 'unavailable'];
        }

        $session->load('clinicalNote');

        return [
            'status' => $session->clinicalNote instanceof ClinicalNote ? 'existing' : 'opened',
            'session' => $session,
        ];
    }

    /**
     * @return array{status: 'updated', session: ExamSession, clinicalNote: ClinicalNote|null}|array{status: 'duplicate'}|array{status: 'missing'}
     */
    public function rescheduleSession(
        Patient $patient,
        int $sessionId,
        string $newDate,
        ?int $actorId = null,
        ?int $doctorId = null,
    ): array {
        $session = $patient->examSessions()
            ->with('clinicalNote')
            ->find($sessionId);

        if (! $session instanceof ExamSession) {
            return ['status' => 'missing'];
        }

        if ($this->isSessionLockedByProgress($patient, $session)) {
            throw ValidationException::withMessages([
                'session' => 'Ngày khám đã có tiến trình điều trị nên không thể chỉnh sửa.',
            ]);
        }

        $existingSession = $patient->examSessions()
            ->whereDate('session_date', $newDate)
            ->where('id', '!=', $session->id)
            ->first();

        if ($existingSession instanceof ExamSession) {
            return ['status' => 'duplicate'];
        }

        $resolvedActorId = $this->resolveActorId($actorId);
        $visitEpisodeId = $session->visit_episode_id
            ?: $this->resolveEncounterIdForDate($patient, $newDate, $doctorId ?? $resolvedActorId);

        $session->fill([
            'session_date' => $newDate,
            'visit_episode_id' => $visitEpisodeId,
            'updated_by' => $resolvedActorId,
        ]);
        $session->save();

        $updatedClinicalNote = null;

        if ($session->clinicalNote instanceof ClinicalNote) {
            $updatedClinicalNote = $this->clinicalNoteVersioningService->updateWithOptimisticLock(
                clinicalNote: $session->clinicalNote,
                attributes: [
                    'date' => $newDate,
                    'visit_episode_id' => $visitEpisodeId,
                    'updated_by' => $resolvedActorId,
                ],
                expectedVersion: (int) ($session->clinicalNote->lock_version ?: 1),
                actorId: $resolvedActorId,
                operation: 'amend',
                reason: 'session_date_update',
            );
        }

        if ($visitEpisodeId) {
            $this->encounterService->syncStandaloneEncounterDate((int) $visitEpisodeId, $newDate);
        }

        $session->refresh()->load('clinicalNote');

        return [
            'status' => 'updated',
            'session' => $session,
            'clinicalNote' => $updatedClinicalNote,
        ];
    }

    public function deleteSession(Patient $patient, int $sessionId): bool
    {
        $session = $patient->examSessions()
            ->with(['clinicalOrders:id,exam_session_id', 'prescriptions:id,exam_session_id'])
            ->find($sessionId);

        if (! $session instanceof ExamSession) {
            return false;
        }

        if ($this->isSessionLockedByProgress($patient, $session)) {
            throw ValidationException::withMessages([
                'session' => 'Ngày khám đã có tiến trình điều trị nên không thể xóa được.',
            ]);
        }

        if ($session->clinicalOrders->isNotEmpty() || $session->prescriptions->isNotEmpty()) {
            throw ValidationException::withMessages([
                'session' => 'Phiếu khám đã phát sinh chỉ định/đơn thuốc nên không thể xóa.',
            ]);
        }

        ClinicalNote::query()
            ->where('exam_session_id', $session->id)
            ->delete();

        $session->delete();

        return true;
    }

    protected function isSessionLockedByProgress(Patient $patient, ExamSession $session): bool
    {
        if ($session->status === ExamSession::STATUS_LOCKED) {
            return true;
        }

        $sessionDate = $session->session_date?->toDateString();

        if ($sessionDate === null) {
            return false;
        }

        return in_array(
            $sessionDate,
            $this->patientExamStatusReadModelService->treatmentProgressDates($patient),
            true,
        );
    }

    protected function resolveEncounterIdForDate(Patient $patient, string $date, ?int $doctorId = null): ?int
    {
        $encounter = $this->encounterService->resolveForPatientOnDate(
            patientId: (int) $patient->getKey(),
            branchId: is_numeric($patient->first_branch_id) ? (int) $patient->first_branch_id : null,
            date: $date,
            doctorId: $doctorId,
            createIfMissing: true,
        );

        return is_numeric($encounter?->id) ? (int) $encounter->id : null;
    }

    protected function resolveActorId(?int $actorId): ?int
    {
        return $actorId ?? (is_numeric(Auth::id()) ? (int) Auth::id() : null);
    }
}
