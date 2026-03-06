<?php

namespace App\Filament\Resources\Appointments\Tables;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Patients\PatientResource;
use App\Models\Appointment;
use App\Models\Patient;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AppointmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('patient.full_name')
                    ->label('Khách hàng')
                    ->getStateUsing(function ($record) {
                        // Priority: Customer (Lead) > Patient
                        // Nếu có customer_id trực tiếp (Lead mới)
                        if ($record->customer_id && $record->customer) {
                            $customer = $record->customer;
                            $name = $customer->full_name;
                            $phone = $customer->phone ? " — {$customer->phone}" : '';

                            return $name.$phone;
                        }

                        // Nếu có patient (bệnh nhân hoặc data cũ)
                        if ($record->patient_id && $record->patient) {
                            $patient = $record->patient;
                            $name = $patient->full_name;
                            $phone = $patient->phone ? " — {$patient->phone}" : '';
                            $code = $patient->patient_code ? " [{$patient->patient_code}]" : '';

                            return $name.$code.$phone;
                        }

                        return '-';
                    })
                    ->searchable(query: function ($query, $search) {
                        $patientPhoneHash = Patient::phoneSearchHash((string) $search);

                        return $query->where(function ($q) use ($search, $patientPhoneHash) {
                            $q->whereHas('customer', function ($query) use ($search) {
                                $query->where('full_name', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%");
                            })
                                ->orWhereHas('patient', function ($query) use ($search, $patientPhoneHash) {
                                    $query->where('full_name', 'like', "%{$search}%")
                                        ->orWhere('patient_code', 'like', "%{$search}%");

                                    if ($patientPhoneHash !== null) {
                                        $query->orWhere('phone_search_hash', $patientPhoneHash);
                                    }
                                });
                        });
                    })
                    ->badge()
                    ->color(fn ($record) => $record->customer_id && ! $record->patient_id ? 'warning' : 'success')
                    ->icon(fn ($record) => $record->customer_id && ! $record->patient_id ? 'heroicon-o-user' : 'heroicon-o-check-circle')
                    ->description(fn ($record) => $record->customer_id && ! $record->patient_id ? 'Lead' : 'Bệnh nhân')
                    ->url(function ($record): ?string {
                        if ($record->patient_id && $record->patient) {
                            return PatientResource::getUrl('view', [
                                'record' => $record->patient,
                                'tab' => 'appointments',
                            ]);
                        }

                        if ($record->customer_id && $record->customer) {
                            return CustomerResource::getUrl('edit', [
                                'record' => $record->customer,
                            ]);
                        }

                        return null;
                    })
                    ->openUrlInNewTab(),

                TextColumn::make('doctor.name')->label('Bác sĩ')->toggleable(),
                TextColumn::make('branch.name')->label('Chi nhánh')->toggleable(),
                TextColumn::make('date')
                    ->label('Thời gian')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('time_range_label')
                    ->label('Khung giờ')
                    ->toggleable(),
                TextColumn::make('appointment_type')
                    ->label('Loại')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'consultation' => 'Tư vấn',
                        'treatment' => 'Điều trị',
                        'follow_up' => 'Tái khám',
                        'emergency' => 'Khẩn cấp',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'consultation' => 'info',
                        'treatment' => 'success',
                        'follow_up' => 'warning',
                        'emergency' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('appointment_kind')
                    ->label('Loại lịch hẹn')
                    ->badge()
                    ->formatStateUsing(fn ($state, $record) => $record->appointment_kind_label)
                    ->color(fn (?string $state): string => $state === 're_exam' ? 'warning' : 'primary'),
                TextColumn::make('duration_minutes')
                    ->label('Thời lượng')
                    ->suffix(' phút')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Appointment::statusLabel($state))
                    ->icon(fn (?string $state): string => Appointment::statusIcon($state))
                    ->color(fn (?string $state): string => Appointment::statusColor($state)),
                TextColumn::make('ops_flags')
                    ->label('Vận hành')
                    ->badge()
                    ->getStateUsing(function ($record): string {
                        $flags = [];

                        if ($record->is_emergency) {
                            $flags[] = 'Khẩn cấp';
                        }

                        if ($record->is_walk_in) {
                            $flags[] = 'Walk-in';
                        }

                        if ($record->late_arrival_minutes) {
                            $flags[] = 'Trễ '.$record->late_arrival_minutes.' phút';
                        }

                        if ($record->is_overbooked) {
                            $flags[] = 'Overbook';
                        }

                        return $flags === [] ? 'Bình thường' : implode(' • ', $flags);
                    })
                    ->color(fn ($record): string => $record->is_emergency ? 'danger' : (($record->is_walk_in || $record->late_arrival_minutes || $record->is_overbooked) ? 'warning' : 'gray'))
                    ->toggleable(),
                TextColumn::make('overbooking_reason')
                    ->label('Lý do overbook')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('overbookingOverrideBy.name')
                    ->label('Người override overbook')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('overbooking_override_at')
                    ->label('Thời gian override overbook')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('chief_complaint')
                    ->label('Lý do khám')
                    ->limit(50)
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('cancellation_reason')
                    ->label('Lý do hủy')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('operation_override_reason')
                    ->label('Lý do override')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('operationOverrideBy.name')
                    ->label('Người override')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('operation_override_at')
                    ->label('Thời gian override')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('mark_late_arrival')
                    ->label('Đánh dấu trễ giờ')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->successNotificationTitle('Đã ghi nhận trễ giờ')
                    ->visible(fn (Appointment $record) => in_array($record->status, Appointment::activeStatuses(), true))
                    ->form([
                        \Filament\Forms\Components\TextInput::make('late_minutes')
                            ->label('Số phút trễ')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->default(fn (Appointment $record): int => $record->date?->isPast() ? max($record->date->diffInMinutes(now()), 1) : 5),
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->label('Lý do')
                            ->rows(3)
                            ->required(),
                    ])
                    ->action(function (Appointment $record, array $data): void {
                        $record->applyOperationalOverride(
                            Appointment::OVERRIDE_LATE_ARRIVAL,
                            (string) ($data['reason'] ?? ''),
                            auth()->id(),
                            [
                                'late_minutes' => (int) ($data['late_minutes'] ?? 0),
                            ],
                        );
                    }),
                Action::make('mark_emergency')
                    ->label('Đánh dấu khẩn cấp')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->successNotificationTitle('Đã ghi nhận ca khẩn cấp')
                    ->visible(fn (Appointment $record) => ! $record->is_emergency)
                    ->form([
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->label('Lý do')
                            ->rows(3)
                            ->required(),
                    ])
                    ->action(function (Appointment $record, array $data): void {
                        $record->applyOperationalOverride(
                            Appointment::OVERRIDE_EMERGENCY,
                            (string) ($data['reason'] ?? ''),
                            auth()->id(),
                        );
                    }),
                Action::make('mark_walk_in')
                    ->label('Đánh dấu walk-in')
                    ->icon('heroicon-o-user-plus')
                    ->color('info')
                    ->successNotificationTitle('Đã ghi nhận khách walk-in')
                    ->visible(fn (Appointment $record) => ! $record->is_walk_in)
                    ->form([
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->label('Lý do')
                            ->rows(3)
                            ->required(),
                    ])
                    ->action(function (Appointment $record, array $data): void {
                        $record->applyOperationalOverride(
                            Appointment::OVERRIDE_WALK_IN,
                            (string) ($data['reason'] ?? ''),
                            auth()->id(),
                        );
                    }),

                // Action "Chuyển thành bệnh nhân" - chỉ hiện khi có customer_id nhưng chưa có patient_id
                Action::make('convert_to_patient')
                    ->label('Chuyển thành bệnh nhân')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->visible(fn ($record) => $record->customer_id && ! $record->patient_id)
                    ->requiresConfirmation()
                    ->modalHeading('Chuyển khách hàng thành bệnh nhân?')
                    ->modalDescription(fn ($record) => "Bạn có chắc muốn chuyển \"{$record->customer?->full_name}\" từ Lead thành Bệnh nhân không?")
                    ->modalSubmitActionLabel('Xác nhận chuyển đổi')
                    ->action(function ($record) {
                        $customer = $record->customer;

                        if (! $customer) {
                            Notification::make()
                                ->title('❌ Lỗi: Không tìm thấy khách hàng!')
                                ->danger()
                                ->send();

                            return;
                        }
                        try {
                            /** @var \App\Services\PatientConversionService $service */
                            $service = app(\App\Services\PatientConversionService::class);
                            $patient = $service->convert($customer, $record);
                            $isCanonicalOwner = (int) ($patient?->customer_id ?? 0) === (int) $customer->id;

                            $toast = Notification::make();

                            if ($isCanonicalOwner) {
                                $toast
                                    ->title('🎉 Đã chuyển thành bệnh nhân thành công!')
                                    ->body("Khách hàng \"{$customer->full_name}\" đã được liên kết hồ sơ: {$patient?->patient_code}")
                                    ->success()
                                    ->send();
                            } else {
                                $toast
                                    ->title('ℹ️ Đã liên kết hồ sơ bệnh nhân hiện có')
                                    ->body("Khách hàng \"{$customer->full_name}\" trùng dữ liệu, dùng hồ sơ: {$patient?->patient_code}")
                                    ->warning()
                                    ->send();
                            }
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->title('❌ Chuyển đổi thất bại')
                                ->body('Không thể chuyển đổi khách hàng thành bệnh nhân. Vui lòng thử lại.')
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }
}
