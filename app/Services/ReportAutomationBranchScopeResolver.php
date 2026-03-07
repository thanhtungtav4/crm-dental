<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Validation\ValidationException;

class ReportAutomationBranchScopeResolver
{
    /**
     * @return array<int, int|null>
     */
    public function resolveSnapshotBranchIds(?User $actor, ?int $requestedBranchId): array
    {
        if ($requestedBranchId !== null) {
            $this->assertBranchInScope($actor, $requestedBranchId);

            return [$requestedBranchId];
        }

        if ($this->runsWithGlobalScope($actor)) {
            $branchIds = Branch::query()
                ->pluck('id')
                ->map(static fn (mixed $branchId): int => (int) $branchId)
                ->values()
                ->all();

            array_unshift($branchIds, null);

            return $branchIds;
        }

        $accessibleBranchIds = BranchAccess::accessibleBranchIds($actor, true);

        if ($accessibleBranchIds === []) {
            throw ValidationException::withMessages([
                'branch_id' => 'Tai khoan khong co chi nhanh hop le de chay report automation.',
            ]);
        }

        return $accessibleBranchIds;
    }

    /**
     * @return array<int, int>|null
     */
    public function resolveQueryBranchIds(?User $actor, ?int $requestedBranchId): ?array
    {
        if ($requestedBranchId !== null) {
            $this->assertBranchInScope($actor, $requestedBranchId);

            return [$requestedBranchId];
        }

        if ($this->runsWithGlobalScope($actor)) {
            return null;
        }

        $accessibleBranchIds = BranchAccess::accessibleBranchIds($actor, true);

        if ($accessibleBranchIds === []) {
            throw ValidationException::withMessages([
                'branch_id' => 'Tai khoan khong co chi nhanh hop le de chay report automation.',
            ]);
        }

        return $accessibleBranchIds;
    }

    protected function runsWithGlobalScope(?User $actor): bool
    {
        return ! $actor instanceof User
            || $actor->hasRole('Admin')
            || $actor->hasRole('AutomationService');
    }

    protected function assertBranchInScope(?User $actor, int $branchId): void
    {
        if (! $actor instanceof User || $this->runsWithGlobalScope($actor)) {
            return;
        }

        BranchAccess::assertCanAccessBranch(
            $branchId,
            field: 'branch_id',
            message: 'Ban khong co quyen chay report automation cho chi nhanh nay.',
        );
    }
}
