<?php

namespace Database\Seeders;

use App\Models\Disease;
use App\Models\DiseaseGroup;
use Illuminate\Database\Seeder;

class DiseaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create disease groups
        $groups = [
            [
                'name' => 'Bệnh của mô cứng của răng',
                'description' => 'Sâu răng và các bệnh lý mô cứng',
                'sort_order' => 1,
                'diseases' => [
                    ['code' => 'K02.0', 'name' => 'Sâu men răng', 'description' => 'Sâu giới hạn ở men'],
                    ['code' => 'K02.1', 'name' => 'Sâu ngà răng', 'description' => 'Sâu đến ngà răng'],
                    ['code' => 'K02.2', 'name' => 'Sâu xi măng răng', 'description' => 'Sâu xi măng'],
                    ['code' => 'K02.3', 'name' => 'Sâu răng ngừng tiến triển', 'description' => 'Sâu đã ổn định'],
                    ['code' => 'K02.9', 'name' => 'Sâu răng không đặc hiệu', 'description' => 'Sâu răng chưa phân loại'],
                    ['code' => 'K03.0', 'name' => 'Mòn răng quá mức', 'description' => 'Mài mòn bất thường'],
                    ['code' => 'K03.1', 'name' => 'Mài mòn răng', 'description' => 'Do bàn chải, thức ăn'],
                    ['code' => 'K03.2', 'name' => 'Xói mòn răng', 'description' => 'Do acid'],
                    ['code' => 'K03.8', 'name' => 'Bệnh mô cứng răng xác định khác', 'description' => 'Bệnh mô cứng khác'],
                ],
            ],
            [
                'name' => 'Bệnh của tủy răng và mô quanh chóp',
                'description' => 'Viêm tủy, tủy hoại tử, viêm quanh chóp',
                'sort_order' => 2,
                'diseases' => [
                    ['code' => 'K04.0', 'name' => 'Viêm tủy', 'description' => 'Viêm tủy răng cấp/mãn'],
                    ['code' => 'K04.1', 'name' => 'Tủy hoại tử', 'description' => 'Hoại tử mô tủy'],
                    ['code' => 'K04.2', 'name' => 'Thoái hóa tủy', 'description' => 'Xơ hóa, canxi hóa tủy'],
                    ['code' => 'K04.3', 'name' => 'Tạo ngà bất thường trong tủy', 'description' => 'Ngà thứ cấp bất thường'],
                    ['code' => 'K04.4', 'name' => 'Viêm quanh chóp cấp nguồn gốc tủy', 'description' => 'Viêm cấp tính'],
                    ['code' => 'K04.5', 'name' => 'Viêm quanh chóp mãn', 'description' => 'U hạt quanh chóp'],
                    ['code' => 'K04.6', 'name' => 'Áp xe quanh chóp có lỗ dò', 'description' => 'Áp xe dẫn lưu'],
                    ['code' => 'K04.7', 'name' => 'Áp xe quanh chóp không có lỗ dò', 'description' => 'Áp xe kín'],
                    ['code' => 'K04.8', 'name' => 'Nang chân răng', 'description' => 'Nang do viêm quanh chóp'],
                ],
            ],
            [
                'name' => 'Bệnh của nướu và nha chu',
                'description' => 'Viêm nướu, viêm nha chu',
                'sort_order' => 3,
                'diseases' => [
                    ['code' => 'K05.0', 'name' => 'Viêm nướu cấp', 'description' => 'Viêm nướu cấp tính'],
                    ['code' => 'K05.1', 'name' => 'Viêm nướu mãn', 'description' => 'Viêm nướu mãn tính'],
                    ['code' => 'K05.2', 'name' => 'Viêm nha chu cấp', 'description' => 'Viêm nha chu cấp tính'],
                    ['code' => 'K05.3', 'name' => 'Viêm nha chu mãn', 'description' => 'Viêm nha chu mãn tính'],
                    ['code' => 'K05.4', 'name' => 'Tiêu nha chu', 'description' => 'Bệnh nha chu gây tiêu xương'],
                    ['code' => 'K05.5', 'name' => 'Bệnh nha chu khác xác định', 'description' => 'Bệnh nha chu khác'],
                    ['code' => 'K05.6', 'name' => 'Bệnh nha chu không đặc hiệu', 'description' => 'Bệnh nha chu chưa phân loại'],
                    ['code' => 'K06.0', 'name' => 'Tụt nướu', 'description' => 'Lở loét nướu, tụt lợi'],
                    ['code' => 'K06.1', 'name' => 'Tăng sản nướu', 'description' => 'Phì đại nướu'],
                ],
            ],
            [
                'name' => 'Các bất thường khớp cắn',
                'description' => 'Sai khớp cắn, khớp thái dương hàm',
                'sort_order' => 4,
                'diseases' => [
                    ['code' => 'K07.0', 'name' => 'Bất thường kích thước hàm', 'description' => 'Hàm lớn hoặc nhỏ bất thường'],
                    ['code' => 'K07.1', 'name' => 'Bất thường tương quan hàm-sọ', 'description' => 'Sai lệch vị trí hàm'],
                    ['code' => 'K07.2', 'name' => 'Bất thường tương quan cung răng', 'description' => 'Bất thường khớp cắn'],
                    ['code' => 'K07.3', 'name' => 'Bất thường vị trí răng', 'description' => 'Răng lệch, xoay'],
                    ['code' => 'K07.4', 'name' => 'Sai khớp cắn không xác định', 'description' => 'Sai khớp cắn chung'],
                    ['code' => 'K07.5', 'name' => 'Bất thường chức năng hàm mặt', 'description' => 'Rối loạn khớp thái dương hàm'],
                    ['code' => 'K07.6', 'name' => 'Bệnh khớp thái dương hàm', 'description' => 'Rối loạn TMJ'],
                ],
            ],
            [
                'name' => 'Các bệnh răng miệng khác',
                'description' => 'Các bệnh lý khác ở miệng',
                'sort_order' => 5,
                'diseases' => [
                    ['code' => 'K08.0', 'name' => 'Rụng răng do bệnh toàn thân', 'description' => 'Mất răng không do chấn thương'],
                    ['code' => 'K08.1', 'name' => 'Mất răng do tai nạn, nhổ', 'description' => 'Mất răng do can thiệp'],
                    ['code' => 'K08.2', 'name' => 'Tiêu xương ổ răng', 'description' => 'Tiêu xương'],
                    ['code' => 'K08.3', 'name' => 'Sót chân răng', 'description' => 'Chân răng còn lại'],
                    ['code' => 'K09.0', 'name' => 'Nang phát triển răng', 'description' => 'Nang do mầm răng'],
                    ['code' => 'K10.0', 'name' => 'Rối loạn phát triển hàm', 'description' => 'Bất thường xương hàm'],
                    ['code' => 'K10.2', 'name' => 'Hoại tử xương hàm', 'description' => 'Viêm xương hàm'],
                    ['code' => 'K12.0', 'name' => 'Viêm miệng Aphtha tái phát', 'description' => 'Loét aphtha'],
                    ['code' => 'K13.0', 'name' => 'Bệnh môi', 'description' => 'Viêm môi, nứt môi'],
                    ['code' => 'S02.5', 'name' => 'Gãy răng', 'description' => 'Chấn thương răng'],
                ],
            ],
        ];

        foreach ($groups as $groupData) {
            $diseases = $groupData['diseases'];
            unset($groupData['diseases']);

            $group = DiseaseGroup::firstOrCreate(
                ['name' => $groupData['name']],
                $groupData
            );

            foreach ($diseases as $diseaseData) {
                Disease::firstOrCreate(
                    ['code' => $diseaseData['code']],
                    array_merge($diseaseData, ['disease_group_id' => $group->id])
                );
            }
        }
    }
}
