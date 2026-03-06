<?php

namespace App\Filament\Resources\MasterPatientDuplicates\Pages;

use App\Filament\Resources\MasterPatientDuplicates\MasterPatientDuplicateResource;
use Filament\Resources\Pages\ListRecords;

class ListMasterPatientDuplicates extends ListRecords
{
    protected static string $resource = MasterPatientDuplicateResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
