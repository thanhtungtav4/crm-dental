<?php

use App\Filament\Resources\BranchLogs\BranchLogResource;
use App\Models\Branch;
use App\Models\BranchLog;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

it('makes the branch log resource read-only and removes create edit routes', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $branchLog = BranchLog::factory()->create();

    expect(Route::has('filament.admin.resources.branch-logs.create'))->toBeFalse()
        ->and(Route::has('filament.admin.resources.branch-logs.edit'))->toBeFalse()
        ->and(array_keys(BranchLogResource::getPages()))->toBe(['index'])
        ->and(BranchLogResource::canCreate())->toBeFalse()
        ->and(BranchLogResource::canEdit($branchLog))->toBeFalse()
        ->and(BranchLogResource::canDelete($branchLog))->toBeFalse()
        ->and(BranchLogResource::canDeleteAny())->toBeFalse();

    $this->actingAs($admin)
        ->get(route('filament.admin.resources.branch-logs.index'))
        ->assertOk();
});

it('scopes branch log visibility to accessible branches for delegated viewers', function (): void {
    $accessibleBranch = Branch::factory()->create(['active' => true]);
    $otherBranch = Branch::factory()->create(['active' => true]);

    $manager = User::factory()->create([
        'branch_id' => $accessibleBranch->id,
    ]);
    $manager->assignRole('Manager');

    Permission::findOrCreate('ViewAny:BranchLog', 'web');
    Permission::findOrCreate('View:BranchLog', 'web');
    $manager->givePermissionTo(['ViewAny:BranchLog', 'View:BranchLog']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $visiblePatient = Patient::factory()->create([
        'first_branch_id' => $accessibleBranch->id,
    ]);
    $hiddenPatient = Patient::factory()->create([
        'first_branch_id' => $otherBranch->id,
    ]);

    $visibleBranchLog = BranchLog::factory()->create([
        'patient_id' => $visiblePatient->id,
        'from_branch_id' => $accessibleBranch->id,
        'to_branch_id' => $otherBranch->id,
    ]);

    $hiddenBranchLog = BranchLog::factory()->create([
        'patient_id' => $hiddenPatient->id,
        'from_branch_id' => $otherBranch->id,
        'to_branch_id' => $otherBranch->id,
    ]);

    $this->actingAs($manager);

    expect(BranchLogResource::getEloquentQuery()->pluck('branch_logs.id')->all())
        ->toContain($visibleBranchLog->id)
        ->not->toContain($hiddenBranchLog->id);

    expect($manager->can('view', $visibleBranchLog))->toBeTrue()
        ->and($manager->can('view', $hiddenBranchLog))->toBeFalse();
});

it('blocks updating and deleting branch logs after creation', function (): void {
    $branchLog = BranchLog::factory()->create();

    expect(fn () => $branchLog->update([
        'note' => 'attempted manual mutation',
    ]))->toThrow(ValidationException::class, 'không cho phép cập nhật');

    expect(fn () => $branchLog->delete())
        ->toThrow(ValidationException::class, 'không cho phép xóa');
});
