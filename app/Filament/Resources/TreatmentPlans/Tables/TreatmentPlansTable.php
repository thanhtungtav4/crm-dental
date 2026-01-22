<?php

namespace App\Filament\Resources\TreatmentPlans\Tables;

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

class TreatmentPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\Layout\Stack::make([
                    \Filament\Tables\Columns\Layout\Split::make([
                        TextColumn::make('title')
                            ->weight('bold')
                            ->searchable()
                            ->color('primary')
                            ->icon('heroicon-m-clipboard-document-list')
                            ->grow(true),
                        TextColumn::make('status')
                            ->badge()
                            ->formatStateUsing(fn($record) => $record->getStatusLabel())
                            ->colors(\App\Support\StatusBadge::getColors())
                            ->icons(\App\Support\StatusBadge::getIcons())
                            ->grow(false),
                    ]),

                    \Filament\Tables\Columns\Layout\Split::make([
                        TextColumn::make('patient.full_name')
                            ->label('Bệnh nhân')
                            ->icon('heroicon-m-user')
                            ->color('gray'),
                        TextColumn::make('doctor.name')
                            ->label('Bác sĩ')
                            ->icon('heroicon-m-user-circle')
                            ->color('gray'),
                    ])->from('md'),

                    \Filament\Tables\Columns\Layout\Split::make([
                        TextColumn::make('total_cost')
                            ->money('VND')
                            ->color('success')
                            ->icon('heroicon-m-currency-dollar'),
                        TextColumn::make('expected_end_date')
                            ->date('d/m/Y')
                            ->icon('heroicon-m-calendar')
                            ->color('secondary'),
                    ])->from('md'),

                    \Filament\Tables\Columns\Layout\Stack::make([
                        TextColumn::make('progress_percentage')
                            ->formatStateUsing(fn($state) => "Tiến độ: {$state}%")
                            ->color('gray')
                            ->size('xs'),

                        // We can use a View column for a real progress bar if needed, 
                        // but for now let's use a visual indicator or just colorful text
                        TextColumn::make('progress_visual')
                            ->default(fn($record) => str_repeat('█', (int) ($record->progress_percentage / 10)) . str_repeat('░', 10 - (int) ($record->progress_percentage / 10)))
                            ->color(fn($record) => $record->getProgressBadgeColor())
                            ->fontFamily('mono')
                            ->size('xs'),
                    ])->space(1),
                ])->space(3),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
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
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Duyệt kế hoạch')
                    ->icon('heroicon-o-check-badge')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'approved',
                            'approved_by' => auth()->id(),
                        ]);
                    })
                    ->visible(fn($record) => $record->status === 'draft'),
                Action::make('start')
                    ->label('Bắt đầu điều trị')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'in_progress',
                            'actual_start_date' => now(),
                        ]);
                    })
                    ->visible(fn($record) => in_array($record->status, ['approved', 'draft'])),
                Action::make('complete')
                    ->label('Hoàn thành')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'completed',
                            'actual_end_date' => now(),
                        ]);
                        $record->updateProgress();
                    })
                    ->visible(fn($record) => $record->status === 'in_progress'),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('approve_selected')
                        ->label('Duyệt các kế hoạch đã chọn')
                        ->icon('heroicon-o-check-badge')
                        ->color('info')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
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
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            foreach ($records as $record) {
                                if (in_array($record->status, ['approved', 'draft'])) {
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
