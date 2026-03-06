<?php

namespace App\Filament\Resources\ZnsCampaigns\Pages;

use App\Filament\Resources\ZnsCampaigns\ZnsCampaignResource;
use App\Models\ZnsCampaign;
use App\Services\ZnsCampaignRunnerService;
use App\Services\ZnsCampaignWorkflowService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\EditRecord;

class EditZnsCampaign extends EditRecord
{
    protected static string $resource = ZnsCampaignResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(ZnsCampaignWorkflowService::class)->prepareEditablePayload($this->getRecord(), $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('schedule')
                ->label('Lên lịch')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->visible(fn (): bool => in_array($this->record->status, [
                    ZnsCampaign::STATUS_DRAFT,
                    ZnsCampaign::STATUS_FAILED,
                ], true))
                ->form([
                    DateTimePicker::make('scheduled_at')
                        ->label('Thời điểm chạy')
                        ->native(false)
                        ->seconds(false)
                        ->required(),
                    Textarea::make('reason')
                        ->label('Ghi chú vận hành')
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    app(ZnsCampaignWorkflowService::class)->schedule(
                        campaign: $this->getRecord(),
                        scheduledAt: $data['scheduled_at'] ?? null,
                        reason: $data['reason'] ?? null,
                    );

                    $this->refreshFormData(['status', 'scheduled_at', 'started_at', 'finished_at']);
                }),
            Action::make('runNow')
                ->label('Chạy campaign')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn (): bool => in_array($this->record->status, [
                    ZnsCampaign::STATUS_DRAFT,
                    ZnsCampaign::STATUS_SCHEDULED,
                    ZnsCampaign::STATUS_FAILED,
                ], true))
                ->form([
                    Textarea::make('reason')
                        ->label('Lý do chạy ngay')
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    app(ZnsCampaignWorkflowService::class)->runNow(
                        campaign: $this->getRecord(),
                        reason: $data['reason'] ?? null,
                    );

                    app(ZnsCampaignRunnerService::class)->runCampaign($this->record);
                    $this->record->refresh();
                    $this->refreshFormData([
                        'status',
                        'scheduled_at',
                        'sent_count',
                        'failed_count',
                        'started_at',
                        'finished_at',
                    ]);
                }),
            Action::make('cancel')
                ->label('Huỷ campaign')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => in_array($this->record->status, [
                    ZnsCampaign::STATUS_DRAFT,
                    ZnsCampaign::STATUS_SCHEDULED,
                    ZnsCampaign::STATUS_RUNNING,
                    ZnsCampaign::STATUS_FAILED,
                ], true))
                ->form([
                    Textarea::make('reason')
                        ->label('Lý do huỷ')
                        ->rows(3)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    app(ZnsCampaignWorkflowService::class)->cancel(
                        campaign: $this->getRecord(),
                        reason: $data['reason'] ?? null,
                    );

                    $this->refreshFormData(['status', 'finished_at']);
                }),
        ];
    }
}
