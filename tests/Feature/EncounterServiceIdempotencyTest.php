<?php

use App\Models\Patient;
use App\Services\EncounterService;

it('returns the same standalone encounter for repeated resolve calls on the same patient date and branch', function (): void {
    $patient = Patient::factory()->create();

    $firstEncounter = app(EncounterService::class)->resolveForPatientOnDate(
        patientId: (int) $patient->id,
        branchId: $patient->first_branch_id ? (int) $patient->first_branch_id : null,
        date: '2026-03-18',
        doctorId: null,
        createIfMissing: true,
    );

    $secondEncounter = app(EncounterService::class)->resolveForPatientOnDate(
        patientId: (int) $patient->id,
        branchId: $patient->first_branch_id ? (int) $patient->first_branch_id : null,
        date: '2026-03-18',
        doctorId: null,
        createIfMissing: true,
    );

    expect($firstEncounter)->not->toBeNull()
        ->and($secondEncounter)->not->toBeNull()
        ->and((int) $secondEncounter?->id)->toBe((int) $firstEncounter?->id)
        ->and($patient->visitEpisodes()->whereDate('scheduled_at', '2026-03-18')->count())->toBe(1);
});
