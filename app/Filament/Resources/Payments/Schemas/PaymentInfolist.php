<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Patients\PatientResource;
use App\Support\ClinicRuntimeSettings;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Liên kết hồ sơ')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('id')
                            ->label('Mã phiếu')
                            ->formatStateUsing(fn ($state): string => '#'.$state)
                            ->badge()
                            ->copyable(),
                        TextEntry::make('invoice.invoice_no')
                            ->label('Hóa đơn')
                            ->placeholder('-')
                            ->url(fn ($record): ?string => $record->invoice
                                ? InvoiceResource::getUrl('edit', ['record' => $record->invoice])
                                : null)
                            ->openUrlInNewTab(),
                        TextEntry::make('invoice.patient.patient_code')
                            ->label('Mã BN')
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('invoice.patient.full_name')
                            ->label('Bệnh nhân')
                            ->placeholder('-')
                            ->url(fn ($record): ?string => $record->invoice?->patient
                                ? PatientResource::getUrl('view', ['record' => $record->invoice->patient, 'tab' => 'payments'])
                                : null)
                            ->openUrlInNewTab()
                            ->columnSpan(2),
                        TextEntry::make('branch.name')
                            ->label('Chi nhánh')
                            ->placeholder('-'),
                    ]),

                Section::make('Chi tiết thanh toán')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('amount')
                            ->label('Số tiền')
                            ->money('VND')
                            ->weight('bold')
                            ->color(fn ($record): string => $record->isRefund() ? 'danger' : 'success'),
                        TextEntry::make('direction')
                            ->label('Loại phiếu')
                            ->formatStateUsing(fn ($record): string => $record->getDirectionLabel())
                            ->badge()
                            ->color(fn ($record): string => $record->isRefund() ? 'danger' : 'success'),
                        TextEntry::make('is_deposit')
                            ->label('Phiếu cọc')
                            ->badge()
                            ->formatStateUsing(fn ($state): string => $state ? 'Có' : 'Không')
                            ->color(fn ($state): string => $state ? 'warning' : 'gray'),
                        TextEntry::make('method')
                            ->label('Phương thức')
                            ->formatStateUsing(fn ($record): string => $record->getMethodLabel())
                            ->badge()
                            ->icon(fn ($record): string => $record->getMethodIcon())
                            ->color(fn ($record): string => $record->getMethodBadgeColor()),
                        TextEntry::make('payment_source')
                            ->label('Nguồn thanh toán')
                            ->formatStateUsing(fn (?string $state): string => ClinicRuntimeSettings::paymentSourceLabel($state))
                            ->badge()
                            ->color(fn ($record): string => $record->getSourceBadgeColor()),
                        TextEntry::make('paid_at')
                            ->label('Thời gian thanh toán')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('receiver.name')
                            ->label('Người nhận')
                            ->placeholder('-'),
                        TextEntry::make('transaction_ref')
                            ->label('Mã giao dịch')
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('insurance_claim_number')
                            ->label('Mã hồ sơ BH')
                            ->placeholder('-')
                            ->visible(fn ($record): bool => $record->payment_source === 'insurance'),
                    ]),

                Section::make('Nội dung & đối soát')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('note')
                            ->label('Ghi chú')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('refund_reason')
                            ->label('Lý do hoàn')
                            ->placeholder('-')
                            ->visible(fn ($record): bool => $record->isRefund())
                            ->columnSpanFull(),
                        TextEntry::make('reversal_of_id')
                            ->label('Đảo từ phiếu')
                            ->formatStateUsing(fn ($state): string => $state ? '#'.$state : '-')
                            ->placeholder('-'),
                        TextEntry::make('reversed_at')
                            ->label('Đảo phiếu lúc')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('reversedBy.name')
                            ->label('Đảo phiếu bởi')
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->label('Tạo lúc')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->label('Cập nhật lúc')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
