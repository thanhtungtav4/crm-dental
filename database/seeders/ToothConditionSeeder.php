<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ToothConditionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $conditions = [
            // Left column (matching DentalFlow modal layout)
            ['code' => 'K02', 'name' => '(K 02) K02 Sâu răng', 'category' => 'Bệnh lý', 'color' => '#ef4444'],
            ['code' => 'RD', 'name' => '(RD) Hàm giả tháo lắp', 'category' => 'Phục hình', 'color' => '#3b82f6'],
            ['code' => 'SR', 'name' => '(SR) Sâu răng', 'category' => 'Bệnh lý', 'color' => '#dc2626'],
            ['code' => 'RV', 'name' => '(RV) Răng viêm', 'category' => 'Bệnh lý', 'color' => '#b91c1c'],
            ['code' => 'SL', 'name' => '(SL) Sâu lớn lộ tủy', 'category' => 'Bệnh lý', 'color' => '#991b1b'],
            ['code' => 'A', 'name' => '(A) Miếng trám Amalgam', 'category' => 'Phục hình', 'color' => '#64748b'],
            ['code' => 'RSHB', 'name' => '(RSHB) Răng sứ hở bờ', 'category' => 'Phục hình', 'color' => '#a855f7'],
            ['code' => 'RS', 'name' => '(RS) Răng sâu', 'category' => 'Bệnh lý', 'color' => '#7f1d1d'],

            // Right column (matching DentalFlow modal layout)
            ['code' => 'MR', 'name' => '(MR) Mòn cổ răng', 'category' => 'Bệnh lý', 'color' => '#84cc16'],
            ['code' => 'IMP', 'name' => '(Imp) Implant', 'category' => 'Phục hình', 'color' => '#22c55e'],
            ['code' => 'RKK', 'name' => '(RKK) Răng khấp khểnh', 'category' => 'Hiện trạng', 'color' => '#eab308'],
            ['code' => 'R99', 'name' => '(99) Răng siêu khôn', 'category' => 'Hiện trạng', 'color' => '#be123c'],
            ['code' => 'SMN', 'name' => '(SMN) Sâu răng mặt nhai', 'category' => 'Bệnh lý', 'color' => '#65a30d'],
            ['code' => 'MER', 'name' => '(MR) Mẻ răng', 'category' => 'Bệnh lý', 'color' => '#f97316'],
            ['code' => 'VN', 'name' => '(VN) Viêm nướu', 'category' => 'Bệnh lý', 'color' => '#fca5a5'],
            ['code' => 'HC', 'name' => '(HC) Răng sâu', 'category' => 'Bệnh lý', 'color' => '#f87171'],

            // Other
            ['code' => 'MAT', 'name' => 'Mất răng', 'category' => 'Hiện trạng', 'color' => '#0f172a'],
            ['code' => 'RTR', 'name' => 'Mão sứ', 'category' => 'Phục hình', 'color' => '#06b6d4'],
            ['code' => 'KHAC', 'name' => '(*) Khác', 'category' => 'Khác', 'color' => '#9ca3af'],
        ];

        foreach ($conditions as $condition) {
            \App\Models\ToothCondition::updateOrCreate(
                ['code' => $condition['code']],
                $condition
            );
        }
    }
}
