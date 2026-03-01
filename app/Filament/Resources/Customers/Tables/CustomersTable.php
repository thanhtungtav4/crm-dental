<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Patients\PatientResource;
use App\Models\Appointment;
use App\Services\DoctorBranchAssignmentService;
use App\Support\BranchAccess;
use App\Support\ClinicRuntimeSettings;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
                    ->searchable()
                    ->weight('bold')
                    ->color(fn ($record) => $record->patient ? 'primary' : null)
                    ->url(fn ($record): string => $record->patient
                        ? PatientResource::getUrl('view', ['record' => $record->patient, 'tab' => 'basic-info'])
                        : CustomerResource::getUrl('edit', ['record' => $record])),
                TextColumn::make('patient.patient_code')
                    ->label('Mã BN')
                    ->badge()
                    ->placeholder('Lead')
                    ->color(fn ($record): string => $record->patient ? 'success' : 'gray')
                    ->url(fn ($record): ?string => $record->patient
                        ? PatientResource::getUrl('view', ['record' => $record->patient, 'tab' => 'basic-info'])
                        : null),
                TextColumn::make('phone')
                    ->label('Điện thoại')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->toggleable(),
                TextColumn::make('source_detail')
                    ->label('Nguồn chi tiết')
                    ->badge()
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
                    ->formatStateUsing(fn (?string $state): string => ClinicRuntimeSettings::customerStatusLabel($state))
                    ->icon(fn (?string $state) => \App\Support\StatusBadge::icon($state))
                    ->color(fn (?string $state) => \App\Support\StatusBadge::color($state)),
                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('source')
                    ->label('Nguồn')
                    ->options(fn (): array => ClinicRuntimeSettings::customerSourceOptions()),
                SelectFilter::make('source_detail')
                    ->label('Nguồn chi tiết')
                    ->options([
                        'website' => 'Website',
                    ]),
                TrashedFilter::make(),
            ])
            ->defaultSort('created_at', direction: 'desc')
            ->recordActions([
                EditAction::make(),
                Action::make('viewAppointments')
                    ->label('Xem lịch hẹn')
                    ->icon('heroicon-o-eye')
                    ->visible(function ($record) {
                        $patientId = optional($record->patient)->id;
                        if (! $patientId) {
                            return false;
                        }

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
                                if (! $patientId) {
                                    return [];
                                }

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
                    ->visible(fn ($record) => blank($record->patient))
                    ->action(function ($record) {
                        try {
                            /** @var \App\Services\PatientConversionService $service */
                            $service = app(\App\Services\PatientConversionService::class);
                            $patient = $service->convert($record);

                            $isCanonicalOwner = (int) ($patient?->customer_id ?? 0) === (int) $record->id;

                            // Notification is handled inside Service, but we can add extra if needed
                            // For table action, simple success is fine. Service sends to Database,
                            // maybe we want a toast here.

                            $toast = Notification::make();

                            if ($isCanonicalOwner) {
                                $toast
                                    ->title('Đã chuyển thành bệnh nhân')
                                    ->success()
                                    ->send();
                            } else {
                                $toast
                                    ->title('Đã liên kết hồ sơ bệnh nhân hiện có')
                                    ->body("Khách hàng trùng dữ liệu với hồ sơ {$patient?->patient_code}.")
                                    ->warning()
                                    ->send();
                            }

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Không thể chuyển thành bệnh nhân')
                                ->body('Vui lòng kiểm tra dữ liệu và thử lại.')
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('createAppointment')
                    ->label('Tạo lịch hẹn')
                    ->icon('heroicon-o-calendar')
                    ->successNotificationTitle('Đã tạo lịch hẹn')
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
                            ->options(fn (): array => BranchAccess::branchOptionsForCurrentUser())
                            ->searchable()
                            ->preload()
                            ->default(fn ($record): ?int => is_numeric($record?->branch_id) ? (int) $record->branch_id : BranchAccess::defaultBranchIdForCurrentUser())
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
                        $resolvedBranchId = is_numeric($data['branch_id'] ?? null)
                            ? (int) $data['branch_id']
                            : (is_numeric($record->branch_id) ? (int) $record->branch_id : null);

                        BranchAccess::assertCanAccessBranch(
                            branchId: $resolvedBranchId,
                            field: 'branch_id',
                            message: 'Bạn không thể tạo lịch hẹn ở chi nhánh ngoài phạm vi được phân quyền.',
                        );

                        /** @var \App\Services\PatientConversionService $service */
                        $service = app(\App\Services\PatientConversionService::class);
                        $patient = $service->convert($record);

                        Appointment::create([
                            'patient_id' => $patient->id,
                            'customer_id' => $record->id,
                            'doctor_id' => $data['doctor_id'] ?? null,
                            'branch_id' => $resolvedBranchId,
                            'date' => $data['date'],
                            'appointment_kind' => $data['appointment_kind'] ?? 'booking',
                            'status' => $data['status'] ?? Appointment::STATUS_SCHEDULED,
                            'cancellation_reason' => $data['cancellation_reason'] ?? null,
                            'reschedule_reason' => $data['reschedule_reason'] ?? null,
                            'note' => $data['note'] ?? null,
                        ]);
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
