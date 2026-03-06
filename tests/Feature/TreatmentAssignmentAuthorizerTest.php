<?php

use App\Models\Branch;
use App\Models\DoctorBranchAssignment;
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
        'branch_id' => $branchA->id,
        'doctor_id' => $doctor->id,
    ]))->toThrow(ValidationException::class, 'Bác sĩ được chọn không thuộc phạm vi chi nhánh');
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
