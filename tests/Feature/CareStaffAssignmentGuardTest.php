<?php

use App\Models\Branch;
use App\Models\User;
use App\Services\PatientAssignmentAuthorizer;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

it('scopes care staff options and rejects cross branch assignees', function () {
    $branchA = Branch::factory()->create(['active' => true]);
    $branchB = Branch::factory()->create(['active' => true]);

    $managerA = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $managerA->assignRole('Manager');

    $careStaffA = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $careStaffA->assignRole('CSKH');

    $doctorA = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $doctorA->assignRole('Doctor');

    $careStaffB = User::factory()->create([
        'branch_id' => $branchB->id,
    ]);
    $careStaffB->assignRole('CSKH');

    $authorizer = app(PatientAssignmentAuthorizer::class);
    $options = $authorizer->assignableStaffOptions($managerA, $branchA->id);

    expect($options)->toHaveKey($careStaffA->id)
        ->and($options)->toHaveKey($doctorA->id)
        ->and($options)->not->toHaveKey($careStaffB->id);

    expect(fn () => $authorizer->assertAssignableStaffId(
        actor: $managerA,
        staffId: $careStaffB->id,
        branchId: $branchA->id,
        field: 'user_id',
    ))->toThrow(ValidationException::class, 'Nhân sự được chọn không thuộc phạm vi chi nhánh được phép gán.');
});

it('wires branch scoped care staff guard into care relation manager and page filters', function () {
    $relationManager = File::get(app_path('Filament/Resources/Patients/Relations/PatientNotesRelationManager.php'));
    $customerCarePage = File::get(app_path('Filament/Pages/CustomerCare.php'));

    expect($relationManager)
        ->toContain('PatientAssignmentAuthorizer::class')
        ->toContain('assertAssignableStaffId(')
        ->toContain('assignableStaffOptions(')
        ->and($customerCarePage)
        ->toContain('PatientAssignmentAuthorizer::class')
        ->toContain('scopeCareStaffFilterOptions')
        ->toContain("relationship('assignedTo', 'name'");
});
