<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\FrontdeskControlCenterService;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class FrontdeskControlCenter extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Điều phối front-office';

    protected static string|UnitEnum|null $navigationGroup = 'Chăm sóc khách hàng';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'frontdesk-control-center';

    protected string $view = 'filament.pages.frontdesk-control-center';

    public array $state = [];

    public static function canAccess(): bool
    {
        $authUser = auth()->user();

        return $authUser instanceof User
            && $authUser->can('View:FrontdeskControlCenter')
            && $authUser->hasAnyAccessibleBranch();
    }

    public function mount(FrontdeskControlCenterService $service): void
    {
        $this->state = $service->build();
    }

    public function getHeading(): string
    {
        return 'Điều phối front-office';
    }

    public function getTitle(): string
    {
        return $this->getHeading();
    }

    public function getSubheading(): string
    {
        return 'Nhìn nhanh lead đang mở, lịch hẹn sắp tới và queue CSKH để điều phối trong ngày mà không cần nhảy qua nhiều module.';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOverviewCards(): array
    {
        return array_values((array) ($this->state['overview_cards'] ?? []));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getQuickLinks(): array
    {
        return array_values((array) ($this->state['quick_links'] ?? []));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSections(): array
    {
        return array_values((array) ($this->state['sections'] ?? []));
    }
}
