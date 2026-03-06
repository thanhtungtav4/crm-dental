<?php

namespace App\Filament\Resources\MasterPatientDuplicates\Pages;

use App\Filament\Resources\MasterPatientDuplicates\MasterPatientDuplicateResource;
use Filament\Resources\Pages\ViewRecord;

class ViewMasterPatientDuplicate extends ViewRecord
{
    protected static string $resource = MasterPatientDuplicateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MasterPatientDuplicateResource::mergeAction(),
            MasterPatientDuplicateResource::ignoreAction(),
            MasterPatientDuplicateResource::rollbackAction(),
        ];
    }
}
