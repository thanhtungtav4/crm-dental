<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPayment extends EditRecord
{
    protected static string $resource = PaymentResource::class;

    protected ?int $originalInvoiceId = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->originalInvoiceId = $this->record->invoice_id;
    }

    protected function afterSave(): void
    {
        $this->record->invoice?->updatePaidAmount();

        if ($this->originalInvoiceId && $this->originalInvoiceId !== $this->record->invoice_id) {
            \App\Models\Invoice::find($this->originalInvoiceId)?->updatePaidAmount();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->after(function () {
                    if ($this->originalInvoiceId) {
                        \App\Models\Invoice::find($this->originalInvoiceId)?->updatePaidAmount();
                    }
                }),
        ];
    }
}
