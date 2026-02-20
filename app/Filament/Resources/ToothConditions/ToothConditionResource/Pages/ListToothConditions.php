<?php

namespace App\Filament\Resources\ToothConditions\ToothConditionResource\Pages;

use App\Filament\Resources\ToothConditions\ToothConditionResource;
use Filament\Resources\Pages\ListRecords;

class ListToothConditions extends ListRecords
{
    protected static string $resource = ToothConditionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}

