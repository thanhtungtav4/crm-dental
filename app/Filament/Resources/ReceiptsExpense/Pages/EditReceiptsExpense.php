<?php

namespace App\Filament\Resources\ReceiptsExpense\Pages;

use App\Filament\Resources\ReceiptsExpense\ReceiptsExpenseResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditReceiptsExpense extends EditRecord
{
    protected static string $resource = ReceiptsExpenseResource::class;

    public function mount(int|string $record): void
    {
        if (! ReceiptsExpenseResource::hasBackingTable()) {
            Notification::make()
                ->title('Thiếu bảng dữ liệu Thu/chi')
                ->body('Vui lòng chạy migration trước khi sử dụng module này: php artisan migrate')
                ->danger()
                ->send();

            $this->redirect(filament()->getHomeUrl());

            return;
        }

        parent::mount($record);
    }
}
