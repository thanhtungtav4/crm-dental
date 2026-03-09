<?php

namespace App\Filament\Resources\WebLeadEmailDeliveries\Tables;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\WebLeadEmailDelivery;
use App\Services\WebLeadInternalEmailNotificationService;
use App\Support\ClinicRuntimeSettings;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WebLeadEmailDeliveriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tạo lúc')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->colors([
                        'gray' => WebLeadEmailDelivery::STATUS_QUEUED,
                        'warning' => [WebLeadEmailDelivery::STATUS_PROCESSING, WebLeadEmailDelivery::STATUS_RETRYABLE],
                        'success' => WebLeadEmailDelivery::STATUS_SENT,
                        'danger' => WebLeadEmailDelivery::STATUS_DEAD,
                        'info' => WebLeadEmailDelivery::STATUS_SKIPPED,
                    ])
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        WebLeadEmailDelivery::STATUS_QUEUED => 'Queued',
                        WebLeadEmailDelivery::STATUS_PROCESSING => 'Processing',
                        WebLeadEmailDelivery::STATUS_SENT => 'Sent',
                        WebLeadEmailDelivery::STATUS_RETRYABLE => 'Retryable',
                        WebLeadEmailDelivery::STATUS_DEAD => 'Dead',
                        WebLeadEmailDelivery::STATUS_SKIPPED => 'Skipped',
                        default => 'Unknown',
                    }),
                TextColumn::make('customer.full_name')
                    ->label('Lead')
                    ->searchable()
                    ->url(fn (WebLeadEmailDelivery $record): ?string => $record->customer
                        ? CustomerResource::getUrl('edit', ['record' => $record->customer])
                        : null)
                    ->openUrlInNewTab(),
                TextColumn::make('customer.phone')
                    ->label('Điện thoại')
                    ->toggleable(),
                TextColumn::make('branch.name')
                    ->label('Chi nhánh')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('recipient_name')
                    ->label('Người nhận')
                    ->formatStateUsing(fn (?string $state, WebLeadEmailDelivery $record): string => $state ?: $record->resolvedRecipientLabel())
                    ->searchable(),
                TextColumn::make('recipient_email')
                    ->label('Email nhận')
                    ->toggleable(),
                TextColumn::make('attempt_count')
                    ->label('Attempts')
                    ->sortable(),
                TextColumn::make('next_retry_at')
                    ->label('Retry tiếp')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('sent_at')
                    ->label('Gửi lúc')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('last_error_message')
                    ->label('Lỗi gần nhất')
                    ->limit(50)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        WebLeadEmailDelivery::STATUS_QUEUED => 'Queued',
                        WebLeadEmailDelivery::STATUS_PROCESSING => 'Processing',
                        WebLeadEmailDelivery::STATUS_SENT => 'Sent',
                        WebLeadEmailDelivery::STATUS_RETRYABLE => 'Retryable',
                        WebLeadEmailDelivery::STATUS_DEAD => 'Dead',
                        WebLeadEmailDelivery::STATUS_SKIPPED => 'Skipped',
                    ]),
                SelectFilter::make('branch_id')
                    ->label('Chi nhánh')
                    ->relationship('branch', 'name'),
                SelectFilter::make('recipient_type')
                    ->label('Loại người nhận')
                    ->options([
                        WebLeadEmailDelivery::RECIPIENT_TYPE_USER => 'User',
                        WebLeadEmailDelivery::RECIPIENT_TYPE_MAILBOX => 'Mailbox',
                    ]),
            ])
            ->recordActions([
                Action::make('resend')
                    ->label('Gửi lại')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->visible(fn (WebLeadEmailDelivery $record): bool => ClinicRuntimeSettings::webLeadInternalEmailEnabled()
                        && (auth()->user()?->can('update', $record) ?? false))
                    ->action(function (WebLeadEmailDelivery $record): void {
                        app(WebLeadInternalEmailNotificationService::class)->resend(
                            delivery: $record,
                            actorId: auth()->id(),
                        );

                        Notification::make()
                            ->title('Đã xếp lại email nội bộ vào queue')
                            ->body('Delivery sẽ được worker gửi lại theo SMTP runtime hiện tại.')
                            ->success()
                            ->send();
                    }),
                ViewAction::make()
                    ->label('Xem'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
