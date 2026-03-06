<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\DoctorBranchAssignment;
use App\Models\User;
use App\Services\PatientAssignmentAuthorizer;
use Illuminate\Validation\ValidationException;

it('scopes assignable staff and doctors to the selected branch', function (): void {
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

    $staffOptions = app(PatientAssignmentAuthorizer::class)->assignableStaffOptions($manager, $branchA->id);
    $doctorOptions = app(PatientAssignmentAuthorizer::class)->assignableDoctorOptions($manager, $branchA->id);

    expect($staffOptions)->toHaveKey($staffA->id)
        ->and($staffOptions)->toHaveKey($doctorA->id)
        ->and($staffOptions)->not->toHaveKey($staffB->id)
        ->and($staffOptions)->not->toHaveKey($doctorB->id)
        ->and($doctorOptions)->toHaveKey($doctorA->id)
        ->and($doctorOptions)->not->toHaveKey($doctorB->id);
});

it('allows doctors assigned to another home branch when they are explicitly assigned to the selected branch', function (): void {
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

    $doctorOptions = app(PatientAssignmentAuthorizer::class)->assignableDoctorOptions($manager, $branchA->id);

    expect($doctorOptions)->toHaveKey($doctor->id);
});

it('rejects cross branch assignee payloads for customer forms', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $foreignStaff = User::factory()->create([
        'branch_id' => $branchB->id,
    ]);
    $foreignStaff->assignRole('CSKH');

    expect(fn () => app(PatientAssignmentAuthorizer::class)->sanitizeCustomerFormData($manager, [
        'branch_id' => $branchA->id,
        'assigned_to' => $foreignStaff->id,
        'full_name' => 'Lead PAT',
        'phone' => '0901888999',
        'email' => 'lead@example.com',
    ]))->toThrow(ValidationException::class, 'Nhân sự được chọn không thuộc phạm vi chi nhánh');
});

it('rejects cross branch doctor payloads for patient forms', function (): void {
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

    expect(fn () => app(PatientAssignmentAuthorizer::class)->sanitizePatientFormData($manager, [
        'first_branch_id' => $branchA->id,
        'primary_doctor_id' => $doctor->id,
        'full_name' => 'Patient PAT',
        'phone' => '0901777888',
    ]))->toThrow(ValidationException::class, 'Bác sĩ được chọn không thuộc phạm vi chi nhánh');
});

it('enforces customer contact uniqueness using search hashes during save sanitization', function (): void {
    $branch = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    Customer::factory()->create([
        'branch_id' => $branch->id,
        'phone' => '0901666777',
        'email' => 'hash@example.com',
    ]);

    expect(fn () => app(PatientAssignmentAuthorizer::class)->sanitizeCustomerFormData($manager, [
        'branch_id' => $branch->id,
        'full_name' => 'Lead Duplicate',
        'phone' => '+84 901 666 777',
        'email' => 'HASH@example.com',
    ]))->toThrow(ValidationException::class, 'Email đã tồn tại trong hệ thống.');
});
