<?php

namespace App\Services;

use App\Models\VisitEpisode;
use Illuminate\Support\Carbon;

class EncounterService
{
    public function resolveForPatientOnDate(
        int $patientId,
        ?int $branchId,
        string $date,
        ?int $doctorId = null,
        bool $createIfMissing = true,
    ): ?VisitEpisode {
        $encounterDate = Carbon::parse($date)->toDateString();

        $query = VisitEpisode::query()
            ->where('patient_id', $patientId)
            ->whereDate('scheduled_at', $encounterDate);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $existing = $query
            ->orderByRaw('appointment_id IS NULL')
            ->orderByDesc('scheduled_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        if (! $createIfMissing) {
            return null;
        }

        return VisitEpisode::query()->create([
            'appointment_id' => null,
            'patient_id' => $patientId,
            'doctor_id' => $doctorId,
            'branch_id' => $branchId,
            'status' => VisitEpisode::STATUS_SCHEDULED,
            'scheduled_at' => Carbon::parse($encounterDate)->setTime(9, 0, 0),
            'planned_duration_minutes' => 30,
            'notes' => 'Auto-created from EMR clinical workflow.',
        ]);
    }

    public function syncStandaloneEncounterDate(int $visitEpisodeId, string $date): void
    {
        $encounter = VisitEpisode::query()->find($visitEpisodeId);

        if (! $encounter || $encounter->appointment_id !== null) {
            return;
        }

        $encounter->scheduled_at = Carbon::parse($date)->setTime(9, 0, 0);
        $encounter->save();
    }
}
