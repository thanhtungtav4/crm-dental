<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAppointments extends ListRecords
{
    protected static string $resource = AppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->color('info'),
            Action::make('calendar')
                ->label('Xem lịch')
                ->icon('heroicon-o-calendar')
                ->color('info')
                ->url(AppointmentResource::getUrl('calendar')),
        ];
    }
}
