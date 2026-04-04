<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\FrontdeskControlCenterService;
use BackedEnum;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use UnitEnum;

class FrontdeskControlCenter extends Page
{
    use BuildsControlCenterPageViewState;

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
     * @return array{
     *   overview_panel:array{
     *     cards:array<int, array<string, mixed>>
     *   },
     *   quick_links_panel:array{
     *     heading:string,
     *     description:string,
     *     grid_classes:string,
     *     links:array<int, array<string, mixed>>
     *   },
     *   sections_panel:array{
     *     sections:array<int, array<string, mixed>>
     *   }
     * }
     */
    #[Computed]
    public function pageViewState(): array
    {
        return $this->buildControlCenterPageViewState(
            quickLinksHeading: 'Lối tắt front-office',
            quickLinksDescription: 'Đi thẳng tới các màn hình hot-path mà không cần tìm lại trong menu.',
            quickLinksGridClasses: 'grid gap-4 md:grid-cols-2 xl:grid-cols-5',
        );
    }
}
