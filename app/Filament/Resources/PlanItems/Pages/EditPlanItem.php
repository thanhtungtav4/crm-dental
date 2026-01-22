<?php

namespace App\Filament\Resources\PlanItems\Pages;

use App\Filament\Resources\PlanItems\PlanItemResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditPlanItem extends EditRecord
{
    protected static string $resource = PlanItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
