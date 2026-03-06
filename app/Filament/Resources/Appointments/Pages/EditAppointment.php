<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\AppointmentStatusActions;
use App\Services\AppointmentSchedulingService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAppointment extends EditRecord
{
    protected static string $resource = AppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AppointmentStatusActions::confirm(fn () => $this->getRecord()),
            AppointmentStatusActions::start(fn () => $this->getRecord()),
            AppointmentStatusActions::complete(fn () => $this->getRecord()),
            AppointmentStatusActions::markNoShow(fn () => $this->getRecord()),
            AppointmentStatusActions::cancel(fn () => $this->getRecord()),
            Actions\DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(AppointmentSchedulingService::class)->update($record, $data);
    }
}
