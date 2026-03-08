<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\DeliveryOpsCenterService;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class DeliveryOpsCenter extends Page
{
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
