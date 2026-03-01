<?php

use App\Filament\Resources\PatientMedicalRecords\PatientMedicalRecordResource;
use App\Livewire\PatientExamForm;
use App\Models\Branch;
use App\Models\ClinicalNote;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\PatientMedicalRecord;
use App\Models\User;
use Livewire\Livewire;

it('scopes patient medical record resource and policy by accessible branch', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $customerA = Customer::factory()->create(['branch_id' => $branchA->id]);
    $customerB = Customer::factory()->create(['branch_id' => $branchB->id]);

    $patientA = Patient::factory()->create([
        'customer_id' => $customerA->id,
        'first_branch_id' => $branchA->id,
    ]);
    $patientB = Patient::factory()->create([
        'customer_id' => $customerB->id,
        'first_branch_id' => $branchB->id,
    ]);

    $recordA = PatientMedicalRecord::query()->create([
        'patient_id' => $patientA->id,
        'updated_by' => $manager->id,
    ]);
    $recordB = PatientMedicalRecord::query()->create([
        'patient_id' => $patientB->id,
        'updated_by' => $manager->id,
    ]);

    $this->actingAs($manager);

    $visibleRecordIds = PatientMedicalRecordResource::getEloquentQuery()
        ->pluck('id')
        ->all();

    expect($visibleRecordIds)->toContain($recordA->id)
        ->and($visibleRecordIds)->not->toContain($recordB->id)
        ->and($manager->can('view', $recordA))->toBeTrue()
        ->and($manager->can('view', $recordB))->toBeFalse();
});

it('shows edit medical record link in patient exam when emr already exists', function () {
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $customer = Customer::factory()->create(['branch_id' => $branch->id]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'primary_doctor_id' => $doctor->id,
    ]);

    ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->toDateString(),
        'indications' => [],
        'indication_images' => [],
        'tooth_diagnosis_data' => [],
        'created_by' => $doctor->id,
        'updated_by' => $doctor->id,
    ]);

    $medicalRecord = PatientMedicalRecord::query()->create([
        'patient_id' => $patient->id,
        'allergies' => ['Penicillin'],
        'updated_by' => $doctor->id,
    ]);

    $createUrl = route('filament.admin.resources.patient-medical-records.create', ['patient_id' => $patient->id]);
    $editUrl = route('filament.admin.resources.patient-medical-records.edit', ['record' => $medicalRecord->id]);

    $this->actingAs($doctor);

    Livewire::test(PatientExamForm::class, ['patient' => $patient])
        ->assertSee($editUrl)
        ->assertDontSee($createUrl);
});

it('allows doctor to open create medical record page from patient context', function () {
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $customer = Customer::factory()->create(['branch_id' => $branch->id]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'primary_doctor_id' => $doctor->id,
    ]);

    $this->actingAs($doctor);

    $createUrl = route('filament.admin.resources.patient-medical-records.create', ['patient_id' => $patient->id]);

    $this->get($createUrl)
        ->assertSuccessful();
});

it('shows patient profile header action to open medical record', function () {
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $customer = Customer::factory()->create(['branch_id' => $branch->id]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'primary_doctor_id' => $doctor->id,
    ]);

    $this->actingAs($doctor);

    $patientViewUrl = route('filament.admin.resources.patients.view', [
        'record' => $patient->id,
        'tab' => 'exam-treatment',
    ]);

    $createMedicalRecordUrl = route('filament.admin.resources.patient-medical-records.create', [
        'patient_id' => $patient->id,
    ]);

    $this->get($patientViewUrl)
        ->assertSuccessful()
        ->assertSee('Tạo bệnh án điện tử')
        ->assertSee($createMedicalRecordUrl);

    $medicalRecord = PatientMedicalRecord::query()->create([
        'patient_id' => $patient->id,
        'updated_by' => $doctor->id,
    ]);

    $editMedicalRecordUrl = route('filament.admin.resources.patient-medical-records.edit', [
        'record' => $medicalRecord->id,
        'patient_id' => $patient->id,
    ]);

    $this->get($patientViewUrl)
        ->assertSuccessful()
        ->assertSee('Mở bệnh án điện tử')
        ->assertSee($editMedicalRecordUrl);
});

it('shows back to patient profile action on create and edit medical record pages', function () {
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $customer = Customer::factory()->create(['branch_id' => $branch->id]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'primary_doctor_id' => $doctor->id,
    ]);

    $this->actingAs($doctor);

    $patientProfileUrl = route('filament.admin.resources.patients.view', [
        'record' => $patient->id,
        'tab' => 'exam-treatment',
    ]);

    $this->get(route('filament.admin.resources.patient-medical-records.create', [
        'patient_id' => $patient->id,
    ]))
        ->assertSuccessful()
        ->assertSee('Về hồ sơ bệnh nhân')
        ->assertSee($patientProfileUrl);

    $medicalRecord = PatientMedicalRecord::query()->create([
        'patient_id' => $patient->id,
        'updated_by' => $doctor->id,
    ]);

    $this->get(route('filament.admin.resources.patient-medical-records.edit', [
        'record' => $medicalRecord->id,
        'patient_id' => $patient->id,
    ]))
        ->assertSuccessful()
        ->assertSee('Về hồ sơ bệnh nhân')
        ->assertSee($patientProfileUrl);
});
