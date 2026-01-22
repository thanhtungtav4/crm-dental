<?php

namespace App\Filament\Resources\Patients\Tables;

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
                    ->visible(fn ($record) => \App\Models\Appointment::query()
                        ->where('patient_id', $record->id)
                        ->whereIn('status', ['scheduled', 'confirmed'])
                        ->where('date', '>=', now())
                        ->exists())
                    ->modalHeading('Lịch hẹn còn hiệu lực')
                    ->form([
                        \Filament\Forms\Components\Select::make('appointment_id')
                            ->label('Chọn lịch hẹn')
                            ->options(function ($record) {
                                return \App\Models\Appointment::query()
                                    ->where('patient_id', $record->id)
                                    ->whereIn('status', ['scheduled', 'confirmed'])
                                    ->where('date', '>=', now())
                                    ->orderBy('date')
                                    ->get()
                                    ->mapWithKeys(function ($a) {
                                        $label = sprintf('%s — %s — %s',
                                            \Carbon\Carbon::parse($a->date)->format('d/m/Y H:i'),
                                            optional($a->doctor)->name ?? 'Chưa chọn bác sĩ',
                                            ucfirst($a->status)
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
                        \Filament\Forms\Components\Select::make('status')
                            ->label('Trạng thái')
                            ->options([
                                'scheduled' => 'Đã đặt',
                                'confirmed' => 'Đã xác nhận',
                                'completed' => 'Hoàn thành',
                                'cancelled' => 'Hủy',
                            ])->default('scheduled'),
                        \Filament\Forms\Components\Textarea::make('note')
                            ->label('Ghi chú')
                            ->rows(3),
                    ])
                    ->action(function (array $data, $record) {
                        \App\Models\Appointment::create([
                            'patient_id' => $record->id,
                            'doctor_id' => $data['doctor_id'] ?? null,
                            'branch_id' => $data['branch_id'] ?? $record->first_branch_id,
                            'date' => $data['date'],
                            'status' => $data['status'] ?? 'scheduled',
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
