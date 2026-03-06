<?php

namespace App\Services;

use App\Models\ExamSession;
use App\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ExamSessionProvisioningService
{
    public function resolveForPatientOnDate(
        int $patientId,
        ?int $branchId,
        string $date,
        ?int $doctorId = null,
        ?int $visitEpisodeId = null,
        bool $createIfMissing = true,
    ): ?ExamSession {
        return DB::transaction(function () use (
            $patientId,
            $branchId,
            $date,
            $doctorId,
            $visitEpisodeId,
            $createIfMissing
        ): ?ExamSession {
            Patient::query()
                ->lockForUpdate()
                ->findOrFail($patientId);

            $sessionDate = Carbon::parse($date)->toDateString();

            $query = ExamSession::query()
                ->where('patient_id', $patientId)
                ->whereDate('session_date', $sessionDate);

            if ($branchId !== null) {
                $query->where('branch_id', $branchId);
            }

            $existing = $query
                ->lockForUpdate()
                ->orderByRaw('visit_episode_id IS NULL')
                ->orderByDesc('id')
                ->first();

            if ($existing) {
                $existing->fill([
                    'visit_episode_id' => $existing->visit_episode_id ?: $visitEpisodeId,
                    'doctor_id' => $existing->doctor_id ?: $doctorId,
                    'branch_id' => $existing->branch_id ?: $branchId,
                ]);

                if ($existing->isDirty()) {
                    $existing->save();
                }

                return $existing->fresh();
            }

            if (! $createIfMissing) {
                return null;
            }

            return ExamSession::query()->create([
                'patient_id' => $patientId,
                'visit_episode_id' => $visitEpisodeId,
                'branch_id' => $branchId,
                'doctor_id' => $doctorId,
                'session_date' => $sessionDate,
                'status' => ExamSession::STATUS_DRAFT,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
        }, 3);
    }
}
