<?php

namespace App\Filament\Resources\WebLeadEmailDeliveries\Schemas;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WebLeadEmailDeliveryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tổng quan')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('id')
                            ->label('Delivery')
                            ->formatStateUsing(fn ($state): string => '#'.$state)
                            ->badge(),
                        TextEntry::make('status')
                            ->label('Trạng thái')
                            ->badge(),
                        TextEntry::make('created_at')
                            ->label('Tạo lúc')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('customer.full_name')
                            ->label('Lead')
                            ->url(fn ($record): ?string => $record->customer
                                ? CustomerResource::getUrl('edit', ['record' => $record->customer])
                                : null)
                            ->openUrlInNewTab(),
                        TextEntry::make('customer.phone')
                            ->label('Điện thoại')
                            ->placeholder('-'),
                        TextEntry::make('branch.name')
                            ->label('Chi nhánh')
                            ->placeholder('-'),
                        TextEntry::make('recipient_type')
                            ->label('Loại người nhận')
                            ->badge(),
                        TextEntry::make('recipient_name')
                            ->label('Người nhận')
                            ->formatStateUsing(fn (?string $state, $record): string => $state ?: $record->resolvedRecipientLabel()),
                        TextEntry::make('recipient_email')
                            ->label('Email nhận')
                            ->placeholder('-'),
                    ]),
                Section::make('Retry & transport')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('attempt_count')
                            ->label('Attempts'),
                        TextEntry::make('manual_resend_count')
                            ->label('Resend tay'),
                        TextEntry::make('processing_token')
                            ->label('Processing token')
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('last_attempt_at')
                            ->label('Attempt gần nhất')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('next_retry_at')
                            ->label('Retry tiếp')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('sent_at')
                            ->label('Gửi lúc')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('transport_message_id')
                            ->label('Transport message ID')
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('last_error_message')
                            ->label('Lỗi gần nhất')
                            ->placeholder('-')
                            ->columnSpan(2),
                    ]),
                Section::make('Snapshot')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('payload')
                            ->label('Payload snapshot')
                            ->formatStateUsing(fn ($state): string => $state ? json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '-')
                            ->columnSpanFull(),
                        TextEntry::make('mailer_snapshot')
                            ->label('Mailer snapshot')
                            ->formatStateUsing(fn ($state): string => $state ? json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '-')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
