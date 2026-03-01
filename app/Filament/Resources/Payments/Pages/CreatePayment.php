<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\BranchAccess;
use App\Support\ClinicRuntimeSettings;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

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
        BranchAccess::assertCanAccessBranch(
            branchId: $invoice->resolveBranchId(),
            field: 'invoice_id',
            message: 'Bạn không thể ghi nhận thanh toán cho hóa đơn thuộc chi nhánh ngoài phạm vi được phân quyền.',
        );

        $transactionRef = filled($data['transaction_ref'] ?? null)
            ? trim((string) $data['transaction_ref'])
            : null;

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
            return $invoice->recordPayment(
                amount: (float) ($data['amount'] ?? 0),
                method: (string) ($data['method'] ?? 'cash'),
                notes: $data['note'] ?? null,
                paidAt: $data['paid_at'] ?? now(),
                direction: (string) ($data['direction'] ?? ClinicRuntimeSettings::defaultPaymentDirection()),
                refundReason: $data['refund_reason'] ?? null,
                transactionRef: $transactionRef,
                paymentSource: (string) ($data['payment_source'] ?? ClinicRuntimeSettings::defaultPaymentSource()),
                insuranceClaimNumber: $data['insurance_claim_number'] ?? null,
                receivedBy: $data['received_by'] ?? auth()->id(),
                reversalOfId: null,
                isDeposit: (bool) ($data['is_deposit'] ?? false),
            );
        } catch (ValidationException $exception) {
            throw $exception;
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
