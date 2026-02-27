<?php

namespace App\Console\Commands;

use App\Services\MasterDataSyncService;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Illuminate\Console\Command;

class SyncMasterData extends Command
{
    protected $signature = 'master-data:sync {source_branch_id : Branch nguồn} {target_branch_ids* : Danh sách branch đích} {--entity=materials : Entity cần sync (CSV hoặc all)} {--conflict-policy=overwrite : Chính sách conflict (overwrite|skip|manual)} {--dry-run : Chỉ preview, không ghi DB}';

    protected $description = 'Đồng bộ master data liên chi nhánh (vật tư/danh mục nền).';

    public function __construct(protected MasterDataSyncService $syncService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::MASTER_DATA_SYNC,
            'Bạn không có quyền đồng bộ master data liên chi nhánh.',
        );

        $sourceBranchId = (int) $this->argument('source_branch_id');
        $targetBranchIds = collect($this->argument('target_branch_ids'))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($targetBranchIds === []) {
            $this->warn('Danh sách chi nhánh đích trống.');

            return self::INVALID;
        }

        $entities = $this->syncService->normalizeEntities((string) $this->option('entity'));

        $results = $this->syncService->sync(
            sourceBranchId: $sourceBranchId,
            targetBranchIds: $targetBranchIds,
            entities: $entities,
            dryRun: (bool) $this->option('dry-run'),
            conflictPolicy: (string) $this->option('conflict-policy'),
            triggeredBy: auth()->id(),
        );

        $this->table(
            ['Entity', 'Target Branch', 'Synced', 'Skipped', 'Conflict', 'Status'],
            collect($results)
                ->map(fn (array $result) => [
                    $result['entity'],
                    $result['target_branch_id'],
                    $result['synced_count'],
                    $result['skipped_count'],
                    $result['conflict_count'],
                    $result['status'],
                ])
                ->all()
        );

        return self::SUCCESS;
    }
}
