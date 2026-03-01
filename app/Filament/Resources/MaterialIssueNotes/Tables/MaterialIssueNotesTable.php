<?php

namespace App\Filament\Resources\MaterialIssueNotes\Tables;

use App\Models\MaterialIssueNote;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MaterialIssueNotesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('note_no')
                    ->label('Mã phiếu')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('issued_at')
                    ->label('Ngày xuất')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('patient.full_name')
                    ->label('Bệnh nhân')
                    ->searchable()
                    ->default('-'),
                TextColumn::make('branch.name')
                    ->label('Chi nhánh')
                    ->searchable()
                    ->default('-'),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => MaterialIssueNote::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        MaterialIssueNote::STATUS_DRAFT => 'gray',
                        MaterialIssueNote::STATUS_POSTED => 'success',
                        MaterialIssueNote::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('items_count')
                    ->label('Số vật tư')
                    ->counts('items'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(MaterialIssueNote::statusOptions()),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('post')
                    ->label('Xuất kho')
                    ->icon('heroicon-o-arrow-up-on-square')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (MaterialIssueNote $record): bool => $record->status === MaterialIssueNote::STATUS_DRAFT)
                    ->action(function (MaterialIssueNote $record): void {
                        $warnings = $record->post(auth()->id());

                        Notification::make()
                            ->title('Đã xuất kho thành công')
                            ->success()
                            ->send();

                        if ($warnings !== []) {
                            Notification::make()
                                ->title('Cảnh báo tồn kho thấp')
                                ->warning()
                                ->body(implode(', ', $warnings))
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
