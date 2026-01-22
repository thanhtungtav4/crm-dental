<?php

namespace App\Filament\Resources\MaterialBatches\Pages;

use App\Filament\Resources\MaterialBatches\MaterialBatchResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditMaterialBatch extends EditRecord
{
    protected static string $resource = MaterialBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
