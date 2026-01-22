<?php

namespace App\Filament\Resources\Billings;

use App\Filament\Resources\Billings\Pages\CreateBilling;
use App\Filament\Resources\Billings\Pages\EditBilling;
use App\Filament\Resources\Billings\Pages\ListBillings;
use App\Filament\Resources\Billings\Schemas\BillingForm;
use App\Filament\Resources\Billings\Tables\BillingsTable;
use App\Models\Billing;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class BillingResource extends Resource
{
    protected static ?string $model = Billing::class;

    public static function getNavigationLabel(): string
    {
        return 'Đối soát kế hoạch';
    }

    public static function getNavigationGroup(): ?string
    {
        return '2️⃣ Tài chính';
    }
    
    protected static ?int $navigationSort = 14;

    public static function form(Schema $schema): Schema
    {
        return BillingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BillingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBillings::route('/'),
            'create' => CreateBilling::route('/create'),
            'edit' => EditBilling::route('/{record}/edit'),
        ];
    }
}
