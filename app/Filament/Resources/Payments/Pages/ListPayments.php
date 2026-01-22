<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Payments\Widgets\PaymentStatsWidget;
use App\Models\Invoice;
use App\Models\Payment;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tạo thanh toán'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PaymentStatsWidget::class,
        ];
    }
}
