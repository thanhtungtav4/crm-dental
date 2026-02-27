<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ClinicSetting;
use App\Models\MasterDataSyncLog;
use App\Models\Material;
use App\Models\RecallRule;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MasterDataSyncService
{
    public const ENTITY_MATERIALS = 'materials';

    public const ENTITY_SERVICE_CATEGORIES = 'service_categories';

    public const ENTITY_SERVICE_CATALOG = 'service_catalog';

    public const ENTITY_PRICE_BOOK = 'price_book';

    public const ENTITY_RECALL_RULES = 'recall_rules';

    public const ENTITY_CONSENT_TEMPLATES = 'consent_templates';

    public const CONFLICT_POLICY_OVERWRITE = 'overwrite';

    public const CONFLICT_POLICY_SKIP = 'skip';

    public const CONFLICT_POLICY_MANUAL = 'manual';

    /**
     * @return array<int, string>
     */
    public static function supportedEntities(): array
    {
        return [
            self::ENTITY_MATERIALS,
            self::ENTITY_SERVICE_CATEGORIES,
            self::ENTITY_SERVICE_CATALOG,
            self::ENTITY_PRICE_BOOK,
            self::ENTITY_RECALL_RULES,
            self::ENTITY_CONSENT_TEMPLATES,
        ];
    }

    /**
     * @param  array<int, int>  $targetBranchIds
     * @param  array<int, string>  $entities
     * @return array<int, array{entity:string,target_branch_id:int,synced_count:int,skipped_count:int,conflict_count:int,status:string}>
     */
    public function sync(
        int $sourceBranchId,
        array $targetBranchIds,
        array $entities,
        bool $dryRun = false,
        string $conflictPolicy = self::CONFLICT_POLICY_OVERWRITE,
        ?int $triggeredBy = null,
    ): array {
        ActionGate::authorize(
            ActionPermission::MASTER_DATA_SYNC,
            'Bạn không có quyền đồng bộ master data liên chi nhánh.',
        );

        $conflictPolicy = $this->normalizeConflictPolicy($conflictPolicy);
        $entities = $this->normalizeEntities($entities);

        $results = [];

        foreach ($entities as $entity) {
            $entityResults = match ($entity) {
                self::ENTITY_MATERIALS => $this->syncMaterialsEntity(
                    $sourceBranchId,
                    $targetBranchIds,
                    $dryRun,
                    $conflictPolicy,
                    $triggeredBy,
                ),
                self::ENTITY_SERVICE_CATEGORIES => $this->syncServiceCategoriesEntity(
                    $sourceBranchId,
                    $targetBranchIds,
                    $dryRun,
                    $conflictPolicy,
                    $triggeredBy,
                ),
                self::ENTITY_SERVICE_CATALOG => $this->syncServiceCatalogEntity(
                    $sourceBranchId,
                    $targetBranchIds,
                    $dryRun,
                    $conflictPolicy,
                    $triggeredBy,
                ),
                self::ENTITY_PRICE_BOOK => $this->syncPriceBookEntity(
                    $sourceBranchId,
                    $targetBranchIds,
                    $dryRun,
                    $conflictPolicy,
                    $triggeredBy,
                ),
                self::ENTITY_RECALL_RULES => $this->syncRecallRulesEntity(
                    $sourceBranchId,
                    $targetBranchIds,
                    $dryRun,
                    $conflictPolicy,
                    $triggeredBy,
                ),
                self::ENTITY_CONSENT_TEMPLATES => $this->syncConsentTemplatesEntity(
                    $sourceBranchId,
                    $targetBranchIds,
                    $dryRun,
                    $conflictPolicy,
                    $triggeredBy,
                ),
                default => throw ValidationException::withMessages([
                    'entity' => 'Entity đồng bộ không được hỗ trợ.',
                ]),
            };

            $results = array_merge($results, $entityResults);
        }

        return $results;
    }

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
        $rows = $this->sync(
            sourceBranchId: $sourceBranchId,
            targetBranchIds: $targetBranchIds,
            entities: [self::ENTITY_MATERIALS],
            dryRun: $dryRun,
            conflictPolicy: $conflictPolicy,
            triggeredBy: $triggeredBy,
        );

        return collect($rows)
            ->map(fn (array $row) => [
                'target_branch_id' => $row['target_branch_id'],
                'synced_count' => $row['synced_count'],
                'skipped_count' => $row['skipped_count'],
                'conflict_count' => $row['conflict_count'],
                'status' => $row['status'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  string|array<int, string>  $entities
     * @return array<int, string>
     */
    public function normalizeEntities(string|array $entities): array
    {
        $values = is_array($entities)
            ? $entities
            : explode(',', $entities);

        $normalized = collect($values)
            ->map(fn ($entity) => strtolower(trim((string) $entity)))
            ->filter()
            ->values();

        if ($normalized->isEmpty()) {
            throw ValidationException::withMessages([
                'entity' => 'Danh sách entity đồng bộ trống.',
            ]);
        }

        if ($normalized->contains('all')) {
            return self::supportedEntities();
        }

        $supported = self::supportedEntities();

        $invalid = $normalized
            ->reject(fn (string $entity) => in_array($entity, $supported, true))
            ->values()
            ->all();

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'entity' => 'Entity không hợp lệ: '.implode(', ', $invalid),
            ]);
        }

        return $normalized
            ->unique()
            ->values()
            ->all();
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
     * @param  array<int, int>  $targetBranchIds
     * @return array<int, array{entity:string,target_branch_id:int,synced_count:int,skipped_count:int,conflict_count:int,status:string}>
     */
    protected function syncMaterialsEntity(
        int $sourceBranchId,
        array $targetBranchIds,
        bool $dryRun,
        string $conflictPolicy,
        ?int $triggeredBy,
    ): array {
        $sourceMaterials = Material::query()
            ->where('branch_id', $sourceBranchId)
            ->orderBy('id')
            ->get();

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
                $payload = [
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

                if ($targetMaterial) {
                    if (! $this->hasDifference($targetMaterial, $payload)) {
                        $skippedCount++;

                        continue;
                    }

                    if ($this->shouldSkipOnConflict($conflictPolicy)) {
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

            $results[] = $this->finalizeSync(
                entity: self::ENTITY_MATERIALS,
                sourceBranchId: $sourceBranchId,
                targetBranchId: $targetBranchId,
                dryRun: $dryRun,
                startedAt: $startedAt,
                syncedCount: $syncedCount,
                skippedCount: $skippedCount,
                conflictCount: $conflictCount,
                metadata: [
                    'source_count' => $sourceMaterials->count(),
                    'conflicts' => $conflictRows,
                ],
                conflictPolicy: $conflictPolicy,
                triggeredBy: $triggeredBy,
            );
        }

        return $results;
    }

    /**
     * @param  array<int, int>  $targetBranchIds
     * @return array<int, array{entity:string,target_branch_id:int,synced_count:int,skipped_count:int,conflict_count:int,status:string}>
     */
    protected function syncServiceCategoriesEntity(
        int $sourceBranchId,
        array $targetBranchIds,
        bool $dryRun,
        string $conflictPolicy,
        ?int $triggeredBy,
    ): array {
        $sourceCategories = ServiceCategory::query()
            ->with('parent:id,code')
            ->orderByRaw('parent_id IS NOT NULL')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

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

            foreach ($sourceCategories as $sourceCategory) {
                $targetParentId = null;
                if (filled($sourceCategory->parent?->code)) {
                    $targetParentId = ServiceCategory::query()
                        ->where('code', (string) $sourceCategory->parent?->code)
                        ->value('id');
                }

                $payload = [
                    'name' => $sourceCategory->name,
                    'code' => $sourceCategory->code,
                    'parent_id' => $targetParentId,
                    'icon' => $sourceCategory->icon,
                    'color' => $sourceCategory->color,
                    'description' => $sourceCategory->description,
                    'sort_order' => $sourceCategory->sort_order,
                    'active' => $sourceCategory->active,
                ];

                $targetCategory = ServiceCategory::query()
                    ->where('code', $sourceCategory->code)
                    ->first();

                if ($targetCategory) {
                    if (! $this->hasDifference($targetCategory, $payload)) {
                        $skippedCount++;

                        continue;
                    }

                    if ($this->shouldSkipOnConflict($conflictPolicy)) {
                        $conflictCount++;
                        $skippedCount++;
                        $conflictRows[] = [
                            'code' => $sourceCategory->code,
                            'name' => $sourceCategory->name,
                            'target_category_id' => $targetCategory->id,
                        ];

                        continue;
                    }

                    if (! $dryRun) {
                        $targetCategory->fill($payload)->save();
                    }

                    $syncedCount++;

                    continue;
                }

                if (! $dryRun) {
                    ServiceCategory::query()->create($payload);
                }

                $syncedCount++;
            }

            $results[] = $this->finalizeSync(
                entity: self::ENTITY_SERVICE_CATEGORIES,
                sourceBranchId: $sourceBranchId,
                targetBranchId: $targetBranchId,
                dryRun: $dryRun,
                startedAt: $startedAt,
                syncedCount: $syncedCount,
                skippedCount: $skippedCount,
                conflictCount: $conflictCount,
                metadata: [
                    'source_count' => $sourceCategories->count(),
                    'conflicts' => $conflictRows,
                ],
                conflictPolicy: $conflictPolicy,
                triggeredBy: $triggeredBy,
            );
        }

        return $results;
    }

    /**
     * @param  array<int, int>  $targetBranchIds
     * @return array<int, array{entity:string,target_branch_id:int,synced_count:int,skipped_count:int,conflict_count:int,status:string}>
     */
    protected function syncServiceCatalogEntity(
        int $sourceBranchId,
        array $targetBranchIds,
        bool $dryRun,
        string $conflictPolicy,
        ?int $triggeredBy,
    ): array {
        $sourceServices = Service::query()
            ->where(function (Builder $query) use ($sourceBranchId): void {
                $query->whereNull('branch_id')
                    ->orWhere('branch_id', $sourceBranchId);
            })
            ->orderBy('id')
            ->get();

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

            foreach ($sourceServices as $sourceService) {
                $mappedBranchId = $this->mapScopedBranch($sourceService->branch_id, $sourceBranchId, $targetBranchId);
                $targetCategoryId = null;

                if ($sourceService->category_id) {
                    $targetCategoryId = ServiceCategory::query()
                        ->where('code', $sourceService->category?->code)
                        ->value('id');
                }

                $targetService = $this->findTargetService($sourceService, $mappedBranchId);

                $payload = [
                    'category_id' => $targetCategoryId,
                    'name' => $sourceService->name,
                    'code' => $sourceService->code,
                    'description' => $sourceService->description,
                    'unit' => $sourceService->unit,
                    'duration_minutes' => $sourceService->duration_minutes,
                    'tooth_specific' => $sourceService->tooth_specific,
                    'requires_consent' => $sourceService->requires_consent,
                    'default_materials' => $sourceService->default_materials,
                    'doctor_commission_rate' => $sourceService->doctor_commission_rate,
                    'branch_id' => $mappedBranchId,
                    'sort_order' => $sourceService->sort_order,
                    'active' => $sourceService->active,
                    'workflow_type' => $sourceService->workflow_type,
                    'protocol_id' => $sourceService->protocol_id,
                ];

                if ($targetService) {
                    if (! $this->hasDifference($targetService, $payload)) {
                        $skippedCount++;

                        continue;
                    }

                    if ($this->shouldSkipOnConflict($conflictPolicy)) {
                        $conflictCount++;
                        $skippedCount++;
                        $conflictRows[] = [
                            'code' => $sourceService->code,
                            'name' => $sourceService->name,
                            'target_service_id' => $targetService->id,
                        ];

                        continue;
                    }

                    if (! $dryRun) {
                        $targetService->fill($payload)->save();
                    }

                    $syncedCount++;

                    continue;
                }

                if (! $dryRun) {
                    Service::query()->create($payload + [
                        'default_price' => $sourceService->default_price,
                        'vat_rate' => $sourceService->vat_rate,
                    ]);
                }

                $syncedCount++;
            }

            $results[] = $this->finalizeSync(
                entity: self::ENTITY_SERVICE_CATALOG,
                sourceBranchId: $sourceBranchId,
                targetBranchId: $targetBranchId,
                dryRun: $dryRun,
                startedAt: $startedAt,
                syncedCount: $syncedCount,
                skippedCount: $skippedCount,
                conflictCount: $conflictCount,
                metadata: [
                    'source_count' => $sourceServices->count(),
                    'conflicts' => $conflictRows,
                ],
                conflictPolicy: $conflictPolicy,
                triggeredBy: $triggeredBy,
            );
        }

        return $results;
    }

    /**
     * @param  array<int, int>  $targetBranchIds
     * @return array<int, array{entity:string,target_branch_id:int,synced_count:int,skipped_count:int,conflict_count:int,status:string}>
     */
    protected function syncPriceBookEntity(
        int $sourceBranchId,
        array $targetBranchIds,
        bool $dryRun,
        string $conflictPolicy,
        ?int $triggeredBy,
    ): array {
        $sourceServices = Service::query()
            ->where(function (Builder $query) use ($sourceBranchId): void {
                $query->whereNull('branch_id')
                    ->orWhere('branch_id', $sourceBranchId);
            })
            ->orderBy('id')
            ->get();

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

            foreach ($sourceServices as $sourceService) {
                $mappedBranchId = $this->mapScopedBranch($sourceService->branch_id, $sourceBranchId, $targetBranchId);
                $targetService = $this->findTargetService($sourceService, $mappedBranchId);

                if (! $targetService) {
                    $conflictCount++;
                    $skippedCount++;
                    $conflictRows[] = [
                        'code' => $sourceService->code,
                        'name' => $sourceService->name,
                        'reason' => 'MISSING_TARGET_SERVICE',
                    ];

                    continue;
                }

                $payload = [
                    'default_price' => $sourceService->default_price,
                    'vat_rate' => $sourceService->vat_rate,
                ];

                if (! $this->hasDifference($targetService, $payload)) {
                    $skippedCount++;

                    continue;
                }

                if ($this->shouldSkipOnConflict($conflictPolicy)) {
                    $conflictCount++;
                    $skippedCount++;
                    $conflictRows[] = [
                        'code' => $sourceService->code,
                        'name' => $sourceService->name,
                        'target_service_id' => $targetService->id,
                    ];

                    continue;
                }

                if (! $dryRun) {
                    $targetService->fill($payload)->save();
                }

                $syncedCount++;
            }

            $results[] = $this->finalizeSync(
                entity: self::ENTITY_PRICE_BOOK,
                sourceBranchId: $sourceBranchId,
                targetBranchId: $targetBranchId,
                dryRun: $dryRun,
                startedAt: $startedAt,
                syncedCount: $syncedCount,
                skippedCount: $skippedCount,
                conflictCount: $conflictCount,
                metadata: [
                    'source_count' => $sourceServices->count(),
                    'conflicts' => $conflictRows,
                ],
                conflictPolicy: $conflictPolicy,
                triggeredBy: $triggeredBy,
            );
        }

        return $results;
    }

    /**
     * @param  array<int, int>  $targetBranchIds
     * @return array<int, array{entity:string,target_branch_id:int,synced_count:int,skipped_count:int,conflict_count:int,status:string}>
     */
    protected function syncRecallRulesEntity(
        int $sourceBranchId,
        array $targetBranchIds,
        bool $dryRun,
        string $conflictPolicy,
        ?int $triggeredBy,
    ): array {
        $sourceRules = RecallRule::query()
            ->with(['service:id,code,branch_id,name'])
            ->where(function (Builder $query) use ($sourceBranchId): void {
                $query->whereNull('branch_id')
                    ->orWhere('branch_id', $sourceBranchId);
            })
            ->orderBy('id')
            ->get();

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

            foreach ($sourceRules as $sourceRule) {
                $mappedBranchId = $this->mapScopedBranch($sourceRule->branch_id, $sourceBranchId, $targetBranchId);
                $mappedServiceId = null;

                if ($sourceRule->service_id !== null) {
                    $sourceService = $sourceRule->service;

                    if (! $sourceService) {
                        $conflictCount++;
                        $skippedCount++;
                        $conflictRows[] = [
                            'rule_id' => $sourceRule->id,
                            'name' => $sourceRule->name,
                            'reason' => 'MISSING_SOURCE_SERVICE',
                        ];

                        continue;
                    }

                    $targetService = $this->findTargetService(
                        $sourceService,
                        $this->mapScopedBranch($sourceService->branch_id, $sourceBranchId, $targetBranchId),
                    );

                    if (! $targetService) {
                        $conflictCount++;
                        $skippedCount++;
                        $conflictRows[] = [
                            'rule_id' => $sourceRule->id,
                            'name' => $sourceRule->name,
                            'reason' => 'MISSING_TARGET_SERVICE',
                            'service_code' => $sourceService->code,
                        ];

                        continue;
                    }

                    $mappedServiceId = $targetService->id;
                }

                $payload = [
                    'branch_id' => $mappedBranchId,
                    'service_id' => $mappedServiceId,
                    'name' => $sourceRule->name,
                    'offset_days' => $sourceRule->offset_days,
                    'care_channel' => $sourceRule->care_channel,
                    'priority' => $sourceRule->priority,
                    'is_active' => $sourceRule->is_active,
                    'rules' => $sourceRule->rules,
                    'created_by' => $triggeredBy,
                    'updated_by' => $triggeredBy,
                ];

                $targetRule = RecallRule::query()
                    ->where('branch_id', $mappedBranchId)
                    ->where('service_id', $mappedServiceId)
                    ->where('name', $sourceRule->name)
                    ->where('care_channel', $sourceRule->care_channel)
                    ->first();

                if ($targetRule) {
                    if (! $this->hasDifference($targetRule, $payload)) {
                        $skippedCount++;

                        continue;
                    }

                    if ($this->shouldSkipOnConflict($conflictPolicy)) {
                        $conflictCount++;
                        $skippedCount++;
                        $conflictRows[] = [
                            'source_rule_id' => $sourceRule->id,
                            'target_rule_id' => $targetRule->id,
                            'name' => $sourceRule->name,
                        ];

                        continue;
                    }

                    if (! $dryRun) {
                        $targetRule->fill($payload)->save();
                    }

                    $syncedCount++;

                    continue;
                }

                if (! $dryRun) {
                    RecallRule::query()->create($payload);
                }

                $syncedCount++;
            }

            $results[] = $this->finalizeSync(
                entity: self::ENTITY_RECALL_RULES,
                sourceBranchId: $sourceBranchId,
                targetBranchId: $targetBranchId,
                dryRun: $dryRun,
                startedAt: $startedAt,
                syncedCount: $syncedCount,
                skippedCount: $skippedCount,
                conflictCount: $conflictCount,
                metadata: [
                    'source_count' => $sourceRules->count(),
                    'conflicts' => $conflictRows,
                ],
                conflictPolicy: $conflictPolicy,
                triggeredBy: $triggeredBy,
            );
        }

        return $results;
    }

    /**
     * @param  array<int, int>  $targetBranchIds
     * @return array<int, array{entity:string,target_branch_id:int,synced_count:int,skipped_count:int,conflict_count:int,status:string}>
     */
    protected function syncConsentTemplatesEntity(
        int $sourceBranchId,
        array $targetBranchIds,
        bool $dryRun,
        string $conflictPolicy,
        ?int $triggeredBy,
    ): array {
        $sourcePrefix = "consent.template.{$sourceBranchId}.";

        $sourceTemplates = ClinicSetting::query()
            ->where('group', 'consent')
            ->where(function (Builder $query) use ($sourcePrefix): void {
                $query->where('key', 'like', $sourcePrefix.'%')
                    ->orWhere('key', 'like', 'consent.template.shared.%');
            })
            ->orderBy('id')
            ->get();

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

            foreach ($sourceTemplates as $sourceTemplate) {
                $targetKey = Str::startsWith($sourceTemplate->key, $sourcePrefix)
                    ? 'consent.template.'.$targetBranchId.'.'.Str::after($sourceTemplate->key, $sourcePrefix)
                    : $sourceTemplate->key;

                $payload = [
                    'group' => 'consent',
                    'key' => $targetKey,
                    'label' => $sourceTemplate->label,
                    'value' => $sourceTemplate->getRawOriginal('value'),
                    'value_type' => $sourceTemplate->value_type,
                    'is_secret' => $sourceTemplate->is_secret,
                    'is_active' => $sourceTemplate->is_active,
                    'sort_order' => $sourceTemplate->sort_order,
                    'description' => $sourceTemplate->description,
                ];

                $targetTemplate = ClinicSetting::query()
                    ->where('key', $targetKey)
                    ->first();

                if ($targetTemplate) {
                    if (! $this->hasDifference($targetTemplate, $payload)) {
                        $skippedCount++;

                        continue;
                    }

                    if ($this->shouldSkipOnConflict($conflictPolicy)) {
                        $conflictCount++;
                        $skippedCount++;
                        $conflictRows[] = [
                            'source_key' => $sourceTemplate->key,
                            'target_key' => $targetKey,
                        ];

                        continue;
                    }

                    if (! $dryRun) {
                        $targetTemplate->fill($payload)->save();
                    }

                    $syncedCount++;

                    continue;
                }

                if (! $dryRun) {
                    ClinicSetting::query()->create($payload);
                }

                $syncedCount++;
            }

            $results[] = $this->finalizeSync(
                entity: self::ENTITY_CONSENT_TEMPLATES,
                sourceBranchId: $sourceBranchId,
                targetBranchId: $targetBranchId,
                dryRun: $dryRun,
                startedAt: $startedAt,
                syncedCount: $syncedCount,
                skippedCount: $skippedCount,
                conflictCount: $conflictCount,
                metadata: [
                    'source_count' => $sourceTemplates->count(),
                    'conflicts' => $conflictRows,
                ],
                conflictPolicy: $conflictPolicy,
                triggeredBy: $triggeredBy,
            );
        }

        return $results;
    }

    protected function shouldSkipOnConflict(string $conflictPolicy): bool
    {
        return in_array($conflictPolicy, [
            self::CONFLICT_POLICY_SKIP,
            self::CONFLICT_POLICY_MANUAL,
        ], true);
    }

    protected function mapScopedBranch(?int $sourceEntityBranchId, int $sourceBranchId, int $targetBranchId): ?int
    {
        if ($sourceEntityBranchId === null) {
            return null;
        }

        if ($sourceEntityBranchId === $sourceBranchId) {
            return $targetBranchId;
        }

        return $sourceEntityBranchId;
    }

    protected function findTargetService(Service $sourceService, ?int $targetBranchId): ?Service
    {
        $lookup = Service::query();

        if ($targetBranchId === null) {
            $lookup->whereNull('branch_id');
        } else {
            $lookup->where('branch_id', $targetBranchId);
        }

        if (filled($sourceService->code)) {
            $lookup->where('code', $sourceService->code);
        } else {
            $lookup->where('name', $sourceService->name);
        }

        return $lookup->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function hasDifference(Model $targetRecord, array $payload): bool
    {
        foreach ($payload as $column => $expectedValue) {
            if (! $this->valuesEqual($targetRecord->getAttribute($column), $expectedValue)) {
                return true;
            }
        }

        return false;
    }

    protected function valuesEqual(mixed $actual, mixed $expected): bool
    {
        if (is_bool($actual) || is_bool($expected)) {
            return (bool) $actual === (bool) $expected;
        }

        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual === (float) $expected;
        }

        return $this->normalizeValue($actual) === $this->normalizeValue($expected);
    }

    protected function normalizeValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode(
                $this->sortArrayRecursively($value),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
            ) ?: '[]';
        }

        if ($value === null) {
            return 'null';
        }

        return (string) $value;
    }

    /**
     * @param  array<int|string, mixed>  $value
     * @return array<int|string, mixed>
     */
    protected function sortArrayRecursively(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortArrayRecursively($item);
            }
        }

        if (Arr::isAssoc($value)) {
            ksort($value);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{entity:string,target_branch_id:int,synced_count:int,skipped_count:int,conflict_count:int,status:string}
     */
    protected function finalizeSync(
        string $entity,
        int $sourceBranchId,
        int $targetBranchId,
        bool $dryRun,
        \Carbon\CarbonInterface $startedAt,
        int $syncedCount,
        int $skippedCount,
        int $conflictCount,
        array $metadata,
        string $conflictPolicy,
        ?int $triggeredBy,
    ): array {
        $status = $conflictCount > 0
            ? MasterDataSyncLog::STATUS_PARTIAL
            : MasterDataSyncLog::STATUS_SUCCESS;

        $log = MasterDataSyncLog::query()->create([
            'entity' => $entity,
            'source_branch_id' => $sourceBranchId,
            'target_branch_id' => $targetBranchId,
            'dry_run' => $dryRun,
            'synced_count' => $syncedCount,
            'skipped_count' => $skippedCount,
            'conflict_count' => $conflictCount,
            'status' => $status,
            'started_at' => $startedAt,
            'finished_at' => now(),
            'metadata' => $metadata + [
                'conflict_policy' => $conflictPolicy,
            ],
            'triggered_by' => $triggeredBy,
        ]);

        AuditLog::record(
            entityType: AuditLog::ENTITY_MASTER_DATA_SYNC,
            entityId: $log->id,
            action: AuditLog::ACTION_SYNC,
            actorId: $triggeredBy,
            metadata: [
                'entity' => $entity,
                'source_branch_id' => $sourceBranchId,
                'target_branch_id' => $targetBranchId,
                'dry_run' => $dryRun,
                'synced_count' => $syncedCount,
                'skipped_count' => $skippedCount,
                'conflict_count' => $conflictCount,
                'conflict_policy' => $conflictPolicy,
            ],
        );

        return [
            'entity' => $entity,
            'target_branch_id' => $targetBranchId,
            'synced_count' => $syncedCount,
            'skipped_count' => $skippedCount,
            'conflict_count' => $conflictCount,
            'status' => $status,
        ];
    }
}
