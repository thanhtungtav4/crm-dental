<?php

namespace App\Services;

use App\Models\AuditLog;
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
                ->with('treatmentPlan:id,branch_id,patient_id')
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

            AuditLog::record(
                entityType: AuditLog::ENTITY_TREATMENT_SESSION,
                entityId: (int) $session->id,
                action: AuditLog::ACTION_CREATE,
                actorId: $actorId,
                metadata: [
                    'trigger' => 'treatment_material_usage_recorded',
                    'treatment_session_id' => (int) $session->id,
                    'treatment_plan_id' => $session->treatment_plan_id !== null ? (int) $session->treatment_plan_id : null,
                    'patient_id' => $session->treatmentPlan?->patient_id !== null ? (int) $session->treatmentPlan->patient_id : null,
                    'branch_id' => $resolvedBranchId,
                    'treatment_material_id' => (int) $usage->id,
                    'material_id' => (int) $material->id,
                    'material_name' => (string) $material->name,
                    'material_unit' => $material->unit,
                    'batch_id' => (int) $batch->id,
                    'batch_number' => (string) $batch->batch_number,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'total_cost' => $totalCost,
                ],
                branchId: $resolvedBranchId,
                patientId: $session->treatmentPlan?->patient_id !== null ? (int) $session->treatmentPlan->patient_id : null,
            );

            return $usage->fresh(['session.treatmentPlan', 'material', 'batch', 'user']);
        }, 3);
    }

    public function delete(TreatmentMaterial $usage): void
    {
        $usageId = is_numeric($usage->getKey()) ? (int) $usage->getKey() : null;

        if ($usageId === null) {
            throw ValidationException::withMessages([
                'treatment_material' => 'Khong tim thay ghi nhan vat tu can hoan tac.',
            ]);
        }

        DB::transaction(function () use ($usageId): void {
            $lockedUsage = TreatmentMaterial::query()
                ->with([
                    'session.treatmentPlan:id,branch_id,patient_id',
                    'material',
                    'batch',
                ])
                ->lockForUpdate()
                ->find($usageId);

            if ($lockedUsage === null) {
                if ($this->reversalAlreadyRecorded($usageId)) {
                    return;
                }

                throw ValidationException::withMessages([
                    'treatment_material' => 'Khong tim thay ghi nhan vat tu can hoan tac.',
                ]);
            }

            $material = Material::query()->findOrFail((int) $lockedUsage->material_id);

            $quantity = (int) $lockedUsage->quantity;
            $resolvedBranchId = $lockedUsage->session?->treatmentPlan?->branch_id !== null
                ? (int) $lockedUsage->session->treatmentPlan->branch_id
                : ($material->branch_id !== null ? (int) $material->branch_id : null);
            $resolvedActorId = is_numeric(auth()->id()) ? (int) auth()->id() : $lockedUsage->used_by;

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
                'created_by' => $resolvedActorId,
            ]);

            AuditLog::record(
                entityType: AuditLog::ENTITY_TREATMENT_SESSION,
                entityId: (int) $lockedUsage->treatment_session_id,
                action: AuditLog::ACTION_REVERSAL,
                actorId: $resolvedActorId,
                metadata: [
                    'trigger' => 'treatment_material_usage_reversed',
                    'treatment_session_id' => (int) $lockedUsage->treatment_session_id,
                    'treatment_plan_id' => $lockedUsage->session?->treatment_plan_id !== null ? (int) $lockedUsage->session->treatment_plan_id : null,
                    'patient_id' => $lockedUsage->session?->treatmentPlan?->patient_id !== null ? (int) $lockedUsage->session->treatmentPlan->patient_id : null,
                    'branch_id' => $resolvedBranchId,
                    'reversed_treatment_material_id' => (int) $lockedUsage->id,
                    'material_id' => (int) $material->id,
                    'material_name' => (string) $material->name,
                    'material_unit' => $material->unit,
                    'batch_id' => $batch->id !== null ? (int) $batch->id : null,
                    'batch_number' => $batch->batch_number,
                    'quantity' => $quantity,
                    'total_cost' => (float) $lockedUsage->cost,
                ],
                branchId: $resolvedBranchId,
                patientId: $lockedUsage->session?->treatmentPlan?->patient_id !== null ? (int) $lockedUsage->session->treatmentPlan->patient_id : null,
            );

            TreatmentMaterial::runWithinManagedPersistence(function () use ($lockedUsage): void {
                $lockedUsage->delete();
            });
        }, 3);
    }

    protected function reversalAlreadyRecorded(int $usageId): bool
    {
        return AuditLog::query()
            ->where('entity_type', AuditLog::ENTITY_TREATMENT_SESSION)
            ->where('action', AuditLog::ACTION_REVERSAL)
            ->where('metadata->trigger', 'treatment_material_usage_reversed')
            ->where('metadata->reversed_treatment_material_id', $usageId)
            ->exists();
    }
}
