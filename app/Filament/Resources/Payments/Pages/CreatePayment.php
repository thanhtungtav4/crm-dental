<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Invoice;
use App\Services\PaymentRecordingService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['received_by'])) {
            $data['received_by'] = auth()->id();
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $invoice = Invoice::query()->findOrFail((int) ($data['invoice_id'] ?? 0));
        $payment = app(PaymentRecordingService::class)->record($invoice, $data, auth()->user());

        if (filled($data['transaction_ref'] ?? null) && ! $payment->wasRecentlyCreated) {
            Notification::make()
                ->warning()
                ->title('Mã giao dịch đã tồn tại')
                ->body('Đã dùng bản ghi thanh toán hiện có để tránh ghi trùng.')
                ->send();
        }

        return $payment;
    }

    protected function afterCreate(): void
    {
        $this->record->invoice?->updatePaidAmount();
    }
}
