<?php

use App\Models\Branch;
use App\Models\User;
use App\Services\UserProvisioningAuthorizer;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('allows admin to assign scoped branches roles and direct permissions', function () {
    $branchA = Branch::factory()->create(['active' => true]);
    $branchB = Branch::factory()->create(['active' => true]);

    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $doctorRoleId = Role::query()->where('name', 'Doctor')->value('id');
    $patientPermissionId = Permission::query()->where('name', 'ViewAny:Patient')->value('id');

    $payload = app(UserProvisioningAuthorizer::class)->sanitizeFormData($admin, [
        'branch_id' => $branchA->id,
        'doctor_branch_ids' => [$branchA->id, $branchB->id, $branchA->id],
        'roles' => [$doctorRoleId],
        'permissions' => [$patientPermissionId],
    ]);

    expect($payload['branch_id'])->toBe($branchA->id)
        ->and($payload['doctor_branch_ids'])->toBe([$branchA->id, $branchB->id])
        ->and($payload['roles'])->toBe([$doctorRoleId])
        ->and($payload['permissions'])->toBe([$patientPermissionId]);
});

it('scopes assignable branches to the actor branch access', function () {
    $branchA = Branch::factory()->create(['active' => true]);
    $branchB = Branch::factory()->create(['active' => true]);

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $options = app(UserProvisioningAuthorizer::class)->assignableBranchOptions($manager);

    expect($options)->toHaveKey($branchA->id)
        ->and($options)->not->toHaveKey($branchB->id);
});

it('blocks non admin role assignment in provisioning payload', function () {
    $branch = Branch::factory()->create(['active' => true]);

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $doctorRoleId = Role::query()->where('name', 'Doctor')->value('id');

    expect(fn () => app(UserProvisioningAuthorizer::class)->sanitizeFormData($manager, [
        'branch_id' => $branch->id,
        'doctor_branch_ids' => [$branch->id],
        'roles' => [$doctorRoleId],
        'permissions' => [],
    ]))->toThrow(ValidationException::class, 'Chỉ Admin được phép gán vai trò');
});

it('blocks non admin direct permission assignment in provisioning payload', function () {
    $branch = Branch::factory()->create(['active' => true]);

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $patientPermissionId = Permission::query()->where('name', 'ViewAny:Patient')->value('id');

    expect(fn () => app(UserProvisioningAuthorizer::class)->sanitizeFormData($manager, [
        'branch_id' => $branch->id,
        'doctor_branch_ids' => [$branch->id],
        'roles' => [],
        'permissions' => [$patientPermissionId],
    ]))->toThrow(ValidationException::class, 'Chỉ Admin được phép gán quyền trực tiếp');
});

it('blocks assigning inaccessible branches in provisioning payload', function () {
    $branchA = Branch::factory()->create(['active' => true]);
    $branchB = Branch::factory()->create(['active' => true]);

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    expect(fn () => app(UserProvisioningAuthorizer::class)->sanitizeFormData($manager, [
        'branch_id' => $branchB->id,
        'doctor_branch_ids' => [$branchB->id],
        'roles' => [],
        'permissions' => [],
    ]))->toThrow(ValidationException::class, 'ngoài phạm vi được phép');
});
