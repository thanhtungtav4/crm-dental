<?php

namespace App\Filament\Resources\PopupAnnouncements\Tables;

use App\Models\PopupAnnouncement;
use App\Services\PopupAnnouncementDispatchService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PopupAnnouncementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Mã')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('title')
                    ->label('Tiêu đề')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('priority')
                    ->label('Ưu tiên')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PopupAnnouncement::priorityOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        PopupAnnouncement::PRIORITY_SUCCESS => 'success',
                        PopupAnnouncement::PRIORITY_WARNING => 'warning',
                        PopupAnnouncement::PRIORITY_DANGER => 'danger',
                        default => 'info',
                    }),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PopupAnnouncement::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        PopupAnnouncement::STATUS_DRAFT => 'gray',
                        PopupAnnouncement::STATUS_SCHEDULED => 'warning',
                        PopupAnnouncement::STATUS_PUBLISHED => 'success',
                        PopupAnnouncement::STATUS_FAILED_NO_RECIPIENT => 'danger',
                        PopupAnnouncement::STATUS_CANCELLED => 'danger',
                        PopupAnnouncement::STATUS_EXPIRED => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('target_role_names')
                    ->label('Nhóm quyền')
                    ->formatStateUsing(fn (mixed $state): string => collect(is_array($state) ? $state : [])->implode(', '))
                    ->toggleable(),
                TextColumn::make('target_branch_ids')
                    ->label('Chi nhánh')
                    ->formatStateUsing(function (mixed $state): string {
                        static $branchMap = null;

                        if (! is_array($branchMap)) {
                            $branchMap = \App\Models\Branch::query()
                                ->pluck('name', 'id')
                                ->mapWithKeys(static fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])
                                ->all();
                        }

                        $branchIds = collect(is_array($state) ? $state : [])
                            ->filter(fn (mixed $branchId): bool => is_numeric($branchId))
                            ->map(fn (mixed $branchId): int => (int) $branchId)
                            ->values();

                        if ($branchIds->isEmpty()) {
                            return 'Toàn hệ thống';
                        }

                        return $branchIds
                            ->map(fn (int $branchId): string => $branchMap[$branchId] ?? (string) $branchId)
                            ->implode(', ');
                    })
                    ->toggleable(),
                TextColumn::make('starts_at')
                    ->label('Bắt đầu')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(),
                TextColumn::make('ends_at')
                    ->label('Kết thúc')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(),
                TextColumn::make('deliveries_count')
                    ->counts('deliveries')
                    ->label('Lượt gửi')
                    ->numeric(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(PopupAnnouncement::statusOptions()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('publishNow')
                    ->label('Phát ngay')
                    ->icon('heroicon-o-bell-alert')
                    ->color('success')
                    ->visible(fn (PopupAnnouncement $record): bool => in_array($record->status, [
                        PopupAnnouncement::STATUS_DRAFT,
                        PopupAnnouncement::STATUS_SCHEDULED,
                        PopupAnnouncement::STATUS_FAILED_NO_RECIPIENT,
                    ], true))
                    ->requiresConfirmation()
                    ->action(function (PopupAnnouncement $record): void {
                        $record->forceFill([
                            'status' => PopupAnnouncement::STATUS_PUBLISHED,
                            'starts_at' => $record->starts_at ?? now(),
                            'published_at' => now(),
                        ])->save();

                        $created = app(PopupAnnouncementDispatchService::class)->dispatchAnnouncement($record->refresh());

                        Notification::make()
                            ->title('Đã phát popup')
                            ->body("Đã tạo {$created} lượt gửi.")
                            ->success()
                            ->send();
                    }),
                Action::make('cancel')
                    ->label('Hủy')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (PopupAnnouncement $record): bool => in_array($record->status, [
                        PopupAnnouncement::STATUS_DRAFT,
                        PopupAnnouncement::STATUS_SCHEDULED,
                        PopupAnnouncement::STATUS_PUBLISHED,
                        PopupAnnouncement::STATUS_FAILED_NO_RECIPIENT,
                    ], true))
                    ->requiresConfirmation()
                    ->action(function (PopupAnnouncement $record): void {
                        $record->forceFill([
                            'status' => PopupAnnouncement::STATUS_CANCELLED,
                        ])->save();
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
