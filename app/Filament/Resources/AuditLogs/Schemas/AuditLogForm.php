<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AuditLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Audit log')
                    ->schema([
                        TextEntry::make('entity_type')
                            ->label('Entity'),
                        TextEntry::make('entity_id')
                            ->label('Entity ID'),
                        TextEntry::make('action')
                            ->label('Hành động')
                            ->badge(),
                        TextEntry::make('actor.name')
                            ->label('Người thực hiện')
                            ->placeholder('-'),
                        TextEntry::make('metadata')
                            ->label('Metadata')
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT) : '-')
                            ->columnSpanFull(),
                        TextEntry::make('created_at')
                            ->label('Thời điểm')
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }
}
