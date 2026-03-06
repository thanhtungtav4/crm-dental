<?php

namespace App\Filament\Resources\FactoryOrders\Pages;

use App\Filament\Resources\FactoryOrders\FactoryOrderResource;
use App\Models\FactoryOrder;
use App\Services\FactoryOrderAuthorizer;
use App\Services\FactoryOrderWorkflowService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateFactoryOrder extends CreateRecord
{
    protected static string $resource = FactoryOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $patientId = request()->integer('patient_id');
        if ($patientId && blank($data['patient_id'] ?? null)) {
            $data['patient_id'] = $patientId;
        }

        return app(FactoryOrderWorkflowService::class)->prepareCreatePayload(
            app(FactoryOrderAuthorizer::class)->sanitizeFactoryOrderData(auth()->user(), $data),
        );
    }

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(
            fn (): Model => FactoryOrder::query()->create($data),
            attempts: 5,
        );
    }
}
