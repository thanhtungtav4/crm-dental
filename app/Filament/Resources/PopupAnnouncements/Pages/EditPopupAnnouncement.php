<?php

namespace App\Filament\Resources\PopupAnnouncements\Pages;

use App\Filament\Resources\PopupAnnouncements\PopupAnnouncementResource;
use App\Models\PopupAnnouncement;
use App\Services\PopupAnnouncementDispatchService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPopupAnnouncement extends EditRecord
{
    protected static string $resource = PopupAnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
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
