<?php

namespace Database\Seeders;

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
            ['code' => 'K02', 'name' => '(K 02) K02 Sâu răng', 'category' => 'Bệnh lý', 'sort_order' => 10, 'color' => '#ef4444'],
            ['code' => 'RD', 'name' => '(RD) Hàm giả tháo lắp', 'category' => 'Phục hình', 'sort_order' => 20, 'color' => '#3b82f6'],
            ['code' => 'SR', 'name' => '(SR) Sâu răng', 'category' => 'Bệnh lý', 'sort_order' => 30, 'color' => '#dc2626'],
            ['code' => 'RV', 'name' => '(RV) Răng viêm', 'category' => 'Bệnh lý', 'sort_order' => 40, 'color' => '#b91c1c'],
            ['code' => 'SL', 'name' => '(SL) Sâu lớn lộ tủy', 'category' => 'Bệnh lý', 'sort_order' => 50, 'color' => '#991b1b'],
            ['code' => 'A', 'name' => '(A) Miếng trám Amalgam', 'category' => 'Phục hình', 'sort_order' => 60, 'color' => '#64748b'],
            ['code' => 'RSHB', 'name' => '(RSHB) Răng sứ hở bờ', 'category' => 'Phục hình', 'sort_order' => 70, 'color' => '#a855f7'],
            ['code' => 'RS', 'name' => '(RS) Răng sâu', 'category' => 'Bệnh lý', 'sort_order' => 80, 'color' => '#7f1d1d'],

            // Right column (matching DentalFlow modal layout)
            ['code' => 'MR', 'name' => '(MR) Mòn cổ răng', 'category' => 'Bệnh lý', 'sort_order' => 90, 'color' => '#84cc16'],
            ['code' => 'IMP', 'name' => '(Imp) Implant', 'category' => 'Phục hình', 'sort_order' => 100, 'color' => '#22c55e'],
            ['code' => 'RKK', 'name' => '(RKK) Răng khấp khểnh', 'category' => 'Hiện trạng', 'sort_order' => 110, 'color' => '#eab308'],
            ['code' => 'R99', 'name' => '(99) Răng siêu khôn', 'category' => 'Hiện trạng', 'sort_order' => 120, 'color' => '#be123c'],
            ['code' => 'SMN', 'name' => '(SMN) Sâu răng mặt nhai', 'category' => 'Bệnh lý', 'sort_order' => 130, 'color' => '#65a30d'],
            ['code' => 'MER', 'name' => '(MR) Mẻ răng', 'category' => 'Bệnh lý', 'sort_order' => 140, 'color' => '#f97316'],
            ['code' => 'VN', 'name' => '(VN) Viêm nướu', 'category' => 'Bệnh lý', 'sort_order' => 150, 'color' => '#fca5a5'],
            ['code' => 'HC', 'name' => '(HC) Răng sâu', 'category' => 'Bệnh lý', 'sort_order' => 160, 'color' => '#f87171'],

            // Other
            ['code' => 'MAT', 'name' => 'Mất răng', 'category' => 'Hiện trạng', 'sort_order' => 170, 'color' => '#0f172a'],
            ['code' => 'RTR', 'name' => 'Mão sứ', 'category' => 'Phục hình', 'sort_order' => 180, 'color' => '#06b6d4'],
            ['code' => 'KHAC', 'name' => '(*) Khác', 'category' => 'Khác', 'sort_order' => 999, 'color' => '#9ca3af'],
        ];

        foreach ($conditions as $condition) {
            \App\Models\ToothCondition::updateOrCreate(
                ['code' => $condition['code']],
                $condition
            );
        }

        $knownCodes = collect($conditions)
            ->pluck('code')
            ->map(fn (string $code): string => strtoupper($code))
            ->all();

        \App\Models\ToothCondition::query()
            ->whereNotIn('code', $knownCodes)
            ->where(function ($query) {
                $query->whereNull('sort_order')->orWhere('sort_order', 0);
            })
            ->update(['sort_order' => 5000]);
    }
}
