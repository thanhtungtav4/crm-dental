<?php

namespace App\Filament\Resources\TreatmentPlans\Tables;

use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\TreatmentPlans\TreatmentPlanResource;
use App\Models\TreatmentPlan;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TreatmentPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'patient:id,full_name,patient_code,phone',
                'doctor:id,name',
            ]))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Kế hoạch')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->description(fn (TreatmentPlan $record): string => 'Mã KH #'.$record->id)
                    ->icon('heroicon-m-clipboard-document-list'),

                TextColumn::make('patient.full_name')
                    ->label('Bệnh nhân')
                    ->searchable()
                    ->sortable()
                    ->description(fn (TreatmentPlan $record): ?string => $record->patient?->patient_code
                        ? 'Mã BN: '.$record->patient->patient_code
                        : null)
                    ->url(fn (TreatmentPlan $record): ?string => $record->patient
                        ? PatientResource::getUrl('view', ['record' => $record->patient, 'tab' => 'exam-treatment'])
                        : null)
                    ->openUrlInNewTab(),

                TextColumn::make('doctor.name')
                    ->label('Bác sĩ')
                    ->placeholder('Chưa phân công')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (TreatmentPlan $record): string => $record->getStatusLabel())
                    ->icon(fn (?string $state): string => \App\Support\StatusBadge::icon($state))
                    ->color(fn (?string $state): string => \App\Support\StatusBadge::color($state))
                    ->sortable(),

                TextColumn::make('priority')
                    ->label('Ưu tiên')
                    ->badge()
                    ->formatStateUsing(fn (TreatmentPlan $record): string => $record->getPriorityLabel())
                    ->color(fn (?string $state): string => match ($state) {
                        'urgent' => 'danger',
                        'high' => 'warning',
                        'normal' => 'info',
                        'low' => 'gray',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('progress_percentage')
                    ->label('Tiến độ')
                    ->formatStateUsing(fn ($state): string => ((int) $state).'%')
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        (int) $state >= 100 => 'success',
                        (int) $state >= 50 => 'info',
                        (int) $state > 0 => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('visit_summary')
                    ->label('Số phiên')
                    ->state(fn (TreatmentPlan $record): string => sprintf(
                        '%d/%d',
                        (int) $record->completed_visits,
                        (int) $record->total_visits
                    ))
                    ->description('Đã hoàn thành / Tổng')
                    ->alignCenter()
                    ->toggleable(),

                TextColumn::make('total_cost')
                    ->label('Tổng chi phí')
                    ->money('VND')
                    ->alignEnd()
                    ->sortable()
                    ->description(fn (TreatmentPlan $record): ?string => $record->total_estimated_cost !== null
                        ? 'Dự kiến: '.number_format((float) $record->total_estimated_cost, 0, ',', '.').' đ'
                        : null),

                TextColumn::make('expected_end_date')
                    ->label('Kết thúc dự kiến')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (TreatmentPlan $record): string => $record->isOverdue() ? 'danger' : 'gray')
                    ->description(fn (TreatmentPlan $record): ?string => $record->isOverdue() ? 'Quá hạn' : null),

                TextColumn::make('updated_at')
                    ->label('Cập nhật')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'draft' => 'Nháp',
                        'approved' => 'Đã duyệt',
                        'in_progress' => 'Đang thực hiện',
                        'completed' => 'Hoàn thành',
                        'cancelled' => 'Đã hủy',
                    ]),
                SelectFilter::make('priority')
                    ->label('Độ ưu tiên')
                    ->options([
                        'low' => 'Thấp',
                        'normal' => 'Bình thường',
                        'high' => 'Cao',
                        'urgent' => 'Khẩn cấp',
                    ]),
                SelectFilter::make('doctor_id')
                    ->label('Bác sĩ')
                    ->relationship('doctor', 'name')
                    ->searchable()
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Duyệt kế hoạch')
                    ->icon('heroicon-o-check-badge')
                    ->color('info')
                    ->successNotificationTitle('Đã duyệt kế hoạch điều trị')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'approved',
                            'approved_by' => auth()->id(),
                        ]);
                    })
                    ->visible(fn ($record) => $record->status === 'draft'),
                Action::make('start')
                    ->label('Bắt đầu điều trị')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->successNotificationTitle('Đã chuyển kế hoạch sang đang thực hiện')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'in_progress',
                            'actual_start_date' => now(),
                        ]);
                    })
                    ->visible(fn ($record) => $record->status === 'approved'),
                Action::make('complete')
                    ->label('Hoàn thành')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->successNotificationTitle('Đã hoàn thành kế hoạch điều trị')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'completed',
                            'actual_end_date' => now(),
                        ]);
                        $record->updateProgress();
                    })
                    ->visible(fn ($record) => $record->status === 'in_progress'),
                Action::make('open_patient_profile')
                    ->label('Hồ sơ BN')
                    ->icon('heroicon-o-user')
                    ->color('gray')
                    ->url(fn (TreatmentPlan $record): ?string => $record->patient
                        ? PatientResource::getUrl('view', ['record' => $record->patient, 'tab' => 'exam-treatment'])
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn (TreatmentPlan $record): bool => $record->patient !== null),
                EditAction::make()
                    ->url(fn (TreatmentPlan $record): string => TreatmentPlanResource::getUrl('edit', [
                        'record' => $record,
                        'return_url' => request()->fullUrl(),
                    ])),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('approve_selected')
                        ->label('Duyệt các kế hoạch đã chọn')
                        ->icon('heroicon-o-check-badge')
                        ->color('info')
                        ->successNotificationTitle('Đã duyệt các kế hoạch đã chọn')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            foreach ($records as $record) {
                                if ($record->status === 'draft') {
                                    $record->update([
                                        'status' => 'approved',
                                        'approved_by' => auth()->id(),
                                    ]);
                                }
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('start_selected')
                        ->label('Bắt đầu các kế hoạch đã chọn')
                        ->icon('heroicon-o-play')
                        ->color('warning')
                        ->successNotificationTitle('Đã cập nhật các kế hoạch sang đang thực hiện')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            foreach ($records as $record) {
                                if ($record->status === 'approved') {
                                    $record->update([
                                        'status' => 'in_progress',
                                        'actual_start_date' => $record->actual_start_date ?? now(),
                                    ]);
                                }
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
