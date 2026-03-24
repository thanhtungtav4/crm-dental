<?php

use App\Models\Branch;
use App\Models\DoctorBranchAssignment;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

it('allows only assignable doctors on prescriptions for the selected branch', function (): void {
    $branchA = Branch::factory()->create(['active' => true]);
    $branchB = Branch::factory()->create(['active' => true]);

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $allowedDoctor = User::factory()->create([
        'branch_id' => $branchA->id,
        'name' => 'Allowed Prescription Doctor',
    ]);
    $allowedDoctor->assignRole('Doctor');

    $assignedDoctor = User::factory()->create([
        'branch_id' => $branchB->id,
        'name' => 'Assigned Prescription Doctor',
    ]);
    $assignedDoctor->assignRole('Doctor');

    DoctorBranchAssignment::query()->create([
        'user_id' => $assignedDoctor->id,
        'branch_id' => $branchA->id,
        'is_active' => true,
        'is_primary' => false,
        'assigned_from' => today()->toDateString(),
        'created_by' => $manager->id,
    ]);

    $outsideDoctor = User::factory()->create([
        'branch_id' => $branchB->id,
        'name' => 'Outside Prescription Doctor',
    ]);
    $outsideDoctor->assignRole('Doctor');

    $patient = Patient::factory()->create([
        'first_branch_id' => $branchA->id,
    ]);

    $this->actingAs($manager);

    $prescription = Prescription::create([
        'patient_id' => $patient->id,
        'branch_id' => $branchA->id,
        'doctor_id' => $allowedDoctor->id,
        'treatment_date' => now(),
    ]);

    expect($prescription->doctor_id)->toBe($allowedDoctor->id);

    $assignedPrescription = Prescription::create([
        'patient_id' => $patient->id,
        'branch_id' => $branchA->id,
        'doctor_id' => $assignedDoctor->id,
        'treatment_date' => now(),
    ]);

    expect($assignedPrescription->doctor_id)->toBe($assignedDoctor->id);

    expect(fn () => Prescription::create([
        'patient_id' => $patient->id,
        'branch_id' => $branchA->id,
        'doctor_id' => $outsideDoctor->id,
        'treatment_date' => now(),
    ]))->toThrow(ValidationException::class, 'Bác sĩ được chọn không thuộc phạm vi chi nhánh được phép gán.');
});

it('wires branch scoped doctor guards into prescription forms and doctor filters', function (): void {
    $prescriptionsRelationManager = File::get(app_path('Filament/Resources/Patients/RelationManagers/PrescriptionsRelationManager.php'));
    $prescriptionModel = File::get(app_path('Models/Prescription.php'));
    $customerCarePage = File::get(app_path('Filament/Pages/CustomerCare.php'));
    $treatmentPlansTable = File::get(app_path('Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php'));

    expect($prescriptionsRelationManager)
        ->toContain('PatientAssignmentAuthorizer::class')
        ->toContain('assignableDoctorOptions(')
        ->toContain('assertAssignableDoctorId(')
        ->toContain('mutateFormDataUsing(fn (array $data): array => $this->sanitizePrescriptionData($data))')
        ->and($prescriptionModel)
        ->toContain('PatientAssignmentAuthorizer::class')
        ->toContain('assertAssignableDoctorId(')
        ->and($customerCarePage)
        ->toContain('scopeDoctorFilterOptions')
        ->toContain("relationship('doctor', 'name', fn (Builder \$query): Builder => \$this->scopeDoctorFilterOptions(\$query))")
        ->and($treatmentPlansTable)
        ->toContain('PatientAssignmentAuthorizer::class')
        ->toContain("relationship('doctor', 'name', fn (Builder \$query): Builder => app(PatientAssignmentAuthorizer::class)->scopeAssignableDoctors(");
});
