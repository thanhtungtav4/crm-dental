<?php

namespace App\Filament\Resources\PopupAnnouncements\Schemas;

use App\Models\PopupAnnouncement;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PopupAnnouncementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Thông tin popup')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Mã popup')
                            ->copyable(),
                        TextEntry::make('priority')
                            ->label('Ưu tiên')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => PopupAnnouncement::priorityOptions()[$state] ?? $state),
                        TextEntry::make('status')
                            ->label('Trạng thái')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => PopupAnnouncement::statusOptions()[$state] ?? $state),
                        TextEntry::make('title')
                            ->label('Tiêu đề')
                            ->columnSpan(2),
                        TextEntry::make('created_at')
                            ->label('Tạo lúc')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('target_role_names')
                            ->label('Nhóm quyền nhận')
                            ->formatStateUsing(fn (mixed $state): string => collect(is_array($state) ? $state : [])->implode(', '))
                            ->columnSpanFull(),
                        TextEntry::make('target_branch_ids')
                            ->label('Chi nhánh nhận')
                            ->formatStateUsing(function (mixed $state): string {
                                $branchIds = collect(is_array($state) ? $state : [])
                                    ->filter(static fn (mixed $branchId): bool => is_numeric($branchId))
                                    ->map(static fn (mixed $branchId): int => (int) $branchId)
                                    ->all();

                                if ($branchIds === []) {
                                    return 'Toàn hệ thống';
                                }

                                return \App\Models\Branch::query()
                                    ->whereIn('id', $branchIds)
                                    ->pluck('name')
                                    ->implode(', ');
                            })
                            ->columnSpanFull(),
                        TextEntry::make('message')
                            ->label('Nội dung')
                            ->formatStateUsing(fn (mixed $state): string => trim((string) $state))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
