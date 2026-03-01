<?php

namespace App\Filament\Resources\Patients\Tables;

use App\Filament\Resources\Patients\PatientResource;
use App\Models\Appointment;
use App\Services\DoctorBranchAssignmentService;
use App\Services\PatientBranchTransferService;
use App\Support\ActionPermission;
use App\Support\ClinicRuntimeSettings;
use App\Support\GenderBadge;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                    ->searchable()
                    ->weight('bold')
                    ->color('primary')
                    ->url(fn ($record): string => PatientResource::getUrl('view', ['record' => $record, 'tab' => 'basic-info'])),
                TextColumn::make('gender')
                    ->label('Giới tính')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ClinicRuntimeSettings::genderLabel($state))
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
                TextColumn::make('appointments_count')
                    ->label('Lịch hẹn')
                    ->counts('appointments')
                    ->badge()
                    ->color('info')
                    ->url(fn ($record): string => PatientResource::getUrl('view', ['record' => $record, 'tab' => 'appointments'])),
                TextColumn::make('treatment_plans_count')
                    ->label('KHĐT')
                    ->counts('treatmentPlans')
                    ->badge()
                    ->color('success')
                    ->url(fn ($record): string => PatientResource::getUrl('view', ['record' => $record, 'tab' => 'exam-treatment'])),
                TextColumn::make('invoices_count')
                    ->label('Hóa đơn')
                    ->counts('invoices')
                    ->badge()
                    ->color('warning')
                    ->url(fn ($record): string => PatientResource::getUrl('view', ['record' => $record, 'tab' => 'payments'])),
                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->defaultSort('created_at', direction: 'desc')
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
                            ->options(function (callable $get): array {
                                $branchId = $get('branch_id');
                                $date = $get('date');

                                $appointmentAt = filled($date) ? \Carbon\Carbon::parse((string) $date) : null;

                                return app(DoctorBranchAssignmentService::class)
                                    ->doctorOptionsForBranch(
                                        branchId: filled($branchId) ? (int) $branchId : null,
                                        at: $appointmentAt,
                                    );
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Chỉ hiển thị bác sĩ đang được phân công ở chi nhánh đã chọn.'),
                        \Filament\Forms\Components\Select::make('branch_id')
                            ->label('Chi nhánh')
                            ->options(fn () => \App\Models\Branch::query()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->default(fn ($record) => $record?->first_branch_id)
                            ->live()
                            ->afterStateUpdated(function (callable $get, callable $set, $state): void {
                                $doctorId = $get('doctor_id');

                                if (! filled($doctorId) || ! filled($state)) {
                                    return;
                                }

                                $date = $get('date');
                                $appointmentAt = filled($date) ? \Carbon\Carbon::parse((string) $date) : null;

                                $isAllowed = app(DoctorBranchAssignmentService::class)->isDoctorAssignedToBranch(
                                    doctorId: (int) $doctorId,
                                    branchId: (int) $state,
                                    at: $appointmentAt,
                                );

                                if (! $isAllowed) {
                                    $set('doctor_id', null);
                                }
                            })
                            ->required(),
                        \Filament\Forms\Components\DateTimePicker::make('date')
                            ->label('Thời gian')
                            ->default(fn () => now()->addDay())
                            ->live()
                            ->afterStateUpdated(function (callable $get, callable $set, $state): void {
                                $doctorId = $get('doctor_id');
                                $branchId = $get('branch_id');

                                if (! filled($doctorId) || ! filled($branchId) || ! filled($state)) {
                                    return;
                                }

                                $isAllowed = app(DoctorBranchAssignmentService::class)->isDoctorAssignedToBranch(
                                    doctorId: (int) $doctorId,
                                    branchId: (int) $branchId,
                                    at: \Carbon\Carbon::parse((string) $state),
                                );

                                if (! $isAllowed) {
                                    $set('doctor_id', null);
                                }
                            })
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
                Action::make('transferBranch')
                    ->label('Chuyển chi nhánh')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('warning')
                    ->visible(fn () => auth()->user()?->can(ActionPermission::PATIENT_BRANCH_TRANSFER) ?? false)
                    ->modalHeading('Chuyển bệnh nhân sang chi nhánh khác')
                    ->form([
                        \Filament\Forms\Components\Select::make('to_branch_id')
                            ->label('Chi nhánh nhận')
                            ->options(function ($record): array {
                                return \App\Models\Branch::query()
                                    ->where('active', true)
                                    ->when(
                                        filled($record->first_branch_id),
                                        fn ($query) => $query->where('id', '!=', (int) $record->first_branch_id)
                                    )
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->searchable()
                            ->preload()
                            ->required(),
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->label('Lý do chuyển')
                            ->rows(2)
                            ->required(),
                        \Filament\Forms\Components\Textarea::make('note')
                            ->label('Ghi chú nội bộ')
                            ->rows(2),
                    ])
                    ->action(function (array $data, $record): void {
                        app(PatientBranchTransferService::class)->transferDirect(
                            patient: $record,
                            toBranchId: (int) $data['to_branch_id'],
                            actorId: auth()->id(),
                            reason: (string) ($data['reason'] ?? ''),
                            note: $data['note'] ?? null,
                        );

                        Notification::make()
                            ->title('Đã chuyển chi nhánh thành công')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
