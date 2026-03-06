<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Services\PatientAssignmentAuthorizer;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return app(PatientAssignmentAuthorizer::class)
            ->sanitizeCustomerFormData(auth()->user(), $data);
    }
}
