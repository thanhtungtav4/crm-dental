<?php

namespace App\Filament\Resources\ReceiptsExpense\Pages;

use App\Filament\Resources\ReceiptsExpense\ReceiptsExpenseResource;
use App\Services\ReceiptExpenseWorkflowService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateReceiptsExpense extends CreateRecord
{
    protected static string $resource = ReceiptsExpenseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return app(ReceiptExpenseWorkflowService::class)->prepareCreatePayload($data);
    }

    public function mount(): void
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

        parent::mount();
    }
}
