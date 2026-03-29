<?php

namespace App\Services;

use App\Models\ExamSession;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Collection;

class PatientExamSessionReadModelService
{
    public function __construct(protected PatientExamStatusReadModelService $patientExamStatusReadModelService) {}

    /**
     * @return Collection<int, ExamSession>
     */
    public function sessions(Patient $patient): Collection
    {
        $lockedDates = array_flip($this->patientExamStatusReadModelService->treatmentProgressDates($patient));

        return $patient->examSessions()
            ->with('clinicalNote')
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->get()
            ->each(function (ExamSession $session) use ($lockedDates): void {
                $session->setAttribute('is_locked', $this->resolveLockedState($session, $lockedDates));
            });
    }

    public function isLocked(Patient $patient, ExamSession $session): bool
    {
        return $this->resolveLockedState($session, array_flip(
            $this->patientExamStatusReadModelService->treatmentProgressDates($patient),
        ));
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
