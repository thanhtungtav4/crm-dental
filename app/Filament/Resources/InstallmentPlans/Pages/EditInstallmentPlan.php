<?php

namespace App\Filament\Resources\InstallmentPlans\Pages;

use App\Filament\Resources\InstallmentPlans\InstallmentPlanResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditInstallmentPlan extends EditRecord
{
    protected static string $resource = InstallmentPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
