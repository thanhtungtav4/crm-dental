<?php

namespace App\Filament\Resources\PatientWallets\Pages;

use App\Filament\Resources\PatientWallets\PatientWalletResource;
use Filament\Resources\Pages\ListRecords;

class ListPatientWallets extends ListRecords
{
    protected static string $resource = PatientWalletResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
