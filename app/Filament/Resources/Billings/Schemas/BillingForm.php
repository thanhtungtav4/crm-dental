<?php

namespace App\Filament\Resources\Billings\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class BillingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('treatment_plan_id')
                    ->relationship('treatmentPlan', 'title')
                    ->label('Kế hoạch')
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->label('Số tiền')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'unpaid' => 'Chưa thu',
                        'paid' => 'Đã thu',
                        'partial' => 'Thu một phần',
                    ])->default('unpaid'),
            ]);
    }
}
