<?php

namespace App\Filament\Resources\PopupAnnouncements\Pages;

use App\Filament\Resources\PopupAnnouncements\PopupAnnouncementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPopupAnnouncements extends ListRecords
{
    protected static string $resource = PopupAnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tạo popup')
                ->color('info')
                ->slideOver()
                ->modalWidth('6xl')
                ->modalHeading('Tạo popup nội bộ')
                ->modalDescription('Soạn nội dung, chọn nhóm nhận và thời điểm hiển thị trước khi phát.'),
        ];
    }
}
