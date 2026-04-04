<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\DeliveryOpsCenterService;
use BackedEnum;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use UnitEnum;

class DeliveryOpsCenter extends Page
{
    use BuildsControlCenterPageViewState;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Điều phối điều trị';

    protected static string|UnitEnum|null $navigationGroup = 'Hoạt động hàng ngày';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'delivery-ops-center';

    protected string $view = 'filament.pages.delivery-ops-center';

    public array $state = [];

    public static function canAccess(): bool
    {
        $authUser = auth()->user();

        return $authUser instanceof User
            && $authUser->can('View:DeliveryOpsCenter')
            && $authUser->hasAnyAccessibleBranch();
    }

    public function mount(DeliveryOpsCenterService $service): void
    {
        $this->state = $service->build();
    }

    public function getHeading(): string
    {
        return 'Điều phối điều trị';
    }

    public function getTitle(): string
    {
        return $this->getHeading();
    }

    public function getSubheading(): string
    {
        return 'Nhìn nhanh điều trị, consent, kho và labo để điều phối bác sĩ, phiên điều trị và vật tư trong cùng một màn hình.';
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
            quickLinksHeading: 'Lối tắt delivery',
            quickLinksDescription: 'Đi thẳng tới các màn hình điều trị, EMR, kho và labo đang được dùng trong ca vận hành.',
            quickLinksGridClasses: 'grid gap-4 md:grid-cols-2 xl:grid-cols-6',
        );
    }
}
