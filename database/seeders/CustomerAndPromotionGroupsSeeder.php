<?php

namespace Database\Seeders;

use App\Models\CustomerGroup;
use App\Models\PromotionGroup;
use Illuminate\Database\Seeder;

class CustomerAndPromotionGroupsSeeder extends Seeder
{
    public function run(): void
    {
        $customerGroups = [
            [
                'code' => 'NEW',
                'name' => 'Khách mới',
                'description' => 'Khách mới phát sinh trong 30 ngày gần nhất.',
                'is_active' => true,
            ],
            [
                'code' => 'RETURN',
                'name' => 'Khách tái khám',
                'description' => 'Khách đã điều trị và quay lại tái khám/tái tư vấn.',
                'is_active' => true,
            ],
            [
                'code' => 'VIP',
                'name' => 'Khách VIP',
                'description' => 'Khách có giá trị điều trị cao hoặc sử dụng nhiều dịch vụ.',
                'is_active' => true,
            ],
            [
                'code' => 'FAMILY',
                'name' => 'Khách gia đình',
                'description' => 'Nhóm khách đến theo hộ gia đình hoặc người thân giới thiệu.',
                'is_active' => true,
            ],
            [
                'code' => 'KID',
                'name' => 'Khách nhi',
                'description' => 'Khách hàng trẻ em, ưu tiên dịch vụ nha khoa trẻ em.',
                'is_active' => true,
            ],
            [
                'code' => 'CORP',
                'name' => 'Khách doanh nghiệp',
                'description' => 'Khách từ gói hợp tác doanh nghiệp/tổ chức.',
                'is_active' => true,
            ],
        ];

        foreach ($customerGroups as $group) {
            CustomerGroup::updateOrCreate(
                ['code' => $group['code']],
                $group,
            );
        }

        $promotionGroups = [
            [
                'code' => 'PROMO-NEW',
                'name' => 'Ưu đãi khách mới',
                'description' => 'Nhóm ưu đãi cho lead mới tạo hồ sơ lần đầu.',
                'is_active' => true,
            ],
            [
                'code' => 'PROMO-IMPL',
                'name' => 'Ưu đãi Implant',
                'description' => 'Gói ưu đãi chuyên sâu cho dịch vụ Implant.',
                'is_active' => true,
            ],
            [
                'code' => 'PROMO-ORTHO',
                'name' => 'Ưu đãi chỉnh nha',
                'description' => 'Ưu đãi cho dịch vụ niềng răng/chỉnh nha.',
                'is_active' => true,
            ],
            [
                'code' => 'PROMO-ESTH',
                'name' => 'Ưu đãi thẩm mỹ',
                'description' => 'Gói khuyến mãi cho tẩy trắng, veneer, bọc sứ.',
                'is_active' => true,
            ],
            [
                'code' => 'PROMO-FAMILY',
                'name' => 'Ưu đãi gia đình',
                'description' => 'Khuyến mãi theo nhóm khách đi theo gia đình.',
                'is_active' => true,
            ],
            [
                'code' => 'PROMO-OLD',
                'name' => 'Ưu đãi cũ',
                'description' => 'Nhóm khuyến mãi lịch sử đã ngừng áp dụng.',
                'is_active' => false,
            ],
        ];

        foreach ($promotionGroups as $group) {
            PromotionGroup::updateOrCreate(
                ['code' => $group['code']],
                $group,
            );
        }
    }
}

