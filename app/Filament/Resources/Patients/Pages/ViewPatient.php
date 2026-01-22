<?php

namespace App\Filament\Resources\Patients\Pages;

use App\Filament\Resources\Patients\PatientResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewPatient extends ViewRecord
{
    protected static string $resource = PatientResource::class;

    public string $activeTab = 'overview';

    public function mount($record): void
    {
        parent::mount($record);
        $this->activeTab = request()->query('tab', 'overview');
    }

    public function getView(): string
    {
        return 'filament.resources.patients.pages.view-patient';
    }

    public function getTitle(): string
    {
        return 'Hồ sơ: ' . $this->record->full_name;
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createTreatmentPlan')
                ->label('Tạo kế hoạch điều trị')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('success')
                ->url(fn() => route('filament.admin.resources.treatment-plans.create', [
                    'patient_id' => $this->record->id,
                ]))
                ->openUrlInNewTab(),

            Action::make('createInvoice')
                ->label('Tạo hóa đơn')
                ->icon('heroicon-o-document-text')
                ->color('warning')
                ->url(fn() => route('filament.admin.resources.invoices.create', [
                    'patient_id' => $this->record->id,
                ]))
                ->openUrlInNewTab(),

            Action::make('createAppointment')
                ->label('Đặt lịch hẹn')
                ->icon('heroicon-o-calendar')
                ->color('info')
                ->url(fn() => route('filament.admin.resources.appointments.create', [
                    'patient_id' => $this->record->id,
                ]))
                ->openUrlInNewTab(),

            Actions\EditAction::make()
                ->label('Chỉnh sửa')
                ->icon('heroicon-o-pencil'),

            Actions\DeleteAction::make()
                ->label('Xóa'),
        ];
    }


}
