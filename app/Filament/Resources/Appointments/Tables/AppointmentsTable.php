<?php

namespace App\Filament\Resources\Appointments\Tables;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Models\Patient;
use Filament\Notifications\Notification;

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
                            return $name . $phone;
                        }
                        
                        // Nếu có patient (bệnh nhân hoặc data cũ)
                        if ($record->patient_id && $record->patient) {
                            $patient = $record->patient;
                            $name = $patient->full_name;
                            $phone = $patient->phone ? " — {$patient->phone}" : '';
                            $code = $patient->patient_code ? " [{$patient->patient_code}]" : '';
                            return $name . $code . $phone;
                        }
                        
                        return '-';
                    })
                    ->searchable(query: function ($query, $search) {
                        return $query->where(function ($q) use ($search) {
                            $q->whereHas('customer', function ($query) use ($search) {
                                $query->where('full_name', 'like', "%{$search}%")
                                      ->orWhere('phone', 'like', "%{$search}%");
                            })
                            ->orWhereHas('patient', function ($query) use ($search) {
                                $query->where('full_name', 'like', "%{$search}%")
                                      ->orWhere('phone', 'like', "%{$search}%")
                                      ->orWhere('patient_code', 'like', "%{$search}%");
                            });
                        });
                    })
                    ->badge()
                    ->color(fn ($record) => $record->customer_id && !$record->patient_id ? 'warning' : 'success')
                    ->icon(fn ($record) => $record->customer_id && !$record->patient_id ? 'heroicon-o-user' : 'heroicon-o-check-circle')
                    ->description(fn ($record) => $record->customer_id && !$record->patient_id ? 'Lead' : 'Bệnh nhân'),
                    
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
                    ->icon(fn (?string $state) => \App\Support\StatusBadge::icon($state))
                    ->color(fn (?string $state) => \App\Support\StatusBadge::color($state)),
                TextColumn::make('chief_complaint')
                    ->label('Lý do khám')
                    ->limit(50)
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('cancellation_reason')
                    ->label('Lý do hủy')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                
                // Action "Chuyển thành bệnh nhân" - chỉ hiện khi có customer_id nhưng chưa có patient_id
                Action::make('convert_to_patient')
                    ->label('Chuyển thành bệnh nhân')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->visible(fn ($record) => $record->customer_id && !$record->patient_id)
                    ->requiresConfirmation()
                    ->modalHeading('Chuyển khách hàng thành bệnh nhân?')
                    ->modalDescription(fn ($record) => "Bạn có chắc muốn chuyển \"{$record->customer?->full_name}\" từ Lead thành Bệnh nhân không?")
                    ->modalSubmitActionLabel('Xác nhận chuyển đổi')
                    ->action(function ($record) {
                        $customer = $record->customer;
                        
                        if (!$customer) {
                            Notification::make()
                                ->title('❌ Lỗi: Không tìm thấy khách hàng!')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        // Kiểm tra xem customer đã có patient chưa
                        $existingPatient = Patient::where('customer_id', $customer->id)->first();
                        
                        if ($existingPatient) {
                            // Nếu đã có patient rồi, chỉ cần link
                            $record->patient_id = $existingPatient->id;
                            $record->save();
                            
                            Notification::make()
                                ->title('✅ Đã liên kết với bệnh nhân hiện có!')
                                ->body("Lịch hẹn đã được liên kết với bệnh nhân \"{$existingPatient->full_name}\".")
                                ->success()
                                ->send();
                        } else {
                            // Tạo Patient mới
                            $patient = Patient::create([
                                'customer_id' => $customer->id,
                                'patient_code' => 'BN' . str_pad(Patient::max('id') + 1, 6, '0', STR_PAD_LEFT),
                                'first_branch_id' => $record->branch_id,
                                'full_name' => $customer->full_name,
                                'phone' => $customer->phone,
                                'email' => $customer->email,
                                'address' => $customer->address ?? null,
                                'customer_group_id' => $customer->customer_group_id,
                                'promotion_group_id' => $customer->promotion_group_id,
                                'owner_staff_id' => $customer->assigned_to,
                                'created_by' => auth()->id(),
                                'updated_by' => auth()->id(),
                            ]);
                            
                            // Link appointment với patient
                            $record->patient_id = $patient->id;
                            $record->save();
                            
                            // Cập nhật Customer status
                            $customer->status = 'converted';
                            $customer->save();
                            
                            Notification::make()
                                ->title('🎉 Đã chuyển thành bệnh nhân thành công!')
                                ->body("Khách hàng \"{$customer->full_name}\" đã trở thành bệnh nhân với mã: {$patient->patient_code}")
                                ->success()
                                ->send();
                        }
                    }),
            ]);
    }
}
