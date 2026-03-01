<?php

use App\Models\EmrAuditLog;
use App\Models\Patient;
use App\Models\PatientMedicalRecord;
use App\Models\User;

it('writes phi read audit when opening exam treatment tab and medical record edit page', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $admin->forceFill([
        'two_factor_confirmed_at' => now(),
    ])->save();

    $patient = Patient::factory()->create();

    $medicalRecord = PatientMedicalRecord::query()->create([
        'patient_id' => $patient->id,
        'allergies' => ['Penicillin'],
        'additional_notes' => 'Theo dõi huyết áp.',
    ]);

    $this->actingAs($admin)
        ->get(route('filament.admin.resources.patients.view', [
            'record' => $patient,
            'tab' => 'exam-treatment',
        ]))
        ->assertSuccessful();

    $this->actingAs($admin)
        ->get(route('filament.admin.resources.patient-medical-records.edit', [
            'record' => $medicalRecord,
            'patient_id' => $patient->id,
        ]))
        ->assertSuccessful();

    $workspaceRead = EmrAuditLog::query()
        ->where('entity_type', EmrAuditLog::ENTITY_PHI_ACCESS)
        ->where('action', EmrAuditLog::ACTION_READ)
        ->where('patient_id', $patient->id)
        ->where('entity_id', $patient->id)
        ->where('actor_id', $admin->id)
        ->whereJsonContains('context->resource', 'patient_exam_treatment_tab')
        ->first();

    $medicalRecordRead = EmrAuditLog::query()
        ->where('entity_type', EmrAuditLog::ENTITY_PHI_ACCESS)
        ->where('action', EmrAuditLog::ACTION_READ)
        ->where('patient_id', $patient->id)
        ->where('entity_id', $medicalRecord->id)
        ->where('actor_id', $admin->id)
        ->whereJsonContains('context->resource', 'patient_medical_record')
        ->first();

    expect($workspaceRead)->not->toBeNull()
        ->and($medicalRecordRead)->not->toBeNull();
});
