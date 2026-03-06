<?php

namespace App\Services;

use App\Models\Material;
use App\Models\MaterialBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryMutationService
{
    /**
     * @return array{material: Material, batch: MaterialBatch}
     */
    public function consumeBatch(
        int $materialId,
        int $batchId,
        int $quantity,
        ?int $expectedBranchId = null,
        ?string $branchMismatchMessage = null,
    ): array {
        return DB::transaction(function () use ($materialId, $batchId, $quantity, $expectedBranchId, $branchMismatchMessage): array {
            $this->assertPositiveQuantity($quantity);

            [$material, $batch] = $this->lockInventoryContext(
                materialId: $materialId,
                batchId: $batchId,
                expectedBranchId: $expectedBranchId,
                branchMismatchMessage: $branchMismatchMessage,
            );

            if ($batch->status !== 'active') {
                throw ValidationException::withMessages([
                    'batch_id' => 'Chi duoc su dung lo vat tu dang hoat dong.',
                ]);
            }

            if ($batch->expiry_date !== null && $batch->expiry_date->lt(today())) {
                throw ValidationException::withMessages([
                    'batch_id' => 'Khong duoc su dung lo vat tu da het han.',
                ]);
            }

            if ((int) $batch->quantity < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'So luong su dung vuot qua ton kho cua lo vat tu da chon.',
                ]);
            }

            if ((int) $material->stock_qty < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'So luong su dung vuot qua ton kho hien tai.',
                ]);
            }

            $batch->quantity = (int) $batch->quantity - $quantity;

            if ((int) $batch->quantity === 0) {
                $batch->status = 'depleted';
            }

            $batch->save();

            $material->stock_qty = (int) $material->stock_qty - $quantity;
            $material->save();

            return [
                'material' => $material,
                'batch' => $batch,
            ];
        }, 3);
    }

    /**
     * @return array{material: Material, batch: MaterialBatch}
     */
    public function restoreBatch(
        int $materialId,
        int $batchId,
        int $quantity,
        ?int $expectedBranchId = null,
        ?string $branchMismatchMessage = null,
    ): array {
        return DB::transaction(function () use ($materialId, $batchId, $quantity, $expectedBranchId, $branchMismatchMessage): array {
            $this->assertPositiveQuantity($quantity);

            [$material, $batch] = $this->lockInventoryContext(
                materialId: $materialId,
                batchId: $batchId,
                expectedBranchId: $expectedBranchId,
                branchMismatchMessage: $branchMismatchMessage,
            );

            $batch->quantity = (int) $batch->quantity + $quantity;

            if ($batch->status === 'depleted' && (int) $batch->quantity > 0) {
                $batch->status = $batch->expiry_date !== null && $batch->expiry_date->lt(today())
                    ? 'expired'
                    : 'active';
            }

            $batch->save();

            $material->stock_qty = (int) $material->stock_qty + $quantity;
            $material->save();

            return [
                'material' => $material,
                'batch' => $batch,
            ];
        }, 3);
    }

    protected function assertPositiveQuantity(int $quantity): void
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'So luong vat tu phai lon hon 0.',
            ]);
        }
    }

    /**
     * @return array{0: Material, 1: MaterialBatch}
     */
    protected function lockInventoryContext(
        int $materialId,
        int $batchId,
        ?int $expectedBranchId = null,
        ?string $branchMismatchMessage = null,
    ): array {
        $material = Material::query()
            ->lockForUpdate()
            ->findOrFail($materialId);

        $batch = MaterialBatch::query()
            ->lockForUpdate()
            ->findOrFail($batchId);

        if ((int) $batch->material_id !== (int) $material->id) {
            throw ValidationException::withMessages([
                'batch_id' => 'Lo vat tu khong thuoc vat tu da chon.',
            ]);
        }

        $materialBranchId = is_numeric($material->branch_id) ? (int) $material->branch_id : null;

        if ($expectedBranchId !== null && $materialBranchId !== $expectedBranchId) {
            throw ValidationException::withMessages([
                'material_id' => $branchMismatchMessage ?? 'Vat tu khong cung chi nhanh voi giao dich inventory hien tai.',
            ]);
        }

        return [$material, $batch];
    }
}
