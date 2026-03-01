<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class BranchAccess
{
    public static function currentUser(): ?User
    {
        $authUser = auth()->user();

        return $authUser instanceof User ? $authUser : null;
    }

    public static function defaultBranchIdForCurrentUser(bool $activeOnly = true): ?int
    {
        $authUser = static::currentUser();

        if (! $authUser instanceof User) {
            return null;
        }

        if ($authUser->hasRole('Admin')) {
            $query = Branch::query();

            if ($activeOnly) {
                $query->where('active', true);
            }

            $branchId = $query->orderBy('name')->value('id');

            return $branchId !== null ? (int) $branchId : null;
        }

        $branchIds = static::accessibleBranchIds($authUser, $activeOnly);
        if ($branchIds === []) {
            return null;
        }

        $preferredBranchId = $authUser->branch_id !== null ? (int) $authUser->branch_id : null;

        if ($preferredBranchId !== null && in_array($preferredBranchId, $branchIds, true)) {
            return $preferredBranchId;
        }

        return $branchIds[0] ?? null;
    }

    /**
     * @return array<int, int>
     */
    public static function accessibleBranchIds(?User $user = null, bool $activeOnly = true): array
    {
        $authUser = $user ?? static::currentUser();

        if (! $authUser instanceof User) {
            return [];
        }

        if ($authUser->hasRole('Admin')) {
            $query = Branch::query();

            if ($activeOnly) {
                $query->where('active', true);
            }

            return $query
                ->pluck('id')
                ->map(static fn (mixed $branchId): int => (int) $branchId)
                ->values()
                ->all();
        }

        $branchIds = collect($authUser->accessibleBranchIds())
            ->map(static fn (mixed $branchId): int => (int) $branchId)
            ->filter(static fn (int $branchId): bool => $branchId > 0)
            ->unique()
            ->values();

        if (! $activeOnly) {
            return $branchIds->all();
        }

        if ($branchIds->isEmpty()) {
            return [];
        }

        return Branch::query()
            ->whereIn('id', $branchIds->all())
            ->where('active', true)
            ->pluck('id')
            ->map(static fn (mixed $branchId): int => (int) $branchId)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function branchOptionsForCurrentUser(bool $activeOnly = true): array
    {
        $query = Branch::query()->orderBy('name');

        static::scopeBranchQueryForCurrentUser($query, $activeOnly);

        return $query
            ->pluck('name', 'id')
            ->mapWithKeys(static fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])
            ->all();
    }

    public static function scopeBranchQueryForCurrentUser(Builder $query, bool $activeOnly = true): Builder
    {
        $authUser = static::currentUser();

        if ($activeOnly) {
            $query->where('active', true);
        }

        if (! $authUser instanceof User || $authUser->hasRole('Admin')) {
            return $query;
        }

        $branchIds = static::accessibleBranchIds($authUser, $activeOnly);
        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id', $branchIds);
    }

    public static function scopeQueryByAccessibleBranches(Builder $query, string $column = 'branch_id', bool $activeOnly = true): Builder
    {
        $authUser = static::currentUser();

        if (! $authUser instanceof User || $authUser->hasRole('Admin')) {
            return $query;
        }

        $branchIds = static::accessibleBranchIds($authUser, $activeOnly);
        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $branchIds);
    }

    public static function assertCanAccessBranch(
        ?int $branchId,
        string $field = 'branch_id',
        ?string $message = null,
    ): void {
        $authUser = static::currentUser();

        if (! $authUser instanceof User || $authUser->hasRole('Admin')) {
            return;
        }

        if ($branchId === null) {
            throw ValidationException::withMessages([
                $field => $message ?? 'Không xác định chi nhánh thao tác.',
            ]);
        }

        if (! in_array($branchId, static::accessibleBranchIds($authUser, true), true)) {
            throw ValidationException::withMessages([
                $field => $message ?? 'Bạn không có quyền thao tác dữ liệu ở chi nhánh này.',
            ]);
        }
    }
}
