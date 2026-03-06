<?php

namespace App\Filament\Resources\FactoryOrders\Pages;

use App\Filament\Resources\FactoryOrders\FactoryOrderResource;
use App\Services\FactoryOrderAuthorizer;
use Filament\Resources\Pages\CreateRecord;

class CreateFactoryOrder extends CreateRecord
{
    protected static string $resource = FactoryOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $patientId = request()->integer('patient_id');
        if ($patientId && blank($data['patient_id'] ?? null)) {
            $data['patient_id'] = $patientId;
        }

        return app(FactoryOrderAuthorizer::class)->sanitizeFactoryOrderData(auth()->user(), $data);
    }
}
