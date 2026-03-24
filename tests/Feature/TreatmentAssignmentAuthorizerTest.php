<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\DoctorBranchAssignment;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\TreatmentAssignmentAuthorizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('scopes treatment doctors and staff to the selected branch', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $staffA = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $staffA->assignRole('CSKH');

    $staffB = User::factory()->create([
        'branch_id' => $branchB->id,
    ]);
    $staffB->assignRole('CSKH');

    $doctorA = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $doctorA->assignRole('Doctor');

    $doctorB = User::factory()->create([
        'branch_id' => $branchB->id,
    ]);
    $doctorB->assignRole('Doctor');

    $authorizer = app(TreatmentAssignmentAuthorizer::class);

    $doctorOptions = $authorizer->assignableDoctorOptions($manager, $branchA->id);
    $staffOptions = $authorizer->assignableStaffOptions($manager, $branchA->id);

    expect($doctorOptions)->toHaveKey($doctorA->id)
        ->and($doctorOptions)->not->toHaveKey($doctorB->id)
        ->and($staffOptions)->toHaveKey($staffA->id)
        ->and($staffOptions)->toHaveKey($doctorA->id)
        ->and($staffOptions)->not->toHaveKey($staffB->id)
        ->and($staffOptions)->not->toHaveKey($doctorB->id);
});

it('allows doctors assigned to the selected branch for treatment flows', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $doctor = User::factory()->create([
        'branch_id' => $branchB->id,
    ]);
    $doctor->assignRole('Doctor');

    DoctorBranchAssignment::query()->create([
        'user_id' => $doctor->id,
        'branch_id' => $branchA->id,
        'is_active' => true,
        'is_primary' => false,
        'assigned_from' => today()->toDateString(),
        'created_by' => $manager->id,
    ]);

    $doctorOptions = app(TreatmentAssignmentAuthorizer::class)->assignableDoctorOptions($manager, $branchA->id);

    expect($doctorOptions)->toHaveKey($doctor->id);
});

it('rejects cross branch doctor payloads for treatment plans', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $customerA = Customer::factory()->create(['branch_id' => $branchA->id]);
    $patientA = Patient::factory()->create([
        'customer_id' => $customerA->id,
        'first_branch_id' => $branchA->id,
        'full_name' => $customerA->full_name,
        'phone' => $customerA->phone,
        'email' => $customerA->email,
    ]);

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $doctor = User::factory()->create([
        'branch_id' => $branchB->id,
    ]);
    $doctor->assignRole('Doctor');

    expect(fn () => app(TreatmentAssignmentAuthorizer::class)->sanitizeTreatmentPlanFormData($manager, [
        'patient_id' => $patientA->id,
        'branch_id' => $branchA->id,
        'doctor_id' => $doctor->id,
    ]))->toThrow(ValidationException::class, 'Bác sĩ được chọn không thuộc phạm vi chi nhánh');
});

it('rejects out of scope patient payloads and scopes treatment plans by accessible branches', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $customerA = Customer::factory()->create(['branch_id' => $branchA->id]);
    $patientA = Patient::factory()->create([
        'customer_id' => $customerA->id,
        'first_branch_id' => $branchA->id,
        'full_name' => $customerA->full_name,
        'phone' => $customerA->phone,
        'email' => $customerA->email,
    ]);

    $customerB = Customer::factory()->create(['branch_id' => $branchB->id]);
    $patientB = Patient::factory()->create([
        'customer_id' => $customerB->id,
        'first_branch_id' => $branchB->id,
        'full_name' => $customerB->full_name,
        'phone' => $customerB->phone,
        'email' => $customerB->email,
    ]);

    $planA = TreatmentPlan::factory()->create([
        'patient_id' => $patientA->id,
        'branch_id' => $branchA->id,
    ]);
    $planB = TreatmentPlan::factory()->create([
        'patient_id' => $patientB->id,
        'branch_id' => $branchB->id,
    ]);

    $this->actingAs($manager);

    $authorizer = app(TreatmentAssignmentAuthorizer::class);

    expect(fn () => $authorizer->sanitizeTreatmentPlanFormData($manager, [
        'patient_id' => $patientB->id,
        'branch_id' => $branchA->id,
        'doctor_id' => null,
    ]))->toThrow(ValidationException::class, 'Bệnh nhân được chọn không thuộc phạm vi chi nhánh')
        ->and($authorizer->findAccessiblePatient($manager, $patientA->id)?->id)->toBe($patientA->id)
        ->and($authorizer->findAccessiblePatient($manager, $patientB->id))->toBeNull()
        ->and($authorizer->scopeAccessibleTreatmentPlans(TreatmentPlan::query(), $manager)->pluck('id')->all())->toContain($planA->id)
        ->and($authorizer->scopeAccessibleTreatmentPlans(TreatmentPlan::query(), $manager)->pluck('id')->all())->not->toContain($planB->id);
});

it('rejects cross branch staff payloads for treatment sessions', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $assistant = User::factory()->create([
        'branch_id' => $branchB->id,
    ]);
    $assistant->assignRole('CSKH');

    expect(fn () => app(TreatmentAssignmentAuthorizer::class)->sanitizeTreatmentSessionFormData(
        actor: $manager,
        data: [
            'doctor_id' => null,
            'assistant_id' => $assistant->id,
        ],
        branchId: $branchA->id,
    ))->toThrow(ValidationException::class, 'Nhân sự được chọn không thuộc phạm vi chi nhánh');
});

it('locks treatment material actor to the authenticated staff', function (): void {
    $branch = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $foreignStaff = User::factory()->create();
    $foreignStaff->assignRole('Manager');

    $data = app(TreatmentAssignmentAuthorizer::class)->sanitizeTreatmentMaterialUsageData(
        actor: $manager,
        data: ['used_by' => $foreignStaff->id],
        branchId: $branch->id,
    );

    expect($data['used_by'])->toBe($manager->id);
});
