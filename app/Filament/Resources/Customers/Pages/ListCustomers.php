<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => \Filament\Schemas\Components\Tabs\Tab::make('Tất cả'),
            'lead' => \Filament\Schemas\Components\Tabs\Tab::make('Lead Mới')
                ->query(fn($query) => $query->where('status', 'lead')),
            'contacted' => \Filament\Schemas\Components\Tabs\Tab::make('Đã liên hệ')
                ->query(fn($query) => $query->where('status', 'contacted')),
            'confirmed' => \Filament\Schemas\Components\Tabs\Tab::make('Đã xác nhận')
                ->query(fn($query) => $query->where('status', 'confirmed')),
            'converted' => \Filament\Schemas\Components\Tabs\Tab::make('Đã chuyển đổi')
                ->query(fn($query) => $query->where('status', 'converted')),
            'lost' => \Filament\Schemas\Components\Tabs\Tab::make('Mất Lead')
                ->query(fn($query) => $query->where('status', 'lost')),
        ];
    }
}
