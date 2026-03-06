<?php

namespace App\Filament\Resources\ZnsCampaigns\Tables;

use App\Models\ZnsCampaign;
use App\Services\ZnsCampaignRunnerService;
use App\Services\ZnsCampaignWorkflowService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
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
                        Textarea::make('reason')
                            ->label('Ghi chú vận hành')
                            ->rows(3),
                    ])
                    ->action(function (ZnsCampaign $record, array $data): void {
                        app(ZnsCampaignWorkflowService::class)->schedule(
                            campaign: $record,
                            scheduledAt: $data['scheduled_at'] ?? null,
                            reason: $data['reason'] ?? null,
                        );
                    }),
                Action::make('runNow')
                    ->label('Chạy ngay')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (ZnsCampaign $record): bool => in_array($record->status, [
                        ZnsCampaign::STATUS_DRAFT,
                        ZnsCampaign::STATUS_SCHEDULED,
                        ZnsCampaign::STATUS_FAILED,
                    ], true))
                    ->form([
                        Textarea::make('reason')
                            ->label('Lý do chạy ngay')
                            ->rows(3),
                    ])
                    ->action(function (ZnsCampaign $record, array $data): void {
                        app(ZnsCampaignWorkflowService::class)->runNow(
                            campaign: $record,
                            reason: $data['reason'] ?? null,
                        );

                        app(ZnsCampaignRunnerService::class)->runCampaign($record);
                    }),
                Action::make('cancel')
                    ->label('Huỷ campaign')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ZnsCampaign $record): bool => in_array($record->status, [
                        ZnsCampaign::STATUS_DRAFT,
                        ZnsCampaign::STATUS_SCHEDULED,
                        ZnsCampaign::STATUS_RUNNING,
                        ZnsCampaign::STATUS_FAILED,
                    ], true))
                    ->form([
                        Textarea::make('reason')
                            ->label('Lý do huỷ')
                            ->rows(3)
                            ->required(),
                    ])
                    ->action(function (ZnsCampaign $record, array $data): void {
                        app(ZnsCampaignWorkflowService::class)->cancel(
                            campaign: $record,
                            reason: $data['reason'] ?? null,
                        );
                    }),
            ]);
    }
}
