<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Material;
use App\Models\MaterialBatch;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $userId = DB::table('users')->orderBy('id')->value('id');
        $branches = Branch::query()
            ->where('active', true)
            ->orderBy('id')
            ->get(['id', 'code']);

        if ($branches->isEmpty()) {
            $this->command?->warn('Khong co chi nhanh active de seed inventory.');

            return;
        }

        $supplierIdsByCode = $this->seedSuppliers($userId);

        foreach ($branches as $branch) {
            $this->seedMaterialsForBranch($branch, $supplierIdsByCode, $userId);
        }

        $this->syncStockQuantities($branches);

        $this->command?->info('Da cap nhat inventory seed da chi nhanh cho thi truong Viet Nam.');
    }

    private function seedSuppliers(?int $userId): Collection
    {
        return collect($this->supplierSeedData())
            ->mapWithKeys(function (array $supplierData) use ($userId): array {
                $supplier = Supplier::query()->updateOrCreate(
                    ['code' => $supplierData['code']],
                    array_merge($supplierData, [
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]),
                );

                return [$supplierData['code'] => $supplier->id];
            });
    }

    private function seedMaterialsForBranch(Branch $branch, Collection $supplierIdsByCode, ?int $userId): void
    {
        $materialIdsBySku = collect();

        foreach ($this->materialSeedData() as $materialData) {
            $supplierCode = $materialData['supplier_code'];
            unset($materialData['supplier_code']);

            $material = Material::query()->updateOrCreate(
                [
                    'branch_id' => $branch->id,
                    'sku' => $materialData['sku'],
                ],
                array_merge($materialData, [
                    'branch_id' => $branch->id,
                    'supplier_id' => $supplierIdsByCode->get($supplierCode),
                ]),
            );

            $materialIdsBySku->put($material->sku, $material->id);
        }

        foreach ($this->batchSeedData() as $batchData) {
            $materialId = $materialIdsBySku->get($batchData['material_sku']);
            if (! is_numeric($materialId)) {
                continue;
            }

            $material = Material::query()->find($materialId);
            if (! $material instanceof Material) {
                continue;
            }

            MaterialBatch::query()->updateOrCreate(
                [
                    'material_id' => $material->id,
                    'batch_number' => $batchData['batch_number'],
                ],
                [
                    'expiry_date' => $batchData['expiry_date'],
                    'quantity' => $batchData['quantity'],
                    'purchase_price' => $batchData['purchase_price'],
                    'received_date' => $batchData['received_date'],
                    'supplier_id' => $material->supplier_id,
                    'status' => $batchData['status'],
                    'notes' => $batchData['notes'],
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ],
            );
        }
    }

    private function syncStockQuantities(Collection $branches): void
    {
        foreach ($branches as $branch) {
            Material::query()
                ->where('branch_id', $branch->id)
                ->get()
                ->each(function (Material $material): void {
                    if (! $material->batches()->exists()) {
                        return;
                    }

                    $material->update([
                        'stock_qty' => (int) $material->batches()
                            ->where('status', 'active')
                            ->sum('quantity'),
                    ]);
                });
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function supplierSeedData(): array
    {
        return [
            [
                'name' => 'Cong ty TNHH Thiet Bi Nha Khoa Minh An',
                'code' => 'MINHAN',
                'tax_code' => '0312345678',
                'contact_person' => 'Nguyen Minh An',
                'phone' => '0901234567',
                'email' => 'sales@minhan.test',
                'address' => '126 Nguyen Thi Minh Khai, Quan 3, TP.HCM',
                'website' => 'https://minhan.test',
                'payment_terms' => '30_days',
                'notes' => 'Nha phan phoi vat lieu va vat tu nha khoa tai TP.HCM.',
                'active' => true,
            ],
            [
                'name' => 'Cong ty CP Sai Gon Dental Supply',
                'code' => 'SGDS',
                'tax_code' => '0309876543',
                'contact_person' => 'Tran Thanh Binh',
                'phone' => '0902345678',
                'email' => 'kinhdoanh@sgds.test',
                'address' => '42 Vo Van Tan, Quan 3, TP.HCM',
                'website' => 'https://sgds.test',
                'payment_terms' => '15_days',
                'notes' => 'Chuyen vat lieu phuc hoi va consignment cho chuoi phong kham.',
                'active' => true,
            ],
            [
                'name' => 'Cong ty TNHH NSK Solutions Vietnam',
                'code' => 'NSKVN',
                'tax_code' => '0102233445',
                'contact_person' => 'Le Huu Khang',
                'phone' => '0903456789',
                'email' => 'support@nskvn.test',
                'address' => '19 Duy Tan, Cau Giay, Ha Noi',
                'website' => 'https://nskvn.test',
                'payment_terms' => '7_days',
                'notes' => 'Thiet bi tay khoan va may scalers cho he thong da chi nhanh.',
                'active' => true,
            ],
            [
                'name' => 'Cong ty TNHH Duoc Y Te Dong Do',
                'code' => 'DONGDO',
                'tax_code' => '0105566778',
                'contact_person' => 'Pham Thu Hien',
                'phone' => '0904567890',
                'email' => 'order@dongdo.test',
                'address' => '88 Xa Dan, Dong Da, Ha Noi',
                'website' => 'https://dongdo.test',
                'payment_terms' => 'cash',
                'notes' => 'Thuoc te, khang sinh va hao pham y te thong dung.',
                'active' => true,
            ],
            [
                'name' => 'Cong ty TNHH Medident Da Nang',
                'code' => 'MEDIDN',
                'tax_code' => '0409988776',
                'contact_person' => 'Hoang Gia Linh',
                'phone' => '0905678901',
                'email' => 'sales@medidn.test',
                'address' => '217 Nguyen Van Linh, Hai Chau, Da Nang',
                'website' => 'https://medidn.test',
                'payment_terms' => '30_days',
                'notes' => 'Nha cung cap khu vuc mien Trung cho phong kham nha khoa.',
                'active' => true,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function materialSeedData(): array
    {
        return [
            ['sku' => 'MED-001', 'name' => 'Lidocaine 2% (Hop 50 ong)', 'category' => 'medicine', 'manufacturer' => 'Novocol', 'supplier_code' => 'DONGDO', 'unit' => 'Hop', 'stock_qty' => 0, 'min_stock' => 5, 'reorder_point' => 8, 'cost_price' => 450000, 'sale_price' => 650000, 'storage_location' => 'Tu thuoc A1'],
            ['sku' => 'MED-002', 'name' => 'Articaine 4% voi Epinephrine', 'category' => 'medicine', 'manufacturer' => 'Septodont', 'supplier_code' => 'DONGDO', 'unit' => 'Hop', 'stock_qty' => 0, 'min_stock' => 3, 'reorder_point' => 5, 'cost_price' => 580000, 'sale_price' => 820000, 'storage_location' => 'Tu thuoc A1'],
            ['sku' => 'MED-003', 'name' => 'Amoxicillin 500mg (Hop 100 vien)', 'category' => 'medicine', 'manufacturer' => 'DHG Pharma', 'supplier_code' => 'DONGDO', 'unit' => 'Hop', 'stock_qty' => 0, 'min_stock' => 10, 'reorder_point' => 15, 'cost_price' => 120000, 'sale_price' => 180000, 'storage_location' => 'Tu thuoc B2'],
            ['sku' => 'MED-004', 'name' => 'Ibuprofen 400mg (Hop 100 vien)', 'category' => 'medicine', 'manufacturer' => 'Traphaco', 'supplier_code' => 'DONGDO', 'unit' => 'Hop', 'stock_qty' => 0, 'min_stock' => 10, 'reorder_point' => 15, 'cost_price' => 85000, 'sale_price' => 130000, 'storage_location' => 'Tu thuoc B2'],
            ['sku' => 'MED-005', 'name' => 'Hydrogen Peroxide 3% (Chai 500ml)', 'category' => 'medicine', 'manufacturer' => 'Vinh Phuc', 'supplier_code' => 'DONGDO', 'unit' => 'Chai', 'stock_qty' => 0, 'min_stock' => 15, 'reorder_point' => 20, 'cost_price' => 25000, 'sale_price' => 45000, 'storage_location' => 'Tu thuoc C1'],
            ['sku' => 'CON-001', 'name' => 'Bong cuon (Goi 100g)', 'category' => 'consumable', 'manufacturer' => 'Vina Cotton', 'supplier_code' => 'MINHAN', 'unit' => 'Goi', 'stock_qty' => 0, 'min_stock' => 20, 'reorder_point' => 30, 'cost_price' => 35000, 'sale_price' => 50000, 'storage_location' => 'Ke D1'],
            ['sku' => 'CON-002', 'name' => 'Gac y te (Goi 100 mieng)', 'category' => 'consumable', 'manufacturer' => 'Vina Cotton', 'supplier_code' => 'MINHAN', 'unit' => 'Goi', 'stock_qty' => 0, 'min_stock' => 15, 'reorder_point' => 25, 'cost_price' => 45000, 'sale_price' => 65000, 'storage_location' => 'Ke D1'],
            ['sku' => 'CON-003', 'name' => 'Gang tay nitrile (Hop 100 cai)', 'category' => 'consumable', 'manufacturer' => 'Ansell', 'supplier_code' => 'MINHAN', 'unit' => 'Hop', 'stock_qty' => 0, 'min_stock' => 15, 'reorder_point' => 20, 'cost_price' => 180000, 'sale_price' => 250000, 'storage_location' => 'Ke E1'],
            ['sku' => 'CON-004', 'name' => 'Khau trang y te 4 lop (Hop 50 cai)', 'category' => 'consumable', 'manufacturer' => '3M', 'supplier_code' => 'MINHAN', 'unit' => 'Hop', 'stock_qty' => 0, 'min_stock' => 20, 'reorder_point' => 30, 'cost_price' => 95000, 'sale_price' => 140000, 'storage_location' => 'Ke E1'],
            ['sku' => 'CON-005', 'name' => 'Xilanh 5ml (Hop 100 cai)', 'category' => 'consumable', 'manufacturer' => 'Terumo', 'supplier_code' => 'MINHAN', 'unit' => 'Hop', 'stock_qty' => 0, 'min_stock' => 8, 'reorder_point' => 12, 'cost_price' => 250000, 'sale_price' => 350000, 'storage_location' => 'Tu D3'],
            ['sku' => 'CON-006', 'name' => 'Kim tiem 27G (Hop 100 cai)', 'category' => 'consumable', 'manufacturer' => 'Terumo', 'supplier_code' => 'MINHAN', 'unit' => 'Hop', 'stock_qty' => 0, 'min_stock' => 10, 'reorder_point' => 15, 'cost_price' => 120000, 'sale_price' => 180000, 'storage_location' => 'Tu D3'],
            ['sku' => 'CON-007', 'name' => 'Saliva ejector (Tui 100 cai)', 'category' => 'consumable', 'manufacturer' => 'Dentsply', 'supplier_code' => 'SGDS', 'unit' => 'Tui', 'stock_qty' => 0, 'min_stock' => 25, 'reorder_point' => 40, 'cost_price' => 150000, 'sale_price' => 220000, 'storage_location' => 'Ke F2'],
            ['sku' => 'CON-008', 'name' => 'Dental bib (Hop 500 tam)', 'category' => 'consumable', 'manufacturer' => 'Medicom', 'supplier_code' => 'MINHAN', 'unit' => 'Hop', 'stock_qty' => 0, 'min_stock' => 5, 'reorder_point' => 8, 'cost_price' => 320000, 'sale_price' => 450000, 'storage_location' => 'Ke F1'],
            ['sku' => 'EQP-001', 'name' => 'Handpiece toc do cao NSK', 'category' => 'equipment', 'manufacturer' => 'NSK', 'supplier_code' => 'NSKVN', 'unit' => 'Cai', 'stock_qty' => 3, 'min_stock' => 1, 'reorder_point' => 2, 'cost_price' => 8500000, 'sale_price' => 12000000, 'storage_location' => 'Tu thiet bi A'],
            ['sku' => 'EQP-002', 'name' => 'May cao voi rang Ultrasonic', 'category' => 'equipment', 'manufacturer' => 'EMS', 'supplier_code' => 'NSKVN', 'unit' => 'Cai', 'stock_qty' => 2, 'min_stock' => 1, 'reorder_point' => 1, 'cost_price' => 15000000, 'sale_price' => 22000000, 'storage_location' => 'Tu thiet bi A'],
            ['sku' => 'EQP-003', 'name' => 'Den quang trung hop LED', 'category' => 'equipment', 'manufacturer' => '3M ESPE', 'supplier_code' => 'MINHAN', 'unit' => 'Cai', 'stock_qty' => 4, 'min_stock' => 2, 'reorder_point' => 3, 'cost_price' => 4500000, 'sale_price' => 6500000, 'storage_location' => 'Tu thiet bi B'],
            ['sku' => 'EQP-004', 'name' => 'Suction tip dung mot lan (Tui 50 cai)', 'category' => 'equipment', 'manufacturer' => 'Dentsply', 'supplier_code' => 'SGDS', 'unit' => 'Tui', 'stock_qty' => 25, 'min_stock' => 10, 'reorder_point' => 15, 'cost_price' => 280000, 'sale_price' => 400000, 'storage_location' => 'Ke G1'],
            ['sku' => 'EQP-005', 'name' => 'Dental mirror (Hop 12 cai)', 'category' => 'equipment', 'manufacturer' => 'ASA Dental', 'supplier_code' => 'MINHAN', 'unit' => 'Hop', 'stock_qty' => 8, 'min_stock' => 3, 'reorder_point' => 5, 'cost_price' => 450000, 'sale_price' => 650000, 'storage_location' => 'Tu dung cu C'],
            ['sku' => 'MAT-001', 'name' => 'Composite resin A2 (Xilanh 4g)', 'category' => 'dental_material', 'manufacturer' => '3M ESPE', 'supplier_code' => 'SGDS', 'unit' => 'Xilanh', 'stock_qty' => 0, 'min_stock' => 15, 'reorder_point' => 25, 'cost_price' => 380000, 'sale_price' => 550000, 'storage_location' => 'Tu lanh vat lieu'],
            ['sku' => 'MAT-002', 'name' => 'Glass ionomer cement (Lo 15g)', 'category' => 'dental_material', 'manufacturer' => 'GC Corporation', 'supplier_code' => 'SGDS', 'unit' => 'Lo', 'stock_qty' => 0, 'min_stock' => 10, 'reorder_point' => 18, 'cost_price' => 420000, 'sale_price' => 600000, 'storage_location' => 'Tu vat lieu A'],
            ['sku' => 'MAT-003', 'name' => 'Dental cement (Bo 35g bot + 15ml dung dich)', 'category' => 'dental_material', 'manufacturer' => 'Dentsply', 'supplier_code' => 'SGDS', 'unit' => 'Bo', 'stock_qty' => 0, 'min_stock' => 8, 'reorder_point' => 12, 'cost_price' => 290000, 'sale_price' => 420000, 'storage_location' => 'Tu vat lieu A'],
            ['sku' => 'MAT-004', 'name' => 'Impression material Alginate (Tui 453g)', 'category' => 'dental_material', 'manufacturer' => 'Zhermack', 'supplier_code' => 'SGDS', 'unit' => 'Tui', 'stock_qty' => 0, 'min_stock' => 8, 'reorder_point' => 12, 'cost_price' => 320000, 'sale_price' => 480000, 'storage_location' => 'Tu vat lieu B'],
            ['sku' => 'MAT-005', 'name' => 'Bonding agent (Chai 5ml)', 'category' => 'dental_material', 'manufacturer' => '3M ESPE', 'supplier_code' => 'MINHAN', 'unit' => 'Chai', 'stock_qty' => 0, 'min_stock' => 10, 'reorder_point' => 15, 'cost_price' => 680000, 'sale_price' => 950000, 'storage_location' => 'Tu lanh vat lieu'],
            ['sku' => 'MAT-006', 'name' => 'Etching gel 37% (Xilanh 3ml)', 'category' => 'dental_material', 'manufacturer' => 'Bisco', 'supplier_code' => 'MINHAN', 'unit' => 'Xilanh', 'stock_qty' => 0, 'min_stock' => 12, 'reorder_point' => 20, 'cost_price' => 180000, 'sale_price' => 280000, 'storage_location' => 'Tu vat lieu A'],
            ['sku' => 'MAT-007', 'name' => 'Temporary filling material (Hop 30g)', 'category' => 'dental_material', 'manufacturer' => 'Dentsply', 'supplier_code' => 'SGDS', 'unit' => 'Hop', 'stock_qty' => 0, 'min_stock' => 15, 'reorder_point' => 25, 'cost_price' => 220000, 'sale_price' => 350000, 'storage_location' => 'Tu vat lieu B'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function batchSeedData(): array
    {
        return [
            ['material_sku' => 'MED-001', 'batch_number' => 'LOT-2025-001', 'expiry_date' => now()->addDays(60), 'quantity' => 150, 'purchase_price' => 430000, 'received_date' => now()->subDays(75)->toDateString(), 'status' => 'active', 'notes' => null],
            ['material_sku' => 'MED-002', 'batch_number' => 'LOT-2025-002', 'expiry_date' => now()->addDays(12), 'quantity' => 20, 'purchase_price' => 560000, 'received_date' => now()->subDays(30)->toDateString(), 'status' => 'active', 'notes' => 'Can uu tien xuat kho truoc.'],
            ['material_sku' => 'MED-003', 'batch_number' => 'LOT-2025-003', 'expiry_date' => now()->addDays(120), 'quantity' => 200, 'purchase_price' => 110000, 'received_date' => now()->subDays(45)->toDateString(), 'status' => 'active', 'notes' => null],
            ['material_sku' => 'MED-004', 'batch_number' => 'LOT-2025-004', 'expiry_date' => now()->addDays(150), 'quantity' => 250, 'purchase_price' => 79000, 'received_date' => now()->subDays(25)->toDateString(), 'status' => 'active', 'notes' => null],
            ['material_sku' => 'MED-005', 'batch_number' => 'LOT-2025-005', 'expiry_date' => now()->subDays(10), 'quantity' => 0, 'purchase_price' => 20000, 'received_date' => now()->subDays(120)->toDateString(), 'status' => 'expired', 'notes' => 'Da het han, khong duoc su dung.'],
            ['material_sku' => 'CON-001', 'batch_number' => 'LOT-2025-010', 'expiry_date' => now()->addDays(240), 'quantity' => 500, 'purchase_price' => 30000, 'received_date' => now()->subDays(20)->toDateString(), 'status' => 'active', 'notes' => null],
            ['material_sku' => 'CON-002', 'batch_number' => 'LOT-2025-011', 'expiry_date' => now()->addDays(270), 'quantity' => 400, 'purchase_price' => 39000, 'received_date' => now()->subDays(22)->toDateString(), 'status' => 'active', 'notes' => null],
            ['material_sku' => 'CON-003', 'batch_number' => 'LOT-2025-012', 'expiry_date' => now()->subDays(30), 'quantity' => 0, 'purchase_price' => 165000, 'received_date' => now()->subDays(200)->toDateString(), 'status' => 'expired', 'notes' => 'Can huy theo quy trinh kiem soat han dung.'],
            ['material_sku' => 'CON-004', 'batch_number' => 'LOT-2025-013', 'expiry_date' => now()->addDays(25), 'quantity' => 70, 'purchase_price' => 88000, 'received_date' => now()->subDays(18)->toDateString(), 'status' => 'active', 'notes' => 'Lot sat han dung, uu tien xuat.'],
            ['material_sku' => 'CON-005', 'batch_number' => 'LOT-2025-014', 'expiry_date' => now()->addDays(365), 'quantity' => 200, 'purchase_price' => 230000, 'received_date' => now()->subDays(10)->toDateString(), 'status' => 'active', 'notes' => null],
            ['material_sku' => 'CON-006', 'batch_number' => 'LOT-2025-015', 'expiry_date' => now()->addDays(28), 'quantity' => 70, 'purchase_price' => 115000, 'received_date' => now()->subDays(28)->toDateString(), 'status' => 'active', 'notes' => 'Can canh bao sap het han.'],
            ['material_sku' => 'CON-007', 'batch_number' => 'LOT-2025-016', 'expiry_date' => now()->addDays(450), 'quantity' => 600, 'purchase_price' => 140000, 'received_date' => now()->subDays(15)->toDateString(), 'status' => 'active', 'notes' => null],
            ['material_sku' => 'CON-008', 'batch_number' => 'LOT-2025-017', 'expiry_date' => now()->addDays(500), 'quantity' => 120, 'purchase_price' => 300000, 'received_date' => now()->subDays(12)->toDateString(), 'status' => 'active', 'notes' => null],
            ['material_sku' => 'MAT-001', 'batch_number' => 'LOT-2025-026', 'expiry_date' => now()->subDays(15), 'quantity' => 0, 'purchase_price' => 360000, 'received_date' => now()->subDays(160)->toDateString(), 'status' => 'expired', 'notes' => 'Da het han, can doi lo moi.'],
            ['material_sku' => 'MAT-001', 'batch_number' => 'LOT-2025-026B', 'expiry_date' => now()->addDays(540), 'quantity' => 450, 'purchase_price' => 372000, 'received_date' => now()->subDays(16)->toDateString(), 'status' => 'active', 'notes' => null],
            ['material_sku' => 'MAT-002', 'batch_number' => 'LOT-2025-027', 'expiry_date' => now()->addDays(10), 'quantity' => 30, 'purchase_price' => 400000, 'received_date' => now()->subDays(40)->toDateString(), 'status' => 'active', 'notes' => 'Can theo doi sat han dung.'],
            ['material_sku' => 'MAT-003', 'batch_number' => 'LOT-2025-028', 'expiry_date' => now()->addDays(650), 'quantity' => 220, 'purchase_price' => 275000, 'received_date' => now()->subDays(20)->toDateString(), 'status' => 'active', 'notes' => null],
            ['material_sku' => 'MAT-004', 'batch_number' => 'LOT-2025-029', 'expiry_date' => now()->addDays(700), 'quantity' => 180, 'purchase_price' => 300000, 'received_date' => now()->subDays(14)->toDateString(), 'status' => 'active', 'notes' => null],
            ['material_sku' => 'MAT-005', 'batch_number' => 'LOT-2025-030', 'expiry_date' => now()->addDays(18), 'quantity' => 25, 'purchase_price' => 640000, 'received_date' => now()->subDays(22)->toDateString(), 'status' => 'active', 'notes' => 'Dat nguong canh bao 30 ngay.'],
            ['material_sku' => 'MAT-006', 'batch_number' => 'LOT-2025-031', 'expiry_date' => now()->addDays(800), 'quantity' => 350, 'purchase_price' => 165000, 'received_date' => now()->subDays(8)->toDateString(), 'status' => 'active', 'notes' => null],
            ['material_sku' => 'MAT-007', 'batch_number' => 'LOT-2025-032', 'expiry_date' => now()->addDays(900), 'quantity' => 400, 'purchase_price' => 200000, 'received_date' => now()->subDays(11)->toDateString(), 'status' => 'active', 'notes' => null],
        ];
    }
}
