<?php

namespace App\Filament\Resources\ZnsCampaigns\Tables;

use App\Models\ZnsCampaign;
use App\Services\ZnsCampaignRunnerService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ZnsCampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Mã')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('name')
                    ->label('Tên campaign')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('branch.name')
                    ->label('Chi nhánh')
                    ->default('Toàn hệ thống')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ZnsCampaign::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        ZnsCampaign::STATUS_DRAFT => 'gray',
                        ZnsCampaign::STATUS_SCHEDULED => 'warning',
                        ZnsCampaign::STATUS_RUNNING => 'info',
                        ZnsCampaign::STATUS_COMPLETED => 'success',
                        ZnsCampaign::STATUS_FAILED => 'danger',
                        ZnsCampaign::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('scheduled_at')
                    ->label('Lịch chạy')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(),
                TextColumn::make('sent_count')
                    ->label('Đã gửi')
                    ->numeric(),
                TextColumn::make('failed_count')
                    ->label('Lỗi')
                    ->numeric(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ZnsCampaign::statusOptions()),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('schedule')
                    ->label('Lên lịch')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->visible(fn (ZnsCampaign $record): bool => in_array($record->status, [
                        ZnsCampaign::STATUS_DRAFT,
                        ZnsCampaign::STATUS_FAILED,
                    ], true))
                    ->form([
                        DateTimePicker::make('scheduled_at')
                            ->label('Thời điểm chạy')
                            ->native(false)
                            ->seconds(false)
                            ->required(),
                    ])
                    ->action(function (ZnsCampaign $record, array $data): void {
                        $record->forceFill([
                            'status' => ZnsCampaign::STATUS_SCHEDULED,
                            'scheduled_at' => $data['scheduled_at'] ?? now()->addMinutes(5),
                            'finished_at' => null,
                        ])->save();
                    }),
                Action::make('runNow')
                    ->label('Chạy ngay')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (ZnsCampaign $record): bool => in_array($record->status, [
                        ZnsCampaign::STATUS_DRAFT,
                        ZnsCampaign::STATUS_SCHEDULED,
                        ZnsCampaign::STATUS_RUNNING,
                        ZnsCampaign::STATUS_FAILED,
                    ], true))
                    ->requiresConfirmation()
                    ->action(function (ZnsCampaign $record): void {
                        app(ZnsCampaignRunnerService::class)->runCampaign($record);
                    }),
                Action::make('cancel')
                    ->label('Huỷ campaign')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (ZnsCampaign $record): bool => in_array($record->status, [
                        ZnsCampaign::STATUS_DRAFT,
                        ZnsCampaign::STATUS_SCHEDULED,
                        ZnsCampaign::STATUS_RUNNING,
                        ZnsCampaign::STATUS_FAILED,
                    ], true))
                    ->action(function (ZnsCampaign $record): void {
                        $record->forceFill([
                            'status' => ZnsCampaign::STATUS_CANCELLED,
                            'finished_at' => now(),
                        ])->save();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
