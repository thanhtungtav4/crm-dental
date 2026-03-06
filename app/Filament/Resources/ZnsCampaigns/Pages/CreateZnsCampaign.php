<?php

namespace App\Filament\Resources\ZnsCampaigns\Pages;

use App\Filament\Resources\ZnsCampaigns\ZnsCampaignResource;
use App\Models\ZnsCampaign;
use App\Services\ZnsCampaignWorkflowService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateZnsCampaign extends CreateRecord
{
    protected static string $resource = ZnsCampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return app(ZnsCampaignWorkflowService::class)->prepareCreatePayload($data);
    }

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(
            fn (): Model => ZnsCampaign::query()->create($data),
            attempts: 5,
        );
    }
}
