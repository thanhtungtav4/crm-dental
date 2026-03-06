<?php

use App\Models\Branch;
use App\Models\ClinicalNote;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\PatientMedicalRecord;
use App\Models\User;
use App\Models\VisitEpisode;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

it('blocks assigning outside-branch doctors when saving a clinical note', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $actor = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $actor->assignRole('Doctor');

    $outsideDoctor = User::factory()->create([
        'branch_id' => $branchB->id,
    ]);
    $outsideDoctor->assignRole('Doctor');

    $customer = Customer::factory()->create([
        'branch_id' => $branchA->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branchA->id,
        'primary_doctor_id' => $actor->id,
    ]);

    $encounter = VisitEpisode::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $actor->id,
        'branch_id' => $branchA->id,
        'status' => VisitEpisode::STATUS_SCHEDULED,
        'scheduled_at' => now()->toDateTimeString(),
        'planned_duration_minutes' => 30,
    ]);

    $this->actingAs($actor);

    expect(fn () => ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'visit_episode_id' => $encounter->id,
        'branch_id' => $branchA->id,
        'doctor_id' => $actor->id,
        'examining_doctor_id' => $outsideDoctor->id,
        'date' => now()->toDateString(),
        'general_exam_notes' => 'Khám lâm sàng.',
        'created_by' => $actor->id,
        'updated_by' => $actor->id,
    ]))->toThrow(ValidationException::class, 'phạm vi chi nhánh');
});

it('removes delete surfaces from clinical note and patient medical record UI', function () {
    $clinicalNotePhp = File::get(app_path('Filament/Resources/Patients/RelationManagers/ClinicalNotesRelationManager.php'));
    $medicalRecordTablePhp = File::get(app_path('Filament/Resources/PatientMedicalRecords/Tables/PatientMedicalRecordsTable.php'));
    $medicalRecordEditPhp = File::get(app_path('Filament/Resources/PatientMedicalRecords/Pages/EditPatientMedicalRecord.php'));

    expect($clinicalNotePhp)->not->toContain('DeleteAction::make()')
        ->and($clinicalNotePhp)->not->toContain('DeleteBulkAction::make()')
        ->and($medicalRecordTablePhp)->not->toContain('DeleteBulkAction::make()')
        ->and($medicalRecordEditPhp)->not->toContain('DeleteAction::make()');
});

it('denies deleting clinical notes and patient medical records via policy', function () {
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'primary_doctor_id' => $doctor->id,
    ]);

    $encounter = VisitEpisode::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'status' => VisitEpisode::STATUS_SCHEDULED,
        'scheduled_at' => now()->toDateTimeString(),
        'planned_duration_minutes' => 30,
    ]);

    $note = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'visit_episode_id' => $encounter->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->toDateString(),
        'general_exam_notes' => 'Khám ban đầu.',
        'created_by' => $doctor->id,
        'updated_by' => $doctor->id,
    ]);

    $medicalRecord = PatientMedicalRecord::query()->create([
        'patient_id' => $patient->id,
        'updated_by' => $doctor->id,
    ]);

    expect($doctor->can('delete', $note))->toBeFalse()
        ->and($doctor->can('delete', $medicalRecord))->toBeFalse();
});
