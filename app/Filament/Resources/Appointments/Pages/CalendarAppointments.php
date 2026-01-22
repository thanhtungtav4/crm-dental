<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Appointment;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;

class CalendarAppointments extends Page
{
    protected static string $resource = AppointmentResource::class;

    protected string $view = 'filament.appointments.calendar';

    public function getHeading(): string
    {
        return 'Lịch hẹn (Calendar)';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Tạo lịch hẹn')
                ->icon('heroicon-o-plus')
                ->url(AppointmentResource::getUrl('create')),
        ];
    }
}
