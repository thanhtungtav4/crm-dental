<?php

namespace App\Filament\Resources\ReceiptsExpense\Pages;

use App\Filament\Resources\ReceiptsExpense\ReceiptsExpenseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReceiptsExpense extends ListRecords
{
    protected static string $resource = ReceiptsExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tạo phiếu'),
        ];
    }
}
