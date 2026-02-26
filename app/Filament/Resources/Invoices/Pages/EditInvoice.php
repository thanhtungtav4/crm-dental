<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recordPayment')
                ->label('Tạo thanh toán')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->url(fn (): string => route('filament.admin.resources.payments.create', [
                    'invoice_id' => $this->record->id,
                ])),

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
