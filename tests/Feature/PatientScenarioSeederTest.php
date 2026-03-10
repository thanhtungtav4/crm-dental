<?php

use App\Models\Appointment;
use App\Models\MasterPatientDuplicate;
use App\Models\MasterPatientMerge;
use App\Models\Note;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\LocalDemoDataSeeder;
use Database\Seeders\PatientScenarioSeeder;

use function Pest\Laravel\seed;

it('creates a merge-ready patient scenario that can be merged and rolled back', function (): void {
    seed(LocalDemoDataSeeder::class);

    $admin = User::query()->where('email', 'admin@demo.ident.test')->firstOrFail();
    $this->actingAs($admin);

    $canonicalPatient = Patient::query()
        ->where('patient_code', PatientScenarioSeeder::CANONICAL_PATIENT_CODE)
        ->firstOrFail();
    $mergedPatient = Patient::query()
        ->where('patient_code', PatientScenarioSeeder::MERGED_PATIENT_CODE)
        ->firstOrFail();
    $appointment = Appointment::query()
        ->where('note', PatientScenarioSeeder::MERGED_APPOINTMENT_NOTE)
        ->firstOrFail();
    $note = Note::query()
        ->where('ticket_key', PatientScenarioSeeder::MERGED_NOTE_TICKET_KEY)
        ->firstOrFail();
    $duplicateCase = MasterPatientDuplicate::query()
        ->where('identity_hash', PatientScenarioSeeder::duplicateIdentityHash())
        ->where('status', MasterPatientDuplicate::STATUS_OPEN)
        ->firstOrFail();

    $this->artisan('mpi:merge', [
        'canonical_patient_id' => $canonicalPatient->id,
        'merged_patient_id' => $mergedPatient->id,
        '--duplicate_case_id' => $duplicateCase->id,
        '--reason' => 'Seeded PAT scenario merge',
    ])->assertSuccessful();

    expect($appointment->fresh()->patient_id)->toBe($canonicalPatient->id)
        ->and($note->fresh()->patient_id)->toBe($canonicalPatient->id)
        ->and($duplicateCase->fresh()->status)->toBe(MasterPatientDuplicate::STATUS_RESOLVED)
        ->and($mergedPatient->fresh()->status)->toBe('inactive');

    $merge = MasterPatientMerge::query()
        ->where('canonical_patient_id', $canonicalPatient->id)
        ->where('merged_patient_id', $mergedPatient->id)
        ->latest('id')
        ->firstOrFail();

    $this->artisan('mpi:merge-rollback', [
        'merge_id' => $merge->id,
        '--note' => 'Seeded PAT scenario rollback',
    ])->assertSuccessful();

    expect($appointment->fresh()->patient_id)->toBe($mergedPatient->id)
        ->and($note->fresh()->patient_id)->toBe($mergedPatient->id)
        ->and($duplicateCase->fresh()->status)->toBe(MasterPatientDuplicate::STATUS_OPEN)
        ->and($mergedPatient->fresh()->status)->toBe('active')
        ->and($merge->fresh()->status)->toBe(MasterPatientMerge::STATUS_ROLLED_BACK);
});
