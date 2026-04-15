<?php

namespace App\Services;

use App\Models\ExamSession;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientExamSessionReadModelService
{
    public function __construct(protected PatientExamStatusReadModelService $patientExamStatusReadModelService) {}

    /**
     * @return Collection<int, ExamSession>
     */
    public function sessions(Patient $patient): Collection
    {
        $lockedDates = $this->lockedDates($patient);

        return $this->sessionQuery($patient)
            ->get()
            ->each(fn (ExamSession $session): ExamSession => $this->decorateSession($session, $lockedDates));
    }

    public function latestSession(Patient $patient): ?ExamSession
    {
        $session = $this->sessionQuery($patient)->first();

        if (! $session instanceof ExamSession) {
            return null;
        }

        return $this->decorateSession($session, $this->lockedDates($patient));
    }

    public function findSession(Patient $patient, int $sessionId): ?ExamSession
    {
        $session = $this->sessionQuery($patient)->find($sessionId);

        if (! $session instanceof ExamSession) {
            return null;
        }

        return $this->decorateSession($session, $this->lockedDates($patient));
    }

    public function isLocked(Patient $patient, ExamSession $session): bool
    {
        return $this->resolveLockedState($session, $this->lockedDates($patient));
    }

    protected function sessionQuery(Patient $patient): HasMany
    {
        return $patient->examSessions()
            ->with('clinicalNote')
            ->orderByDesc('session_date')
            ->orderByDesc('id');
    }

    /**
     * @param  array<string, int>  $lockedDates
     */
    protected function decorateSession(ExamSession $session, array $lockedDates): ExamSession
    {
        $session->setAttribute('is_locked', $this->resolveLockedState($session, $lockedDates));

        return $session;
    }

    /**
     * @return array<string, int>
     */
    protected function lockedDates(Patient $patient): array
    {
        return array_flip($this->patientExamStatusReadModelService->treatmentProgressDates($patient));
    }

    /**
     * @param  array<string, int>  $lockedDates
     */
    protected function resolveLockedState(ExamSession $session, array $lockedDates): bool
    {
        if ($session->status === ExamSession::STATUS_LOCKED) {
            return true;
        }

        $sessionDate = $session->session_date?->toDateString();

        return $sessionDate !== null && isset($lockedDates[$sessionDate]);
    }
}
