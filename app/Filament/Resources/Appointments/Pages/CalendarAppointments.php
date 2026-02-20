<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Support\ClinicRuntimeSettings;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;

class CalendarAppointments extends Page
{
    protected static string $resource = AppointmentResource::class;

    protected string $view = 'filament.appointments.calendar';

    protected static ?string $navigationLabel = 'Lịch hẹn tổng';

    protected static string|\UnitEnum|null $navigationGroup = 'Hoạt động hàng ngày';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'calendar';

    public function getHeading(): string
    {
        return 'Lịch hẹn tổng';
    }

    public function getTitle(): string
    {
        return $this->getHeading();
    }

    public function isGoogleCalendarEnabled(): bool
    {
        return ClinicRuntimeSettings::isGoogleCalendarEnabled();
    }

    public function getGoogleCalendarSyncModeLabel(): string
    {
        return match (ClinicRuntimeSettings::googleCalendarSyncMode()) {
            'one_way_to_google' => 'Một chiều: CRM -> Google',
            'one_way_to_crm' => 'Một chiều: Google -> CRM',
            default => 'Hai chiều',
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Thêm lịch hẹn')
                ->icon('heroicon-o-plus')
                ->url(AppointmentResource::getUrl('create')),
        ];
    }
}
