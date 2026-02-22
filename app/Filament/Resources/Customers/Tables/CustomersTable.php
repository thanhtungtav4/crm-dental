<?php

namespace App\Filament\Resources\Customers\Tables;


use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Customer;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Họ tên')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Điện thoại')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->toggleable(),
                TextColumn::make('customerGroup.name')
                    ->label('Nhóm KH')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('promotionGroup.name')
                    ->label('Nhóm KM')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('branch.name')
                    ->label('Chi nhánh')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->icon(fn(?string $state) => \App\Support\StatusBadge::icon($state))
                    ->color(fn(?string $state) => \App\Support\StatusBadge::color($state)),
                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('viewAppointments')
                    ->label('Xem lịch hẹn')
                    ->icon('heroicon-o-eye')
                    ->visible(function ($record) {
                        $patientId = optional($record->patient)->id;
                        if (!$patientId)
                            return false;
                        return Appointment::query()
                            ->where('patient_id', $patientId)
                            ->whereIn('status', Appointment::statusesForQuery(Appointment::activeStatuses()))
                            ->where('date', '>=', now())
                            ->exists();
                    })
                    ->modalHeading('Lịch hẹn còn hiệu lực')
                    ->form([
                        \Filament\Forms\Components\Select::make('appointment_id')
                            ->label('Chọn lịch hẹn')
                            ->options(function ($record) {
                                $patientId = optional($record->patient)->id;
                                if (!$patientId)
                                    return [];
                                return Appointment::query()
                                    ->where('patient_id', $patientId)
                                    ->whereIn('status', Appointment::statusesForQuery(Appointment::activeStatuses()))
                                    ->where('date', '>=', now())
                                    ->orderBy('date')
                                    ->get()
                                    ->mapWithKeys(function ($a) {
                                        $label = sprintf(
                                            '%s — %s — %s',
                                            \Carbon\Carbon::parse($a->date)->format('d/m/Y H:i'),
                                            optional($a->doctor)->name ?? 'Chưa chọn bác sĩ',
                                            Appointment::statusLabel($a->status)
                                        );
                                        return [$a->id => $label];
                                    });
                            })
                            ->required(),
                    ])
                    ->modalSubmitActionLabel('Mở')
                    ->action(function (array $data) {
                        return redirect(\App\Filament\Resources\Appointments\AppointmentResource::getUrl('edit', [
                            'record' => $data['appointment_id'],
                        ]));
                    }),
                Action::make('convertToPatient')
                    ->label('Xác nhận thành bệnh nhân')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn($record) => blank($record->patient))
                    ->action(function ($record) {
                        try {
                            /** @var \App\Services\PatientConversionService $service */
                            $service = app(\App\Services\PatientConversionService::class);
                            $service->convert($record);

                            // Notification is handled inside Service, but we can add extra if needed
                            // For table action, simple success is fine. Service sends to Database, 
                            // maybe we want a toast here.
            
                            Notification::make()
                                ->title('Đã chuyển thành bệnh nhân')
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            // Error notification handled in Service
                        }
                    }),
                Action::make('createAppointment')
                    ->label('Tạo lịch hẹn')
                    ->icon('heroicon-o-calendar')
                    ->modalHeading('Tạo lịch hẹn')
                    ->form([
                        \Filament\Forms\Components\Select::make('doctor_id')
                            ->label('Bác sĩ')
                            ->options(fn() => \App\Models\User::role('Doctor')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),
                        \Filament\Forms\Components\Select::make('branch_id')
                            ->label('Chi nhánh')
                            ->options(fn() => \App\Models\Branch::query()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->default(fn($record) => $record?->branch_id)
                            ->required(),
                        \Filament\Forms\Components\DateTimePicker::make('date')
                            ->label('Thời gian')
                            ->default(fn() => now()->addDay())
                            ->required(),
                        \Filament\Forms\Components\Select::make('appointment_kind')
                            ->label('Loại lịch hẹn')
                            ->options([
                                'booking' => 'Đặt hẹn',
                                're_exam' => 'Tái khám',
                            ])
                            ->default('booking')
                            ->required(),
                        \Filament\Forms\Components\Select::make('status')
                            ->label('Trạng thái')
                            ->options(Appointment::statusOptions())
                            ->default(Appointment::STATUS_SCHEDULED),
                        \Filament\Forms\Components\Textarea::make('cancellation_reason')
                            ->label('Lý do hủy')
                            ->visible(fn (callable $get) => $get('status') === Appointment::STATUS_CANCELLED)
                            ->rows(2),
                        \Filament\Forms\Components\Textarea::make('note')
                            ->label('Ghi chú')
                            ->rows(3),
                    ])
                    ->action(function (array $data, $record) {
                        // Ensure there's a patient for this customer
                        $patient = $record->patient;
                        if (!$patient) {
                            $patient = \App\Models\Patient::create([
                                'customer_id' => $record->id,
                                'full_name' => $record->full_name,
                                'email' => $record->email,
                                'phone' => $record->phone,
                                'first_branch_id' => $record->branch_id,
                                'customer_group_id' => $record->customer_group_id,
                                'promotion_group_id' => $record->promotion_group_id,
                                'owner_staff_id' => $record->assigned_to,
                            ]);
                            $record->update(['status' => 'converted']);
                        }

                        Appointment::create([
                            'patient_id' => $patient->id,
                            'doctor_id' => $data['doctor_id'] ?? null,
                            'branch_id' => $data['branch_id'] ?? $record->branch_id,
                            'date' => $data['date'],
                            'appointment_kind' => $data['appointment_kind'] ?? 'booking',
                            'status' => $data['status'] ?? Appointment::STATUS_SCHEDULED,
                            'cancellation_reason' => $data['cancellation_reason'] ?? null,
                            'note' => $data['note'] ?? null,
                        ]);
                        Notification::make()
                            ->title('Đã tạo lịch hẹn')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
