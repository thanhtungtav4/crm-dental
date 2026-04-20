<?php

namespace App\Filament\Resources\PopupAnnouncements\Pages;

use App\Filament\Resources\PopupAnnouncements\PopupAnnouncementResource;
use App\Models\PopupAnnouncement;
use App\Services\PopupAnnouncementDispatchService;
use App\Services\PopupAnnouncementWorkflowService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPopupAnnouncement extends EditRecord
{
    protected static string $resource = PopupAnnouncementResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(PopupAnnouncementWorkflowService::class)->prepareEditablePayload($this->getRecord(), $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            Action::make('publishNow')
                ->label('Phát ngay')
                ->icon('heroicon-o-bell-alert')
                ->color('success')
                ->visible(fn (): bool => in_array($this->record->status, [
                    PopupAnnouncement::STATUS_DRAFT,
                    PopupAnnouncement::STATUS_SCHEDULED,
                    PopupAnnouncement::STATUS_FAILED_NO_RECIPIENT,
                ], true))
                ->form([
                    Textarea::make('reason')
                        ->label('Ghi chú vận hành')
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    $announcement = app(PopupAnnouncementWorkflowService::class)->publish(
                        announcement: $this->getRecord(),
                        reason: $data['reason'] ?? null,
                    );

                    $created = app(PopupAnnouncementDispatchService::class)->dispatchAnnouncement($announcement);

                    Notification::make()
                        ->title('Đã phát popup')
                        ->body("Đã tạo {$created} lượt gửi.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'starts_at', 'published_at']);
                }),
            Action::make('cancel')
                ->label('Hủy')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => in_array($this->record->status, [
                    PopupAnnouncement::STATUS_DRAFT,
                    PopupAnnouncement::STATUS_SCHEDULED,
                    PopupAnnouncement::STATUS_PUBLISHED,
                    PopupAnnouncement::STATUS_FAILED_NO_RECIPIENT,
                ], true))
                ->form([
                    Textarea::make('reason')
                        ->label('Lý do hủy')
                        ->rows(3)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    app(PopupAnnouncementWorkflowService::class)->cancel(
                        announcement: $this->getRecord(),
                        reason: $data['reason'] ?? null,
                    );

                    $this->refreshFormData(['status']);
                }),
        ];
    }

    protected function afterSave(): void
    {
        $record = $this->record;

        if (! $record instanceof PopupAnnouncement) {
            return;
        }

        if ($record->status !== PopupAnnouncement::STATUS_PUBLISHED || ($record->starts_at !== null && $record->starts_at->isFuture())) {
            return;
        }

        $created = app(PopupAnnouncementDispatchService::class)->dispatchAnnouncement($record);

        if ($created <= 0) {
            return;
        }

        Notification::make()
            ->title('Đã bổ sung người nhận popup')
            ->body("Thêm {$created} lượt gửi mới.")
            ->success()
            ->send();
    }
}
