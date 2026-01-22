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
                    ->label('Invoice'),
                TextEntry::make('amount')
                    ->numeric(),
                TextEntry::make('method')
                    ->badge(),
                TextEntry::make('transaction_ref')
                    ->placeholder('-'),
                TextEntry::make('payment_source')
                    ->badge(),
                TextEntry::make('insurance_claim_number')
                    ->placeholder('-'),
                TextEntry::make('paid_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('received_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('note')
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
