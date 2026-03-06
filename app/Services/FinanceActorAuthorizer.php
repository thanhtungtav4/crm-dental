<?php

namespace App\Services;

use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class FinanceActorAuthorizer
{
    /**
     * @return array<int, string>
     */
    public function assignableReceiverOptions(?User $actor, ?int $branchId = null): array
    {
        return $this->scopeAssignableReceivers(User::query(), $actor, $branchId)
            ->pluck('name', 'id')
            ->mapWithKeys(fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])
            ->all();
    }

    public function scopeAssignableReceivers(Builder $query, ?User $actor, ?int $branchId = null): Builder
    {
        if (Schema::hasColumn('users', 'status')) {
            $query->where(function (Builder $statusQuery): void {
                $statusQuery->whereNull('status')
                    ->orWhere('status', true);
            });
        }

        if (! $actor instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($actor->hasRole('Admin')) {
            return $this->applyBranchScope($query, $branchId !== null ? [$branchId] : null)
                ->orderBy('name');
        }

        $branchIds = $this->resolveAllowedBranchIds($actor, $branchId);

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $this->applyBranchScope($query, $branchIds)
            ->orderBy('name');
    }

    public function sanitizeReceivedBy(
        ?User $actor,
        ?int $receivedBy,
        ?int $branchId,
        string $field = 'received_by',
    ): ?int {
        if (! $actor instanceof User) {
            return $receivedBy;
        }

        $candidateId = $receivedBy ?? (is_numeric($actor->getKey()) ? (int) $actor->getKey() : null);

        if ($candidateId === null) {
            return null;
        }

        $isAssignable = $this->scopeAssignableReceivers(
            query: User::query()->whereKey($candidateId),
            actor: $actor,
            branchId: $branchId,
        )->exists();

        if ($isAssignable) {
            return $candidateId;
        }

        throw ValidationException::withMessages([
            $field => 'Nguoi nhan duoc chon khong thuoc pham vi chi nhanh duoc phep hach toan.',
        ]);
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    protected function applyBranchScope(Builder $query, ?array $branchIds): Builder
    {
        if ($branchIds === null) {
            return $query;
        }

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $userQuery) use ($branchIds): void {
            $userQuery
                ->whereIn('branch_id', $branchIds)
                ->orWhereHas('activeDoctorBranchAssignments', function (Builder $assignmentQuery) use ($branchIds): void {
                    $assignmentQuery->whereIn('branch_id', $branchIds);
                });
        });
    }

    /**
     * @return list<int>
     */
    protected function resolveAllowedBranchIds(User $actor, ?int $branchId = null): array
    {
        $accessibleBranchIds = BranchAccess::accessibleBranchIds($actor, false);

        if ($accessibleBranchIds === []) {
            return [];
        }

        if ($branchId === null) {
            return $accessibleBranchIds;
        }

        return in_array($branchId, $accessibleBranchIds, true)
            ? [$branchId]
            : [];
    }
}
