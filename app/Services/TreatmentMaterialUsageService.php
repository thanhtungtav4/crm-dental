<?php

namespace App\Services;

use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\TreatmentMaterial;
use App\Models\TreatmentSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TreatmentMaterialUsageService
{
    public function create(array $data): TreatmentMaterial
    {
        return DB::transaction(function () use ($data): TreatmentMaterial {
            $sessionId = is_numeric($data['treatment_session_id'] ?? null)
                ? (int) $data['treatment_session_id']
                : null;
            $materialId = is_numeric($data['material_id'] ?? null)
                ? (int) $data['material_id']
                : null;
            $batchId = is_numeric($data['batch_id'] ?? null)
                ? (int) $data['batch_id']
                : null;
            $quantity = (int) ($data['quantity'] ?? 0);

            if ($sessionId === null) {
                throw ValidationException::withMessages([
                    'treatment_session_id' => 'Vui long chon phien dieu tri.',
                ]);
            }

            if ($materialId === null) {
                throw ValidationException::withMessages([
                    'material_id' => 'Vui long chon vat tu.',
                ]);
            }

            if ($batchId === null) {
                throw ValidationException::withMessages([
                    'batch_id' => 'Vui long chon lo vat tu de dam bao truy vet ton kho.',
                ]);
            }

            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'So luong vat tu phai lon hon 0.',
                ]);
            }

            $session = TreatmentSession::query()
                ->with('treatmentPlan:id,branch_id')
                ->lockForUpdate()
                ->findOrFail($sessionId);

            $material = Material::query()->findOrFail($materialId);

            $sessionBranchId = $session->treatmentPlan?->branch_id;
            $materialBranchId = $material->branch_id;
            $resolvedBranchId = $sessionBranchId !== null
                ? (int) $sessionBranchId
                : ($materialBranchId !== null ? (int) $materialBranchId : null);

            $authUser = auth()->user();

            if ($authUser instanceof User && ! $authUser->canAccessBranch($resolvedBranchId)) {
                throw ValidationException::withMessages([
                    'treatment_session_id' => 'Ban khong co quyen ghi nhan vat tu cho chi nhanh nay.',
                ]);
            }

            $data = app(TreatmentAssignmentAuthorizer::class)->sanitizeTreatmentMaterialUsageData(
                actor: $authUser instanceof User ? $authUser : null,
                data: $data,
                branchId: $resolvedBranchId,
            );

            $actorId = is_numeric($data['used_by'] ?? null)
                ? (int) $data['used_by']
                : (is_numeric(auth()->id()) ? (int) auth()->id() : null);

            $mutation = app(InventoryMutationService::class)->consumeBatch(
                materialId: $material->id,
                batchId: $batchId,
                quantity: $quantity,
                expectedBranchId: $resolvedBranchId,
                branchMismatchMessage: 'Vat tu khong cung chi nhanh voi phien dieu tri da chon.',
            );

            $material = $mutation['material'];
            $batch = $mutation['batch'];

            $unitCost = (float) ($batch->purchase_price ?? $material->cost_price ?? $material->sale_price ?? 0);
            $totalCost = is_numeric($data['cost'] ?? null) && (float) $data['cost'] > 0
                ? round((float) $data['cost'], 2)
                : round($unitCost * $quantity, 2);

            $usage = TreatmentMaterial::runWithinManagedPersistence(fn (): TreatmentMaterial => TreatmentMaterial::query()->create([
                'treatment_session_id' => $session->id,
                'material_id' => $material->id,
                'batch_id' => $batch->id,
                'quantity' => $quantity,
                'cost' => $totalCost,
                'used_by' => $actorId,
            ]));

            InventoryTransaction::query()->create([
                'material_id' => $material->id,
                'material_batch_id' => $batch->id,
                'branch_id' => $resolvedBranchId,
                'treatment_session_id' => $session->id,
                'type' => 'out',
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'note' => 'Auto: used in treatment',
                'created_by' => $actorId,
            ]);

            return $usage->fresh(['session.treatmentPlan', 'material', 'batch', 'user']);
        }, 3);
    }

    public function delete(TreatmentMaterial $usage): void
    {
        DB::transaction(function () use ($usage): void {
            $lockedUsage = TreatmentMaterial::query()
                ->with([
                    'session.treatmentPlan:id,branch_id',
                    'material',
                    'batch',
                ])
                ->lockForUpdate()
                ->findOrFail($usage->getKey());

            $material = Material::query()->findOrFail((int) $lockedUsage->material_id);

            $quantity = (int) $lockedUsage->quantity;
            $resolvedBranchId = $lockedUsage->session?->treatmentPlan?->branch_id !== null
                ? (int) $lockedUsage->session->treatmentPlan->branch_id
                : ($material->branch_id !== null ? (int) $material->branch_id : null);

            $mutation = app(InventoryMutationService::class)->restoreBatch(
                materialId: (int) $lockedUsage->material_id,
                batchId: (int) $lockedUsage->batch_id,
                quantity: $quantity,
                expectedBranchId: $resolvedBranchId,
                branchMismatchMessage: 'Vat tu khong cung chi nhanh voi phien dieu tri da chon.',
            );

            $material = $mutation['material'];
            $batch = $mutation['batch'];

            InventoryTransaction::query()->create([
                'material_id' => $material->id,
                'material_batch_id' => $batch->id,
                'branch_id' => $resolvedBranchId,
                'treatment_session_id' => $lockedUsage->treatment_session_id,
                'type' => 'adjust',
                'quantity' => $quantity,
                'unit_cost' => round(((float) $lockedUsage->cost) / max($quantity, 1), 2),
                'note' => 'Auto: revert usage delete',
                'created_by' => $lockedUsage->used_by,
            ]);

            TreatmentMaterial::runWithinManagedPersistence(function () use ($lockedUsage): void {
                $lockedUsage->delete();
            });
        }, 3);
    }
}
