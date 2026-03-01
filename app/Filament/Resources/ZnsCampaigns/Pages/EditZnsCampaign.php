<?php

namespace App\Filament\Resources\ZnsCampaigns\Pages;

use App\Filament\Resources\ZnsCampaigns\ZnsCampaignResource;
use App\Models\ZnsCampaign;
use App\Services\ZnsCampaignRunnerService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditZnsCampaign extends EditRecord
{
    protected static string $resource = ZnsCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runNow')
                ->label('Chạy campaign')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn (): bool => in_array($this->record->status, [
                    ZnsCampaign::STATUS_DRAFT,
                    ZnsCampaign::STATUS_SCHEDULED,
                    ZnsCampaign::STATUS_RUNNING,
                ], true))
                ->requiresConfirmation()
                ->action(function (): void {
                    app(ZnsCampaignRunnerService::class)->runCampaign($this->record);
                    $this->record->refresh();
                    $this->refreshFormData([
                        'status',
                        'sent_count',
                        'failed_count',
                        'started_at',
                        'finished_at',
                    ]);
                }),
            DeleteAction::make(),
        ];
    }
}
