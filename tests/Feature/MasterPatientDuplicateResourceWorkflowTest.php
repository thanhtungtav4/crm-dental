<?php

use App\Filament\Resources\MasterPatientDuplicates\MasterPatientDuplicateResource;
use App\Filament\Resources\MasterPatientDuplicates\Pages\ListMasterPatientDuplicates;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\DoctorBranchAssignment;
use App\Models\MasterPatientDuplicate;
use App\Models\MasterPatientMerge;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function makePatientForBranch(Branch $branch, string $fullName, string $phone, ?string $email = null): Patient
{
    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'full_name' => $fullName,
        'phone' => $phone,
        'email' => $email,
    ]);

    return Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'full_name' => $fullName,
        'phone' => $phone,
        'email' => $email,
        'status' => 'active',
    ]);
}

it('forbids doctors from accessing the MPI duplicate queue resource', function (): void {
    $doctor = User::factory()->create();
    $doctor->assignRole('Doctor');

    $this->actingAs($doctor)
        ->get(MasterPatientDuplicateResource::getUrl('index'))
        ->assertForbidden();
});

it('shows only branch-overlapping cases to a manager and hides review actions without full branch coverage', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $branchC = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $patientA = makePatientForBranch($branchA, 'Patient A', '0901000001');
    $patientB = makePatientForBranch($branchB, 'Patient B', '0901000001', 'a@example.test');
    $patientC = makePatientForBranch($branchC, 'Patient C', '0901000002', 'c@example.test');

    $visibleCase = MasterPatientDuplicate::factory()->create([
        'patient_id' => $patientB->id,
        'branch_id' => $branchB->id,
        'identity_type' => 'phone',
        'identity_value' => '0901000001',
        'matched_patient_ids' => [$patientA->id, $patientB->id],
        'matched_branch_ids' => [$branchA->id, $branchB->id],
        'metadata' => ['patient_count' => 2, 'branch_count' => 2],
    ]);

    $hiddenCase = MasterPatientDuplicate::factory()->create([
        'patient_id' => $patientC->id,
        'branch_id' => $branchC->id,
        'identity_type' => 'phone',
        'identity_value' => '0901000002',
        'matched_patient_ids' => [$patientB->id, $patientC->id],
        'matched_branch_ids' => [$branchB->id, $branchC->id],
        'metadata' => ['patient_count' => 2, 'branch_count' => 2],
    ]);

    $this->actingAs($manager);

    Livewire::test(ListMasterPatientDuplicates::class)
        ->assertCanSeeTableRecords([$visibleCase])
        ->assertCanNotSeeTableRecords([$hiddenCase])
        ->assertTableActionHidden('mergeCase', $visibleCase)
        ->assertTableActionHidden('ignoreCase', $visibleCase)
        ->assertTableActionHidden('rollbackLatestMerge', $visibleCase);
});

it('allows a manager with full branch coverage to merge and ignore MPI duplicate cases from the queue', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    DoctorBranchAssignment::query()->create([
        'user_id' => $manager->id,
        'branch_id' => $branchB->id,
        'is_primary' => false,
        'is_active' => true,
        'assigned_from' => null,
        'assigned_until' => null,
    ]);

    $canonicalPatient = makePatientForBranch($branchA, 'Canonical Patient', '0902000001');
    $mergedPatient = makePatientForBranch($branchB, 'Merged Patient', '0902000001', 'merge@example.test');
    $ignoreCanonicalPatient = makePatientForBranch($branchA, 'Ignore Canonical', '0902000011', 'mpi-review@example.test');
    $ignoreMergedPatient = makePatientForBranch($branchB, 'Ignore Merged', '0902000012', 'mpi-review@example.test');

    $mergeCase = MasterPatientDuplicate::query()->updateOrCreate(
        [
            'identity_type' => 'phone',
            'identity_hash' => hash('sha256', 'phone|0902000001'),
            'status' => MasterPatientDuplicate::STATUS_OPEN,
        ],
        [
            'patient_id' => $mergedPatient->id,
            'branch_id' => $branchB->id,
            'identity_value' => '0902000001',
            'matched_patient_ids' => [$canonicalPatient->id, $mergedPatient->id],
            'matched_branch_ids' => [$branchA->id, $branchB->id],
            'metadata' => ['patient_count' => 2, 'branch_count' => 2],
        ],
    );

    $ignoreCase = MasterPatientDuplicate::query()->updateOrCreate(
        [
            'identity_type' => 'email',
            'identity_hash' => hash('sha256', 'email|mpi-review@example.test'),
            'status' => MasterPatientDuplicate::STATUS_OPEN,
        ],
        [
            'patient_id' => $ignoreCanonicalPatient->id,
            'branch_id' => $branchA->id,
            'identity_value' => 'mpi-review@example.test',
            'matched_patient_ids' => [$ignoreCanonicalPatient->id, $ignoreMergedPatient->id],
            'matched_branch_ids' => [$branchA->id, $branchB->id],
            'metadata' => ['patient_count' => 2, 'branch_count' => 2],
        ],
    );

    $this->actingAs($manager);

    Livewire::test(ListMasterPatientDuplicates::class)
        ->assertTableActionVisible('mergeCase', $mergeCase)
        ->callTableAction('mergeCase', $mergeCase, [
            'canonical_patient_id' => $canonicalPatient->id,
            'merged_patient_id' => $mergedPatient->id,
            'reason' => 'Duplicate cross-branch from resource queue',
        ])
        ->assertHasNoActionErrors();

    expect($mergeCase->fresh()->status)->toBe(MasterPatientDuplicate::STATUS_RESOLVED)
        ->and(MasterPatientMerge::query()
            ->where('duplicate_case_id', $mergeCase->id)
            ->where('canonical_patient_id', $canonicalPatient->id)
            ->where('merged_patient_id', $mergedPatient->id)
            ->where('status', MasterPatientMerge::STATUS_APPLIED)
            ->exists())->toBeTrue();

    Livewire::test(ListMasterPatientDuplicates::class)
        ->assertTableActionVisible('ignoreCase', $ignoreCase)
        ->callTableAction('ignoreCase', $ignoreCase, [
            'note' => 'Reviewed and accepted as acceptable duplicate noise',
        ])
        ->assertHasNoActionErrors();

    $ignoredCase = $ignoreCase->fresh();

    expect($ignoredCase->status)->toBe(MasterPatientDuplicate::STATUS_IGNORED)
        ->and($ignoredCase->review_note)->toContain('acceptable duplicate noise')
        ->and(AuditLog::query()
            ->where('entity_type', AuditLog::ENTITY_MASTER_PATIENT_DUPLICATE)
            ->where('entity_id', $ignoreCase->id)
            ->where('action', AuditLog::ACTION_RESOLVE)
            ->where('metadata->status_to', MasterPatientDuplicate::STATUS_IGNORED)
            ->exists())->toBeTrue();
});

it('blocks direct duplicate case status mutations outside the workflow service', function (): void {
    $duplicateCase = MasterPatientDuplicate::factory()->create([
        'status' => MasterPatientDuplicate::STATUS_OPEN,
    ]);

    expect(fn () => $duplicateCase->update([
        'status' => MasterPatientDuplicate::STATUS_IGNORED,
    ]))->toThrow(
        ValidationException::class,
        'Trang thai duplicate case chi duoc thay doi qua MasterPatientDuplicateWorkflowService.',
    );

    expect($duplicateCase->fresh()->status)->toBe(MasterPatientDuplicate::STATUS_OPEN);
});

it('allows an authorized manager to rollback the latest applied merge from the duplicate queue', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    DoctorBranchAssignment::query()->create([
        'user_id' => $manager->id,
        'branch_id' => $branchB->id,
        'is_primary' => false,
        'is_active' => true,
        'assigned_from' => null,
        'assigned_until' => null,
    ]);

    $canonicalPatient = makePatientForBranch($branchA, 'Canonical Rollback', '0903000001');
    $mergedPatient = makePatientForBranch($branchB, 'Merged Rollback', '0903000001', 'rollback@example.test');

    $duplicateCase = MasterPatientDuplicate::query()->updateOrCreate(
        [
            'identity_type' => 'phone',
            'identity_hash' => hash('sha256', 'phone|0903000001'),
            'status' => MasterPatientDuplicate::STATUS_OPEN,
        ],
        [
            'patient_id' => $mergedPatient->id,
            'branch_id' => $branchB->id,
            'identity_value' => '0903000001',
            'matched_patient_ids' => [$canonicalPatient->id, $mergedPatient->id],
            'matched_branch_ids' => [$branchA->id, $branchB->id],
            'metadata' => ['patient_count' => 2, 'branch_count' => 2],
        ],
    );

    $this->actingAs($manager);

    $this->artisan('mpi:merge', [
        'canonical_patient_id' => $canonicalPatient->id,
        'merged_patient_id' => $mergedPatient->id,
        '--duplicate_case_id' => $duplicateCase->id,
        '--reason' => 'Merged before rollback test',
    ])->assertSuccessful();

    Livewire::test(ListMasterPatientDuplicates::class)
        ->assertCanSeeTableRecords([$duplicateCase->fresh()])
        ->assertTableActionVisible('rollbackLatestMerge', $duplicateCase->fresh())
        ->callTableAction('rollbackLatestMerge', $duplicateCase->fresh(), [
            'note' => 'Rollback from MPI queue resource',
        ])
        ->assertHasNoActionErrors();

    $latestMerge = MasterPatientMerge::query()->latest('id')->firstOrFail();

    expect($duplicateCase->fresh()->status)->toBe(MasterPatientDuplicate::STATUS_OPEN)
        ->and($latestMerge->status)->toBe(MasterPatientMerge::STATUS_ROLLED_BACK)
        ->and($latestMerge->rollback_note)->toContain('MPI queue resource')
        ->and(AuditLog::query()
            ->where('entity_type', AuditLog::ENTITY_MASTER_PATIENT_MERGE)
            ->where('entity_id', $latestMerge->id)
            ->where('action', AuditLog::ACTION_ROLLBACK)
            ->exists())->toBeTrue();
});

it('marks stale open mpi cases as needing triage and leaves fresh or reviewed cases normal', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    DoctorBranchAssignment::query()->create([
        'user_id' => $manager->id,
        'branch_id' => $branchB->id,
        'is_primary' => false,
        'is_active' => true,
        'assigned_from' => null,
        'assigned_until' => null,
    ]);

    $patientA = makePatientForBranch($branchA, 'Priority Patient A', '0904000001');
    $patientB = makePatientForBranch($branchB, 'Priority Patient B', '0904000001', 'priority@example.test');

    $staleCase = MasterPatientDuplicate::factory()->create([
        'patient_id' => $patientB->id,
        'branch_id' => $branchB->id,
        'identity_type' => 'phone',
        'identity_value' => '0904000001',
        'matched_patient_ids' => [$patientA->id, $patientB->id],
        'matched_branch_ids' => [$branchA->id, $branchB->id],
        'metadata' => ['patient_count' => 2, 'branch_count' => 2],
        'created_at' => now()->subDays(MasterPatientDuplicate::STALE_OPEN_CASE_DAYS + 1),
        'updated_at' => now()->subDays(MasterPatientDuplicate::STALE_OPEN_CASE_DAYS + 1),
    ]);

    $freshCase = MasterPatientDuplicate::factory()->create([
        'patient_id' => $patientB->id,
        'branch_id' => $branchB->id,
        'identity_type' => 'email',
        'identity_hash' => hash('sha256', 'email|priority@example.test'),
        'identity_value' => 'priority@example.test',
        'matched_patient_ids' => [$patientA->id, $patientB->id],
        'matched_branch_ids' => [$branchA->id, $branchB->id],
        'metadata' => ['patient_count' => 2, 'branch_count' => 2],
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $resolvedCase = MasterPatientDuplicate::factory()->resolved()->create([
        'patient_id' => $patientB->id,
        'branch_id' => $branchB->id,
        'identity_type' => 'cccd',
        'identity_hash' => hash('sha256', 'cccd|012345678901'),
        'identity_value' => '012345678901',
        'matched_patient_ids' => [$patientA->id, $patientB->id],
        'matched_branch_ids' => [$branchA->id, $branchB->id],
        'metadata' => ['patient_count' => 2, 'branch_count' => 2],
        'created_at' => now()->subDays(MasterPatientDuplicate::STALE_OPEN_CASE_DAYS + 2),
        'updated_at' => now()->subHours(2),
    ]);

    expect($staleCase->isStaleOpenCase())->toBeTrue()
        ->and($freshCase->isStaleOpenCase())->toBeFalse()
        ->and($resolvedCase->isStaleOpenCase())->toBeFalse();

    $this->actingAs($manager);

    Livewire::test(ListMasterPatientDuplicates::class)
        ->assertCanSeeTableRecords([$staleCase, $freshCase, $resolvedCase])
        ->assertSee('Cần ưu tiên')
        ->assertSee('Bình thường')
        ->assertSee('Quá 3 ngày');
});
