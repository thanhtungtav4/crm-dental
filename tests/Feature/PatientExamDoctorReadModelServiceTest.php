<?php

use App\Models\Branch;
use App\Models\DoctorBranchAssignment;
use App\Models\User;
use App\Services\PatientExamDoctorReadModelService;

it('returns only assignable doctors for the actor and branch, with optional search', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $allowedDoctor = User::factory()->create([
        'branch_id' => $branchA->id,
        'name' => 'Allowed Doctor',
    ]);
    $allowedDoctor->assignRole('Doctor');

    $assignedDoctor = User::factory()->create([
        'branch_id' => $branchB->id,
        'name' => 'Assigned Doctor',
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
        'name' => 'Outside Doctor',
    ]);
    $outsideDoctor->assignRole('Doctor');

    $service = app(PatientExamDoctorReadModelService::class);

    $allOptions = $service->options(
        actor: $manager,
        branchId: $branchA->id,
    );

    $filteredOptions = $service->options(
        actor: $manager,
        branchId: $branchA->id,
        search: 'Assigned',
    );

    expect($allOptions->pluck('id')->all())
        ->toContain($allowedDoctor->id, $assignedDoctor->id)
        ->not->toContain($outsideDoctor->id)
        ->and($filteredOptions->pluck('id')->all())
        ->toBe([$assignedDoctor->id]);
});

it('finds only doctors that are assignable in the current branch scope and resolves names by id', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $allowedDoctor = User::factory()->create([
        'branch_id' => $branchA->id,
        'name' => 'Allowed Doctor',
    ]);
    $allowedDoctor->assignRole('Doctor');

    $outsideDoctor = User::factory()->create([
        'branch_id' => $branchB->id,
        'name' => 'Outside Doctor',
    ]);
    $outsideDoctor->assignRole('Doctor');

    $service = app(PatientExamDoctorReadModelService::class);

    expect($service->find($manager, $branchA->id, $allowedDoctor->id)?->id)->toBe($allowedDoctor->id)
        ->and($service->find($manager, $branchA->id, $outsideDoctor->id))->toBeNull()
        ->and($service->name($allowedDoctor->id))->toBe('Allowed Doctor')
        ->and($service->name(null))->toBe('');
});
