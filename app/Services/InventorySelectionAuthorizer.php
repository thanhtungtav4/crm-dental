<?php

namespace App\Services;

use App\Models\Material;
use App\Models\MaterialBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class InventorySelectionAuthorizer
{
    /**
     * @return array<int, string>
     */
    public function materialOptions(?User $actor, ?int $branchId = null): array
    {
        return $this->scopeMaterials(Material::query(), $actor, $branchId)
            ->pluck('name', 'id')
            ->mapWithKeys(fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])
            ->all();
    }

    public function scopeMaterials(Builder $query, ?User $actor, ?int $branchId = null): Builder
    {
        $allowedBranchIds = $this->resolveAllowedBranchIds($actor, $branchId, 'material_id');

        if ($allowedBranchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereIn('branch_id', $allowedBranchIds)
            ->orderBy('name');
    }

    public function scopeActiveSuppliers(Builder $query): Builder
    {
        return $query
            ->where('active', true)
            ->orderBy('name');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sanitizeMaterialFormData(?User $actor, array $data): array
    {
        $data['branch_id'] = $this->sanitizeBranchId(
            actor: $actor,
            branchId: isset($data['branch_id']) && filled($data['branch_id'])
                ? (int) $data['branch_id']
                : BranchAccess::defaultBranchIdForCurrentUser(),
            field: 'branch_id',
            message: 'Bạn không thể tạo hoặc cập nhật vật tư ở chi nhánh ngoài phạm vi được phân quyền.',
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sanitizeMaterialBatchFormData(?User $actor, array $data, ?MaterialBatch $record = null): array
    {
        $materialId = isset($data['material_id']) && filled($data['material_id'])
            ? (int) $data['material_id']
            : ($record?->material_id !== null ? (int) $record->material_id : null);

        $data['material_id'] = $this->sanitizeMaterialId(
            actor: $actor,
            materialId: $materialId,
            field: 'material_id',
        );

        $supplierId = isset($data['supplier_id']) && filled($data['supplier_id'])
            ? (int) $data['supplier_id']
            : null;

        $data['supplier_id'] = $this->sanitizeSupplierId($supplierId, 'supplier_id');

        return $data;
    }

    protected function sanitizeMaterialId(?User $actor, ?int $materialId, string $field): int
    {
        if ($materialId === null) {
            throw ValidationException::withMessages([
                $field => 'Vui lòng chọn vật tư thuộc phạm vi chi nhánh hợp lệ.',
            ]);
        }

        $material = Material::query()
            ->select(['id', 'branch_id'])
            ->find($materialId);

        if (! $material instanceof Material) {
            throw ValidationException::withMessages([
                $field => 'Không tìm thấy vật tư đã chọn.',
            ]);
        }

        $branchId = is_numeric($material->branch_id) ? (int) $material->branch_id : null;

        BranchAccess::assertCanAccessBranch(
            branchId: $branchId,
            field: $field,
            message: 'Vật tư được chọn không thuộc phạm vi chi nhánh.',
        );

        return (int) $material->id;
    }

    protected function sanitizeSupplierId(?int $supplierId, string $field): ?int
    {
        if ($supplierId === null) {
            return null;
        }

        $supplier = Supplier::query()
            ->select(['id', 'active'])
            ->find($supplierId);

        if (! $supplier instanceof Supplier) {
            throw ValidationException::withMessages([
                $field => 'Không tìm thấy nhà cung cấp đã chọn.',
            ]);
        }

        if (! $supplier->active) {
            throw ValidationException::withMessages([
                $field => 'Chỉ được chọn nhà cung cấp đang hoạt động.',
            ]);
        }

        return (int) $supplier->id;
    }

    protected function sanitizeBranchId(?User $actor, ?int $branchId, string $field, string $message): ?int
    {
        if (! $actor instanceof User) {
            return $branchId;
        }

        BranchAccess::assertCanAccessBranch(
            branchId: $branchId,
            field: $field,
            message: $message,
        );

        return $branchId;
    }

    /**
     * @return array<int, int>
     */
    protected function resolveAllowedBranchIds(?User $actor, ?int $branchId, string $field): array
    {
        if ($branchId !== null) {
            $resolvedBranchId = $this->sanitizeBranchId(
                actor: $actor,
                branchId: $branchId,
                field: $field,
                message: 'Bạn không thể thao tác inventory ngoài phạm vi chi nhánh được phân quyền.',
            );

            return $resolvedBranchId !== null ? [$resolvedBranchId] : [];
        }

        if (! $actor instanceof User) {
            return [];
        }

        return BranchAccess::accessibleBranchIds($actor, true);
    }
}
