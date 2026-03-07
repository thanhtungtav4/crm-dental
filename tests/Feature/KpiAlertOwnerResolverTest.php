<?php

use App\Models\Branch;
use App\Models\DoctorBranchAssignment;
use App\Models\User;
use App\Services\KpiAlertOwnerResolver;

it('prefers a manager directly assigned to the snapshot branch', function (): void {
    $branch = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $resolver = app(KpiAlertOwnerResolver::class);

    expect($resolver->resolve($branch->id))->toBe($manager->id);
});

it('resolves a branch-assigned manager before falling back to admin', function (): void {
    $branch = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => null,
    ]);
    $manager->assignRole('Manager');

    DoctorBranchAssignment::query()->create([
        'user_id' => $manager->id,
        'branch_id' => $branch->id,
        'is_active' => true,
        'is_primary' => false,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $resolver = app(KpiAlertOwnerResolver::class);

    expect($resolver->resolve($branch->id))->toBe($manager->id);
});

it('falls back to admin instead of an unrelated manager from another branch', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $otherBranchManager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $otherBranchManager->assignRole('Manager');

    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $resolver = app(KpiAlertOwnerResolver::class);

    expect($resolver->resolve($branchB->id))->toBe($admin->id);
});
