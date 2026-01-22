<?php

namespace App\Filament\Resources\InstallmentPlans;

use App\Filament\Resources\InstallmentPlans\Pages\CreateInstallmentPlan;
use App\Filament\Resources\InstallmentPlans\Pages\EditInstallmentPlan;
use App\Filament\Resources\InstallmentPlans\Pages\ListInstallmentPlans;
use App\Filament\Resources\InstallmentPlans\Pages\ViewInstallmentPlan;
use App\Filament\Resources\InstallmentPlans\Schemas\InstallmentPlanForm;
use App\Filament\Resources\InstallmentPlans\Schemas\InstallmentPlanInfolist;
use App\Filament\Resources\InstallmentPlans\Tables\InstallmentPlansTable;
use App\Models\InstallmentPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InstallmentPlanResource extends Resource
{
    protected static ?string $model = InstallmentPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    public static function getNavigationLabel(): string
    {
        return 'Trả góp';
    }

    public static function getNavigationGroup(): ?string
    {
        return '2️⃣ Tài chính';
    }
    
    protected static ?int $navigationSort = 13;

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getModelLabel(): string
    {
        return 'Kế hoạch trả góp';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Kế hoạch trả góp';
    }

    public static function form(Schema $schema): Schema
    {
        return InstallmentPlanForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InstallmentPlanInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InstallmentPlansTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInstallmentPlans::route('/'),
            'create' => CreateInstallmentPlan::route('/create'),
            'view' => ViewInstallmentPlan::route('/{record}'),
            'edit' => EditInstallmentPlan::route('/{record}/edit'),
        ];
    }
}
