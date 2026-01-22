<?php

namespace App\Filament\Resources\InstallmentPlans\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class InstallmentPlanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('invoice.id')
                    ->label('Invoice'),
                TextEntry::make('total_amount')
                    ->numeric(),
                TextEntry::make('paid_amount')
                    ->numeric(),
                TextEntry::make('remaining_amount')
                    ->numeric(),
                TextEntry::make('number_of_installments')
                    ->numeric(),
                TextEntry::make('installment_amount')
                    ->numeric(),
                TextEntry::make('interest_rate')
                    ->numeric(),
                TextEntry::make('start_date')
                    ->date(),
                TextEntry::make('end_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('payment_frequency')
                    ->badge(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
