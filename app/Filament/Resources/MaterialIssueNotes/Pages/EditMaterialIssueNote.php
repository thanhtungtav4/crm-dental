<?php

namespace App\Filament\Resources\MaterialIssueNotes\Pages;

use App\Filament\Resources\MaterialIssueNotes\MaterialIssueNoteResource;
use App\Models\MaterialIssueNote;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMaterialIssueNote extends EditRecord
{
    protected static string $resource = MaterialIssueNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('post')
                ->label('Xuất kho')
                ->icon('heroicon-o-arrow-up-on-square')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === MaterialIssueNote::STATUS_DRAFT)
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->post(auth()->id());
                    $this->refreshFormData(['status', 'posted_at', 'posted_by']);
                }),
            DeleteAction::make(),
        ];
    }
}
