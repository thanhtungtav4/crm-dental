<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Support\ClinicRuntimeSettings;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->color('info'),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('Tất cả'),
        ];

        foreach (ClinicRuntimeSettings::customerStatusOptions() as $status => $label) {
            $tabs["status_{$status}"] = Tab::make($label)
                ->query(fn ($query) => $query->where('status', $status));
        }

        return $tabs;
    }
}
