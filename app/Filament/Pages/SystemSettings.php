<?php

namespace App\Filament\Pages;

use App\Filament\Resources\CustomerGroups\CustomerGroupResource;
use App\Filament\Resources\Diseases\DiseaseGroupResource;
use App\Filament\Resources\Diseases\DiseaseResource;
use App\Filament\Resources\PromotionGroups\PromotionGroupResource;
use App\Filament\Resources\ServiceCategories\ServiceCategoryResource;
use App\Filament\Resources\ToothConditions\ToothConditionResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Branches\BranchResource;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;

class SystemSettings extends Page
{
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Tổng quan cài đặt';

    protected static string|\UnitEnum|null $navigationGroup = 'Cài đặt hệ thống';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'system-settings';

    protected string $view = 'filament.pages.system-settings';

    public function getHeading(): string
    {
        return 'Cài đặt hệ thống';
    }

    public function getTitle(): string
    {
        return $this->getHeading();
    }

    public function getSubheading(): string
    {
        return 'Quản lý danh mục và cấu hình nền tảng dùng chung cho toàn bộ hệ thống CRM.';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSettingSections(): array
    {
        $sections = [
            [
                'title' => 'Danh mục khám & điều trị',
                'description' => 'Chuẩn hóa dữ liệu dùng trong tab Khám & Điều trị và các màn hình điều trị.',
                'items' => [
                    [
                        'label' => 'Danh mục bệnh',
                        'description' => 'Quản lý nhóm bệnh tổng quát để phân loại các mã bệnh.',
                        'url' => DiseaseGroupResource::getUrl('index'),
                        'can' => static fn (): bool => DiseaseGroupResource::canAccess(),
                    ],
                    [
                        'label' => 'Cấu hình bệnh',
                        'description' => 'Quản lý mã bệnh, tên bệnh và trạng thái sử dụng khi chẩn đoán.',
                        'url' => DiseaseResource::getUrl('index'),
                        'can' => static fn (): bool => DiseaseResource::canAccess(),
                    ],
                    [
                        'label' => 'Danh mục tình trạng răng',
                        'description' => 'Quản lý danh sách tình trạng răng hiển thị trong popup chọn khi click vào răng.',
                        'url' => ToothConditionResource::getUrl('index'),
                        'can' => static fn (): bool => ToothConditionResource::canAccess(),
                    ],
                    [
                        'label' => 'Danh mục dịch vụ',
                        'description' => 'Quản lý cây danh mục dịch vụ khám và điều trị.',
                        'url' => ServiceCategoryResource::getUrl('index'),
                        'can' => static fn (): bool => ServiceCategoryResource::canAccess(),
                    ],
                ],
            ],
            [
                'title' => 'Cài đặt tích hợp',
                'description' => 'Thiết lập các kết nối ngoài hệ thống.',
                'items' => [
                    [
                        'label' => 'Zalo, ZNS, Google Calendar, VNPay, EMR',
                        'description' => 'Quản lý toàn bộ cấu hình tích hợp trong bảng clinic_settings.',
                        'url' => IntegrationSettings::getUrl(),
                        'can' => static fn (): bool => IntegrationSettings::canAccess(),
                    ],
                ],
            ],
            [
                'title' => 'Danh mục khách hàng',
                'description' => 'Các phân nhóm phục vụ marketing, chăm sóc và phân loại hồ sơ.',
                'items' => [
                    [
                        'label' => 'Nhóm khách hàng',
                        'description' => 'Phân loại lead/bệnh nhân theo tệp khách hàng.',
                        'url' => CustomerGroupResource::getUrl('index'),
                        'can' => static fn (): bool => CustomerGroupResource::canAccess(),
                    ],
                    [
                        'label' => 'Nhóm khuyến mãi',
                        'description' => 'Phân loại chương trình ưu đãi áp cho khách hàng.',
                        'url' => PromotionGroupResource::getUrl('index'),
                        'can' => static fn (): bool => PromotionGroupResource::canAccess(),
                    ],
                ],
            ],
            [
                'title' => 'Cấu hình vận hành',
                'description' => 'Thiết lập nhân sự và chi nhánh phục vụ phân quyền, điều phối vận hành.',
                'items' => [
                    [
                        'label' => 'Người dùng',
                        'description' => 'Tạo tài khoản vận hành và gán vai trò hệ thống.',
                        'url' => UserResource::getUrl('index'),
                        'can' => static fn (): bool => UserResource::canAccess(),
                    ],
                    [
                        'label' => 'Bác sĩ',
                        'description' => 'Quản lý hồ sơ bác sĩ trong danh sách người dùng (vai trò Bác sĩ).',
                        'url' => UserResource::getUrl('index'),
                        'can' => static fn (): bool => UserResource::canAccess(),
                    ],
                    [
                        'label' => 'Chi nhánh',
                        'description' => 'Quản lý cấu trúc chi nhánh cho toàn hệ thống.',
                        'url' => BranchResource::getUrl('index'),
                        'can' => static fn (): bool => BranchResource::canAccess(),
                    ],
                ],
            ],
        ];

        return collect($sections)
            ->map(function (array $section): array {
                $section['items'] = collect($section['items'] ?? [])
                    ->filter(function (array $item): bool {
                        if (!array_key_exists('can', $item)) {
                            return true;
                        }

                        return (bool) value($item['can']);
                    })
                    ->values()
                    ->all();

                return $section;
            })
            ->filter(fn (array $section): bool => !empty($section['items']))
            ->values()
            ->all();
    }
}
