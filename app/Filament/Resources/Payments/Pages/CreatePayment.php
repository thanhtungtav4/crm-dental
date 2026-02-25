<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

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
        $transactionRef = filled($data['transaction_ref'] ?? null)
            ? trim((string) $data['transaction_ref'])
            : null;

        $data['transaction_ref'] = $transactionRef;
        $data['received_by'] = $data['received_by'] ?? auth()->id();

        if ($transactionRef) {
            $existingPayment = Payment::query()
                ->where('invoice_id', (int) $data['invoice_id'])
                ->where('transaction_ref', $transactionRef)
                ->first();

            if ($existingPayment) {
                Notification::make()
                    ->warning()
                    ->title('Mã giao dịch đã tồn tại')
                    ->body('Đã dùng bản ghi thanh toán hiện có để tránh ghi trùng.')
                    ->send();

                return $existingPayment;
            }
        }

        try {
            return parent::handleRecordCreation($data);
        } catch (QueryException $exception) {
            $isDuplicateTransaction = str_contains((string) $exception->getCode(), '23000');

            if (! $isDuplicateTransaction || ! $transactionRef) {
                throw $exception;
            }

            $existingPayment = Payment::query()
                ->where('invoice_id', (int) $data['invoice_id'])
                ->where('transaction_ref', $transactionRef)
                ->first();

            if ($existingPayment) {
                Notification::make()
                    ->warning()
                    ->title('Mã giao dịch đã tồn tại')
                    ->body('Đã dùng bản ghi thanh toán hiện có để tránh ghi trùng.')
                    ->send();

                return $existingPayment;
            }

            throw $exception;
        }
    }

    protected function afterCreate(): void
    {
        $this->record->invoice?->updatePaidAmount();
    }
}
