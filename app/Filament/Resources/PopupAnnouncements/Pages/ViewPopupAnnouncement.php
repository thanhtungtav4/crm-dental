<?php

namespace App\Filament\Resources\PopupAnnouncements\Pages;

use App\Filament\Resources\PopupAnnouncements\PopupAnnouncementResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPopupAnnouncement extends ViewRecord
{
    protected static string $resource = PopupAnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
