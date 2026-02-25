<?php

namespace App\Filament\Resources\Patients\Tables;

use App\Models\Appointment;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Support\GenderBadge;

class PatientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('patient_code')
                    ->label('Mã bệnh nhân')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('full_name')
                    ->label('Họ tên')
                    ->searchable(),
                TextColumn::make('gender')
                    ->label('Giới tính')
                    ->badge()
                    ->icon(fn (?string $state) => GenderBadge::icon($state))
                    ->color(fn (?string $state) => GenderBadge::color($state))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Điện thoại')
                    ->searchable(),
                TextColumn::make('phone_secondary')
                    ->label('Điện thoại 2')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('address')
                    ->label('Địa chỉ')
                    ->limit(40)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('medical_history')
                    ->label('Tiền sử bệnh')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('note')
                    ->label('Ghi chú')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('branch.name')
                    ->label('Chi nhánh')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
                EditAction::make(),
                Action::make('viewAppointments')
                    ->label('Xem lịch hẹn')
                    ->icon('heroicon-o-eye')
                    ->visible(fn ($record) => Appointment::query()
                        ->where('patient_id', $record->id)
                        ->whereIn('status', Appointment::statusesForQuery(Appointment::activeStatuses()))
                        ->where('date', '>=', now())
                        ->exists())
                    ->modalHeading('Lịch hẹn còn hiệu lực')
                    ->form([
                        \Filament\Forms\Components\Select::make('appointment_id')
                            ->label('Chọn lịch hẹn')
                            ->options(function ($record) {
                                return Appointment::query()
                                    ->where('patient_id', $record->id)
                                    ->whereIn('status', Appointment::statusesForQuery(Appointment::activeStatuses()))
                                    ->where('date', '>=', now())
                                    ->orderBy('date')
                                    ->get()
                                    ->mapWithKeys(function ($a) {
                                        $label = sprintf('%s — %s — %s',
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
                Action::make('createAppointment')
                    ->label('Tạo lịch hẹn')
                    ->icon('heroicon-o-calendar')
                    ->modalHeading('Tạo lịch hẹn')
                    ->form([
                        \Filament\Forms\Components\Select::make('doctor_id')
                            ->label('Bác sĩ')
                            ->options(fn () => \App\Models\User::role('Doctor')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),
                        \Filament\Forms\Components\Select::make('branch_id')
                            ->label('Chi nhánh')
                            ->options(fn () => \App\Models\Branch::query()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->default(fn ($record) => $record?->first_branch_id)
                            ->required(),
                        \Filament\Forms\Components\DateTimePicker::make('date')
                            ->label('Thời gian')
                            ->default(fn () => now()->addDay())
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
                            ->required(fn (callable $get) => $get('status') === Appointment::STATUS_CANCELLED)
                            ->rows(2),
                        \Filament\Forms\Components\Textarea::make('reschedule_reason')
                            ->label('Lý do hẹn lại')
                            ->visible(fn (callable $get) => $get('status') === Appointment::STATUS_RESCHEDULED)
                            ->required(fn (callable $get) => $get('status') === Appointment::STATUS_RESCHEDULED)
                            ->rows(2),
                        \Filament\Forms\Components\Textarea::make('note')
                            ->label('Ghi chú')
                            ->rows(3),
                    ])
                    ->action(function (array $data, $record) {
                        Appointment::create([
                            'patient_id' => $record->id,
                            'doctor_id' => $data['doctor_id'] ?? null,
                            'branch_id' => $data['branch_id'] ?? $record->first_branch_id,
                            'date' => $data['date'],
                            'appointment_kind' => $data['appointment_kind'] ?? 'booking',
                            'status' => $data['status'] ?? Appointment::STATUS_SCHEDULED,
                            'cancellation_reason' => $data['cancellation_reason'] ?? null,
                            'reschedule_reason' => $data['reschedule_reason'] ?? null,
                            'note' => $data['note'] ?? null,
                        ]);
                        Notification::make()
                            ->title('Đã tạo lịch hẹn')
                            ->success()
                            ->send();
                    }),
            ])
            ;
    }
}
