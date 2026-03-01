<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\ReceiptsExpense\ReceiptsExpenseResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open_patient_profile')
                ->label('Hồ sơ BN')
                ->icon('heroicon-o-user')
                ->color('gray')
                ->url(fn (): ?string => $this->record->invoice?->patient
                    ? PatientResource::getUrl('view', [
                        'record' => $this->record->invoice->patient,
                        'tab' => 'payments',
                    ])
                    : null)
                ->openUrlInNewTab()
                ->visible(fn (): bool => $this->record->invoice?->patient !== null),
            Action::make('open_invoice')
                ->label('Mở hóa đơn')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->url(fn (): ?string => $this->record->invoice
                    ? InvoiceResource::getUrl('edit', ['record' => $this->record->invoice])
                    : null)
                ->openUrlInNewTab()
                ->visible(fn (): bool => $this->record->invoice !== null),
            Action::make('create_receipt_expense_voucher')
                ->label('Phiếu thu/chi')
                ->icon('heroicon-o-document-plus')
                ->color('gray')
                ->url(function (): string {
                    $isRefund = $this->record->direction === 'refund';
                    $voucherType = $isRefund ? 'expense' : 'receipt';
                    $invoiceNo = $this->record->invoice?->invoice_no ?? '-';
                    $voucherContent = $isRefund
                        ? 'Hoàn tiền theo phiếu #'.$this->record->id.' / hóa đơn '.$invoiceNo
                        : 'Thu tiền theo phiếu #'.$this->record->id.' / hóa đơn '.$invoiceNo;

                    return ReceiptsExpenseResource::getUrl('create', [
                        'patient_id' => $this->record->invoice?->patient_id,
                        'invoice_id' => $this->record->invoice_id,
                        'clinic_id' => $this->record->invoice?->resolveBranchId(),
                        'voucher_type' => $voucherType,
                        'amount' => abs((float) $this->record->amount),
                        'payment_method' => $this->record->method,
                        'payer_or_receiver' => $this->record->invoice?->patient?->full_name,
                        'content' => $voucherContent,
                    ]);
                })
                ->openUrlInNewTab(),
            Action::make('print')
                ->label('In phiếu')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('payments.print', $this->record))
                ->openUrlInNewTab(),
        ];
    }
}
