<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Models\Appointment;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AppointmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'appointments';

    protected static ?string $title = 'Lịch hẹn';

    protected static ?string $recordTitleAttribute = 'title';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Ngày hẹn')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->weight('bold')
                    ->color(fn (Appointment $record) => 
                        $record->date->isPast() ? 'gray' : 'primary'
                    ),

                Tables\Columns\TextColumn::make('time_range_label')
                    ->label('Khung giờ'),

                Tables\Columns\TextColumn::make('chief_complaint')
                    ->label('Lý do')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('appointment_kind')
                    ->label('Loại lịch hẹn')
                    ->badge()
                    ->formatStateUsing(fn ($state, Appointment $record) => $record->appointment_kind_label)
                    ->color(fn (?string $state) => $state === 're_exam' ? 'warning' : 'primary'),

                Tables\Columns\TextColumn::make('doctor.name')
                    ->label('Bác sĩ')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Appointment::statusLabel($state))
                    ->color(fn (?string $state): string => Appointment::statusColor($state))
                    ->icon(fn (?string $state): string => Appointment::statusIcon($state)),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Chi nhánh')
                    ->badge(),

                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label('Thời lượng')
                    ->suffix(' phút'),

                Tables\Columns\TextColumn::make('cancellation_reason')
                    ->label('Lý do hủy')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(Appointment::statusOptions())
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;

                        if (! $value) {
                            return $query;
                        }

                        return $query->whereIn('status', Appointment::statusesForQuery([$value]));
                    }),
            ])
            ->headerActions([
                Action::make('createAppointment')
                    ->label('Đặt lịch mới')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->url(fn (): string => route('filament.admin.resources.appointments.create', [
                        'patient_id' => $this->getOwnerRecord()->id,
                    ])),
            ])
            ->actions([
                EditAction::make()
                    ->label('')
                    ->tooltip('Sửa lịch hẹn')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Appointment $record): string => 
                        route('filament.admin.resources.appointments.edit', ['record' => $record->id])),
                DeleteAction::make()
                    ->label('')
                    ->tooltip('Xóa lịch hẹn')
                    ->icon('heroicon-o-trash')
                    ->modalHeading('Xóa lịch hẹn')
                    ->modalDescription('Bạn có chắc chắn muốn xóa lịch hẹn này không?')
                    ->successNotificationTitle('Đã xóa lịch hẹn'),
            ])
            ->emptyStateHeading('Chưa có lịch hẹn')
            ->emptyStateDescription('Tạo lịch hẹn đầu tiên cho bệnh nhân này')
            ->emptyStateIcon('heroicon-o-calendar')
            ->emptyStateActions([
                Action::make('create')
                    ->label('Đặt lịch mới')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->url(fn () => route('filament.admin.resources.appointments.create', [
                        'patient_id' => $this->getOwnerRecord()->id,
                    ])),
            ])
            ->defaultSort('date', 'desc');
    }
}
