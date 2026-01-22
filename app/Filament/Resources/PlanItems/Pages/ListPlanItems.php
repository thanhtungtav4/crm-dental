<?php

namespace App\Filament\Resources\PlanItems\Pages;

use App\Filament\Resources\PlanItems\PlanItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlanItems extends ListRecords
{
    protected static string $resource = PlanItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
