<?php

namespace App\Filament\Resources\PopupAnnouncements\Pages;

use App\Filament\Resources\PopupAnnouncements\PopupAnnouncementResource;
use App\Models\PopupAnnouncement;
use App\Services\PopupAnnouncementDispatchService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePopupAnnouncement extends CreateRecord
{
    protected static string $resource = PopupAnnouncementResource::class;

    protected function afterCreate(): void
    {
        $record = $this->record;

        if (! $record instanceof PopupAnnouncement) {
            return;
        }

        if ($record->status !== PopupAnnouncement::STATUS_PUBLISHED || ($record->starts_at !== null && $record->starts_at->isFuture())) {
            return;
        }

        $created = app(PopupAnnouncementDispatchService::class)->dispatchAnnouncement($record);

        Notification::make()
            ->title('Đã phát popup')
            ->body("Đã tạo {$created} lượt gửi.")
            ->success()
            ->send();
    }
}
