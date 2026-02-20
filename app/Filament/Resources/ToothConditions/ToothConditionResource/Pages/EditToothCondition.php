<?php

namespace App\Filament\Resources\ToothConditions\ToothConditionResource\Pages;

use App\Filament\Resources\ToothConditions\ToothConditionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditToothCondition extends EditRecord
{
    protected static string $resource = ToothConditionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

