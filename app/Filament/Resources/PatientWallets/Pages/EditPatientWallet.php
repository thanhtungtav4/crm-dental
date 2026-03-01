<?php

namespace App\Filament\Resources\PatientWallets\Pages;

use App\Filament\Resources\PatientWallets\PatientWalletResource;
use Filament\Resources\Pages\EditRecord;

class EditPatientWallet extends EditRecord
{
    protected static string $resource = PatientWalletResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
