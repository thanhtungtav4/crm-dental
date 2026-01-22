<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Models\Appointment;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AppointmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'appointments';

    protected static ?string $title = 'Lịch hẹn';

    protected static ?string $recordTitleAttribute = 'title';

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

                Tables\Columns\TextColumn::make('title')
                    ->label('Lý do')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('doctor.name')
                    ->label('Bác sĩ')
                    ->badge()
                    ->color('success'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'scheduled' => 'Đã đặt',
                        'confirmed' => 'Đã xác nhận',
                        'in_progress' => 'Đang khám',
                        'completed' => 'Hoàn thành',
                        'cancelled' => 'Đã hủy',
                        'no_show' => 'Không đến',
                        default => 'Không xác định',
                    })
                    ->colors([
                        'info' => 'scheduled',
                        'primary' => 'confirmed',
                        'warning' => 'in_progress',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                        'gray' => 'no_show',
                    ]),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Chi nhánh')
                    ->badge(),

                Tables\Columns\TextColumn::make('duration')
                    ->label('Thời lượng')
                    ->suffix(' phút'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'scheduled' => 'Đã đặt',
                        'confirmed' => 'Đã xác nhận',
                        'in_progress' => 'Đang khám',
                        'completed' => 'Hoàn thành',
                        'cancelled' => 'Đã hủy',
                        'no_show' => 'Không đến',
                    ]),
            ])
            ->actions([
                EditAction::make()
                    ->url(fn (Appointment $record): string => 
                        route('filament.admin.resources.appointments.edit', ['record' => $record->id])),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Chưa có lịch hẹn')
            ->emptyStateDescription('Tạo lịch hẹn đầu tiên cho bệnh nhân này')
            ->emptyStateIcon('heroicon-o-calendar')
            ->emptyStateActions([
                Action::make('create')
                    ->label('Đặt lịch mới')
                    ->icon('heroicon-o-plus')
                    ->color('info')
                    ->url(fn () => route('filament.admin.resources.appointments.create', [
                        'patient_id' => $this->getOwnerRecord()->id,
                    ])),
            ])
            ->defaultSort('date', 'desc');
    }
}
