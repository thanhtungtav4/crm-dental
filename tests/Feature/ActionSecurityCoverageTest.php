<?php

use App\Models\User;
use App\Support\ActionPermission;
use App\Support\SensitiveActionRegistry;
use Illuminate\Support\Facades\File;

it('keeps sensitive action registry in sync with action permissions', function () {
    $registryPermissions = array_keys(SensitiveActionRegistry::definitions());
    $matrixAnchors = [
        ActionPermission::PAYMENT_REVERSAL,
        ActionPermission::APPOINTMENT_OVERRIDE,
        ActionPermission::PLAN_APPROVAL,
        ActionPermission::AUTOMATION_RUN,
        ActionPermission::MASTER_DATA_SYNC,
        ActionPermission::INSURANCE_CLAIM_DECISION,
        ActionPermission::MPI_DEDUPE_REVIEW,
        ActionPermission::PATIENT_BRANCH_TRANSFER,
    ];

    expect($matrixAnchors)->toEqualCanonicalizing(ActionPermission::all())
        ->and($registryPermissions)->toEqualCanonicalizing($matrixAnchors);
});

it('keeps anti bypass checklist markers valid', function () {
    foreach (SensitiveActionRegistry::definitions() as $permission => $definition) {
        $guardMarkers = (array) data_get($definition, 'guard_markers', []);
        $testMarkers = (array) data_get($definition, 'authorization_test_markers', []);

        expect($guardMarkers)->not->toBeEmpty("Thiếu guard markers cho {$permission}");
        expect($testMarkers)->not->toBeEmpty("Thiếu auth test markers cho {$permission}");

        foreach (array_merge($guardMarkers, $testMarkers) as $marker) {
            $path = base_path((string) data_get($marker, 'path'));
            $contains = (string) data_get($marker, 'contains');

            expect(File::exists($path))->toBeTrue("Marker path không tồn tại: {$path}");

            $contents = File::get($path);
            expect(str_contains($contents, $contains))
                ->toBeTrue("Không tìm thấy marker `{$contains}` trong {$path}");
        }
    }
});

it('enforces role action matrix for all sensitive actions', function (string $permission, array $allowedRoles) {
    $roleUsers = collect(['Admin', 'Manager', 'Doctor', 'CSKH'])
        ->mapWithKeys(function (string $role): array {
            $user = User::factory()->create();
            $user->assignRole($role);

            return [$role => $user];
        });

    foreach ($roleUsers as $role => $user) {
        $isAllowed = in_array($role, $allowedRoles, true);

        expect($user->can($permission))
            ->toBe($isAllowed, "Matrix lệch cho permission {$permission} / role {$role}");
    }
})->with(function (): array {
    return collect(SensitiveActionRegistry::roleMatrix())
        ->map(fn (array $allowedRoles, string $permission): array => [$permission, $allowedRoles])
        ->values()
        ->all();
});

it('passes strict security checklist review command', function () {
    $this->artisan('security:review-sensitive-actions', ['--strict' => true])
        ->expectsOutputToContain('Action:PaymentReversal')
        ->expectsOutputToContain('Action:MpiDedupeReview')
        ->expectsOutputToContain('Action:PatientBranchTransfer')
        ->assertSuccessful();
});
