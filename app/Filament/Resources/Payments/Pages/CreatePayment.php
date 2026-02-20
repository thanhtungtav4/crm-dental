<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['received_by'])) {
            $data['received_by'] = auth()->id();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->invoice?->updatePaidAmount();
    }
}
