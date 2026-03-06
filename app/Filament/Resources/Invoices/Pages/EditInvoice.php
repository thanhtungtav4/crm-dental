<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceWorkflowService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(InvoiceWorkflowService::class)->prepareEditablePayload($this->getRecord(), $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recordPayment')
                ->label('Tạo thanh toán')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn (): bool => $this->getRecord()->status !== Invoice::STATUS_CANCELLED)
                ->url(fn (): string => route('filament.admin.resources.payments.create', [
                    'invoice_id' => $this->record->id,
                ])),

            Action::make('cancel_invoice')
                ->label('Hủy hóa đơn')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->form([
                    Textarea::make('reason')
                        ->label('Lý do hủy')
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->successNotificationTitle('Đã hủy hóa đơn')
                ->visible(fn (): bool => $this->getRecord()->canBeCancelled())
                ->action(function (array $data): void {
                    app(InvoiceWorkflowService::class)->cancel($this->getRecord(), $data['reason'] ?? null);
                    $this->refreshFormData(['status', 'paid_at']);
                }),

            Action::make('print')
                ->label('In hóa đơn')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('invoices.print', $this->record))
                ->openUrlInNewTab(),

            DeleteAction::make(),
        ];
    }
}
