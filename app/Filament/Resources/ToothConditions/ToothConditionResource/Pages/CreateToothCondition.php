<?php

namespace App\Filament\Resources\ToothConditions\ToothConditionResource\Pages;

use App\Filament\Resources\ToothConditions\ToothConditionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateToothCondition extends CreateRecord
{
    protected static string $resource = ToothConditionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

