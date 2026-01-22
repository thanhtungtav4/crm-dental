<?php

namespace App\Filament\Resources\Invoices\RelationManagers;

use App\Filament\Resources\InstallmentPlans\InstallmentPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InstallmentPlanRelationManager extends RelationManager
{
    protected static string $relationship = 'installmentPlan';

    protected static ?string $relatedResource = InstallmentPlanResource::class;
    
    protected static ?string $title = 'Kế hoạch trả góp';
    
    protected static ?string $modelLabel = 'kế hoạch trả góp';

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make()
                    ->label('Tạo kế hoạch trả góp')
                    ->icon(Heroicon::OutlinedPlus)
                    ->visible(fn () => !$this->getOwnerRecord()->hasInstallmentPlan()),
            ])
            ->emptyStateHeading('Chưa có kế hoạch trả góp')
            ->emptyStateDescription('Tạo kế hoạch trả góp để cho phép bệnh nhân thanh toán theo từng kỳ')
            ->emptyStateIcon('heroicon-o-credit-card')
            ->heading(function () {
                $record = $this->getOwnerRecord();
                if (!$record->hasInstallmentPlan()) {
                    return 'Kế hoạch trả góp';
                }
                
                $plan = $record->installmentPlan;
                $paid = number_format($plan->paid_amount, 0, ',', '.');
                $total = number_format($plan->total_amount, 0, ',', '.');
                $remaining = number_format($plan->remaining_amount, 0, ',', '.');
                $progress = round($plan->getCompletionPercentage(), 1);
                
                return "Kế hoạch trả góp • {$plan->number_of_installments} kỳ • Đã thu: {$paid}đ / {$total}đ ({$progress}%) • Còn lại: {$remaining}đ";
            });
    }
}
