<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Material;
use App\Models\MaterialBatch;
use Illuminate\Database\Seeder;

class InventoryScenarioSeeder extends Seeder
{
    public const MATERIAL_SKU = 'INV-QA-LOW-001';

    public const ACTIVE_BATCH_NUMBER = 'LOT-QA-INV-ACTIVE';

    public const EXPIRED_BATCH_NUMBER = 'LOT-QA-INV-EXPIRED';

    public function run(): void
    {
        $branchId = Branch::query()->where('code', 'HCM-Q1')->value('id');

        if (! is_numeric($branchId)) {
            return;
        }

        $material = Material::query()->updateOrCreate(
            ['sku' => self::MATERIAL_SKU],
            [
                'branch_id' => (int) $branchId,
                'name' => 'QA Inventory Low Stock Composite',
                'unit' => 'box',
                'stock_qty' => 9,
                'sale_price' => 120_000,
                'cost_price' => 80_000,
                'min_stock' => 10,
                'category' => 'consumable',
                'manufacturer' => 'QA Inventory',
                'reorder_point' => 10,
                'storage_location' => 'QA-KHO-A1',
            ],
        );

        MaterialBatch::query()->updateOrCreate(
            ['batch_number' => self::ACTIVE_BATCH_NUMBER],
            [
                'material_id' => $material->id,
                'expiry_date' => now()->addMonths(2)->toDateString(),
                'quantity' => 5,
                'purchase_price' => 80_000,
                'received_date' => now()->subDays(3)->toDateString(),
                'status' => 'active',
            ],
        );

        MaterialBatch::query()->updateOrCreate(
            ['batch_number' => self::EXPIRED_BATCH_NUMBER],
            [
                'material_id' => $material->id,
                'expiry_date' => now()->subDays(2)->toDateString(),
                'quantity' => 4,
                'purchase_price' => 80_000,
                'received_date' => now()->subMonths(2)->toDateString(),
                'status' => 'active',
            ],
        );
    }
}
