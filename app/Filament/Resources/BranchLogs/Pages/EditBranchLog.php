<?php

namespace App\Filament\Resources\BranchLogs\Pages;

use App\Filament\Resources\BranchLogs\BranchLogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBranchLog extends EditRecord
{
    protected static string $resource = BranchLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
