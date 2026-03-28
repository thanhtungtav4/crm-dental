<?php

use App\Models\Branch;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Validation\ValidationException;

it('uses the explicit actor when resolving default branches and query scope', function (): void {
    $branchA = Branch::factory()->create(['active' => true]);
    Branch::factory()->create(['active' => true]);

    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $this->actingAs($admin);

    expect(BranchAccess::defaultBranchIdForUser($manager))
        ->toBe($branchA->id)
        ->and(BranchAccess::scopeBranchQueryForUser(Branch::query(), $manager)->pluck('id')->all())
        ->toBe([$branchA->id])
        ->and(BranchAccess::scopeQueryByUserAccessibleBranches(Branch::query(), $manager, 'id')->pluck('id')->all())
        ->toBe([$branchA->id]);
});

it('asserts branch access against the provided actor instead of the current auth user', function (): void {
    $branchA = Branch::factory()->create(['active' => true]);
    $branchB = Branch::factory()->create(['active' => true]);

    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $this->actingAs($admin);

    expect(fn () => BranchAccess::assertUserCanAccessBranch(
        user: $manager,
        branchId: $branchB->id,
        field: 'branch_id',
        message: 'Ngoài phạm vi.',
    ))->toThrow(ValidationException::class, 'Ngoài phạm vi.');
});
