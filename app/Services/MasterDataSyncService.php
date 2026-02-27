<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\MasterDataSyncLog;
use App\Models\Material;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Illuminate\Validation\ValidationException;

class MasterDataSyncService
{
    public const ENTITY_MATERIALS = 'materials';

    public const CONFLICT_POLICY_OVERWRITE = 'overwrite';

    public const CONFLICT_POLICY_SKIP = 'skip';

    public const CONFLICT_POLICY_MANUAL = 'manual';

    /**
     * @param  array<int, int>  $targetBranchIds
     * @return array<int, array{target_branch_id:int,synced_count:int,skipped_count:int,conflict_count:int,status:string}>
     */
    public function syncMaterials(
        int $sourceBranchId,
        array $targetBranchIds,
        bool $dryRun = false,
        string $conflictPolicy = self::CONFLICT_POLICY_OVERWRITE,
        ?int $triggeredBy = null,
    ): array {
        ActionGate::authorize(
            ActionPermission::MASTER_DATA_SYNC,
            'Bạn không có quyền đồng bộ master data liên chi nhánh.',
        );

        $sourceMaterials = Material::query()
            ->where('branch_id', $sourceBranchId)
            ->orderBy('id')
            ->get();

        $conflictPolicy = $this->normalizeConflictPolicy($conflictPolicy);

        $results = [];

        foreach ($targetBranchIds as $targetBranchId) {
            if ($targetBranchId === $sourceBranchId) {
                continue;
            }

            $startedAt = now();
            $syncedCount = 0;
            $skippedCount = 0;
            $conflictCount = 0;
            $conflictRows = [];

            foreach ($sourceMaterials as $sourceMaterial) {
                $lookup = Material::query()
                    ->where('branch_id', $targetBranchId);

                if (filled($sourceMaterial->sku)) {
                    $lookup->where('sku', $sourceMaterial->sku);
                } else {
                    $lookup->where('name', $sourceMaterial->name);
                }

                $targetMaterial = $lookup->first();
                $payload = $this->buildPayload($sourceMaterial, $targetBranchId);

                if ($targetMaterial) {
                    $changed = $this->hasMaterialDifference($targetMaterial, $payload);

                    if (! $changed) {
                        $skippedCount++;

                        continue;
                    }

                    if (in_array($conflictPolicy, [self::CONFLICT_POLICY_SKIP, self::CONFLICT_POLICY_MANUAL], true)) {
                        $conflictCount++;
                        $skippedCount++;
                        $conflictRows[] = [
                            'source_material_id' => $sourceMaterial->id,
                            'target_material_id' => $targetMaterial->id,
                            'sku' => $sourceMaterial->sku,
                            'name' => $sourceMaterial->name,
                        ];

                        continue;
                    }

                    if (! $dryRun) {
                        $targetMaterial->fill($payload)->save();
                    }

                    $syncedCount++;

                    continue;
                }

                if (! $dryRun) {
                    Material::query()->create($payload + [
                        'stock_qty' => 0,
                    ]);
                }

                $syncedCount++;
            }

            $status = $conflictCount > 0
                ? MasterDataSyncLog::STATUS_PARTIAL
                : MasterDataSyncLog::STATUS_SUCCESS;

            $log = MasterDataSyncLog::query()->create([
                'entity' => 'materials',
                'source_branch_id' => $sourceBranchId,
                'target_branch_id' => $targetBranchId,
                'dry_run' => $dryRun,
                'synced_count' => $syncedCount,
                'skipped_count' => $skippedCount,
                'conflict_count' => $conflictCount,
                'status' => $status,
                'started_at' => $startedAt,
                'finished_at' => now(),
                'metadata' => [
                    'source_materials' => $sourceMaterials->count(),
                    'conflict_policy' => $conflictPolicy,
                    'conflicts' => $conflictRows,
                ],
                'triggered_by' => $triggeredBy,
            ]);

            AuditLog::record(
                entityType: AuditLog::ENTITY_MASTER_DATA_SYNC,
                entityId: $log->id,
                action: AuditLog::ACTION_SYNC,
                actorId: $triggeredBy,
                metadata: [
                    'entity' => 'materials',
                    'source_branch_id' => $sourceBranchId,
                    'target_branch_id' => $targetBranchId,
                    'dry_run' => $dryRun,
                    'synced_count' => $syncedCount,
                    'skipped_count' => $skippedCount,
                    'conflict_count' => $conflictCount,
                    'conflict_policy' => $conflictPolicy,
                ],
            );

            $results[] = [
                'target_branch_id' => $targetBranchId,
                'synced_count' => $syncedCount,
                'skipped_count' => $skippedCount,
                'conflict_count' => $conflictCount,
                'status' => $status,
            ];
        }

        return $results;
    }

    public function normalizeConflictPolicy(string $conflictPolicy): string
    {
        $normalized = strtolower(trim($conflictPolicy));

        if (! in_array($normalized, [
            self::CONFLICT_POLICY_OVERWRITE,
            self::CONFLICT_POLICY_SKIP,
            self::CONFLICT_POLICY_MANUAL,
        ], true)) {
            throw ValidationException::withMessages([
                'conflict_policy' => 'Conflict policy không hợp lệ. Chọn overwrite/skip/manual.',
            ]);
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(Material $sourceMaterial, int $targetBranchId): array
    {
        return [
            'branch_id' => $targetBranchId,
            'name' => $sourceMaterial->name,
            'sku' => $sourceMaterial->sku,
            'unit' => $sourceMaterial->unit,
            'sale_price' => $sourceMaterial->sale_price,
            'cost_price' => $sourceMaterial->cost_price,
            'min_stock' => $sourceMaterial->min_stock,
            'category' => $sourceMaterial->category,
            'manufacturer' => $sourceMaterial->manufacturer,
            'supplier_id' => $sourceMaterial->supplier_id,
            'reorder_point' => $sourceMaterial->reorder_point,
            'storage_location' => $sourceMaterial->storage_location,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function hasMaterialDifference(Material $targetMaterial, array $payload): bool
    {
        foreach ($payload as $column => $expectedValue) {
            $actualValue = $targetMaterial->{$column};

            if ((string) $actualValue !== (string) $expectedValue) {
                return true;
            }
        }

        return false;
    }
}
