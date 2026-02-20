<?php

namespace App\Filament\Resources\Payments\Schemas;

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
                    ->badge(),
                TextEntry::make('method')
                    ->label('Phương thức')
                    ->badge(),
                TextEntry::make('transaction_ref')
                    ->label('Mã giao dịch')
                    ->placeholder('-'),
                TextEntry::make('payment_source')
                    ->label('Nguồn thanh toán')
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
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
