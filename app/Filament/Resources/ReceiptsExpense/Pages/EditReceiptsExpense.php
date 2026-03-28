<?php

namespace App\Filament\Resources\ReceiptsExpense\Pages;

use App\Filament\Resources\ReceiptsExpense\ReceiptsExpenseResource;
use App\Models\ReceiptExpense;
use App\Services\ReceiptExpenseWorkflowService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditReceiptsExpense extends EditRecord
{
    protected static string $resource = ReceiptsExpenseResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(ReceiptExpenseWorkflowService::class)->prepareEditablePayload($this->getRecord(), $data);
    }

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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Duyệt')
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === ReceiptExpense::STATUS_DRAFT)
                ->form([
                    Textarea::make('reason')
                        ->label('Ghi chú duyệt')
                        ->rows(3),
                ])
                ->successNotificationTitle('Đã duyệt phiếu thu/chi')
                ->action(function (array $data): void {
                    app(ReceiptExpenseWorkflowService::class)->approve(
                        receiptExpense: $this->getRecord(),
                        reason: $data['reason'] ?? null,
                    );

                    $this->refreshFormData(['status', 'posted_at', 'posted_by']);
                }),
            Action::make('post')
                ->label('Hạch toán')
                ->icon('heroicon-o-arrow-up-on-square')
                ->color('info')
                ->visible(fn (): bool => in_array($this->record->status, [
                    ReceiptExpense::STATUS_DRAFT,
                    ReceiptExpense::STATUS_APPROVED,
                ], true))
                ->form([
                    Textarea::make('reason')
                        ->label('Ghi chú hạch toán')
                        ->rows(3),
                ])
                ->successNotificationTitle('Đã hạch toán phiếu thu/chi')
                ->action(function (array $data): void {
                    app(ReceiptExpenseWorkflowService::class)->post(
                        receiptExpense: $this->getRecord(),
                        reason: $data['reason'] ?? null,
                    );

                    $this->refreshFormData(['status', 'posted_at', 'posted_by']);
                }),
        ];
    }
}
