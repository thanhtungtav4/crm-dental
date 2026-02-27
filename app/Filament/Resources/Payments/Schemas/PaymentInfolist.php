<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Support\ClinicRuntimeSettings;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('invoice.id')
                    ->label('Hóa đơn'),
                TextEntry::make('amount')
                    ->money('VND'),
                TextEntry::make('direction_label')
                    ->label('Loại phiếu')
                    ->getStateUsing(fn ($record): string => $record->getDirectionLabel())
                    ->badge(),
                TextEntry::make('is_deposit')
                    ->label('Phiếu cọc')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Có' : 'Không'),
                TextEntry::make('method')
                    ->label('Phương thức')
                    ->badge(),
                TextEntry::make('transaction_ref')
                    ->label('Mã giao dịch')
                    ->placeholder('-'),
                TextEntry::make('payment_source')
                    ->label('Nguồn thanh toán')
                    ->formatStateUsing(fn (?string $state): string => ClinicRuntimeSettings::paymentSourceLabel($state))
                    ->badge(),
                TextEntry::make('insurance_claim_number')
                    ->label('Mã hồ sơ BH')
                    ->placeholder('-'),
                TextEntry::make('paid_at')
                    ->label('Ngày lập phiếu')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('receiver.name')
                    ->label('Tên người nhận')
                    ->placeholder('-'),
                TextEntry::make('note')
                    ->label('Nội dung')
                    ->placeholder('-'),
                TextEntry::make('refund_reason')
                    ->label('Lý do hoàn')
                    ->placeholder('-'),
                TextEntry::make('reversal_of_id')
                    ->label('Phiếu gốc')
                    ->placeholder('-'),
                TextEntry::make('reversed_at')
                    ->label('Đảo phiếu lúc')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('reversedBy.name')
                    ->label('Đảo phiếu bởi')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
