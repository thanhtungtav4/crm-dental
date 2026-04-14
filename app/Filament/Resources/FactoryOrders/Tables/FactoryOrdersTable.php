<?php

namespace App\Filament\Resources\FactoryOrders\Tables;

use App\Models\FactoryOrder;
use App\Services\FactoryOrderWorkflowService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FactoryOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_no')
                    ->label('Mã lệnh')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('patient.full_name')
                    ->label('Bệnh nhân')
                    ->searchable(),
                TextColumn::make('branch.name')
                    ->label('Chi nhánh')
                    ->searchable(),
                TextColumn::make('doctor.name')
                    ->label('Bác sĩ')
                    ->toggleable(),
                TextColumn::make('supplier.name')
                    ->label('Nhà cung cấp')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => FactoryOrder::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        FactoryOrder::STATUS_DRAFT => 'gray',
                        FactoryOrder::STATUS_ORDERED => 'warning',
                        FactoryOrder::STATUS_IN_PROGRESS => 'info',
                        FactoryOrder::STATUS_DELIVERED => 'success',
                        FactoryOrder::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('due_at')
                    ->label('Hẹn trả')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('items_count')
                    ->label('Số item')
                    ->counts('items'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(FactoryOrder::statusOptions()),
                SelectFilter::make('supplier_id')
                    ->label('Nhà cung cấp')
                    ->relationship(
                        name: 'supplier',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('name'),
                    )
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (FactoryOrder $record): bool => $record->isEditable()),
                Action::make('markOrdered')
                    ->label('Chuyển đã đặt')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (FactoryOrder $record): bool => $record->status === FactoryOrder::STATUS_DRAFT)
                    ->action(function (FactoryOrder $record): void {
                        app(FactoryOrderWorkflowService::class)->markOrdered($record);
                    }),
                Action::make('markInProgress')
                    ->label('Chuyển đang làm')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (FactoryOrder $record): bool => $record->status === FactoryOrder::STATUS_ORDERED)
                    ->action(function (FactoryOrder $record): void {
                        app(FactoryOrderWorkflowService::class)->markInProgress($record);
                    }),
                Action::make('markDelivered')
                    ->label('Đánh dấu đã giao')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (FactoryOrder $record): bool => in_array($record->status, [
                        FactoryOrder::STATUS_ORDERED,
                        FactoryOrder::STATUS_IN_PROGRESS,
                    ], true))
                    ->action(function (FactoryOrder $record): void {
                        app(FactoryOrderWorkflowService::class)->markDelivered($record);
                    }),
                Action::make('markCancelled')
                    ->label('Hủy lệnh')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('notes')
                            ->label('Lý do hủy')
                            ->rows(3)
                            ->required(),
                    ])
                    ->visible(fn (FactoryOrder $record): bool => in_array($record->status, [
                        FactoryOrder::STATUS_DRAFT,
                        FactoryOrder::STATUS_ORDERED,
                        FactoryOrder::STATUS_IN_PROGRESS,
                    ], true))
                    ->action(function (FactoryOrder $record, array $data): void {
                        app(FactoryOrderWorkflowService::class)->cancel(
                            order: $record,
                            reason: (string) ($data['notes'] ?? ''),
                        );
                    }),
            ]);
    }
}
