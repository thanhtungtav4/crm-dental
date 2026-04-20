<?php

namespace App\Filament\Resources\MaterialIssueNotes\Pages;

use App\Filament\Resources\MaterialIssueNotes\MaterialIssueNoteResource;
use App\Models\MaterialIssueNote;
use App\Services\MaterialIssueNoteWorkflowService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMaterialIssueNote extends EditRecord
{
    protected static string $resource = MaterialIssueNoteResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(MaterialIssueNoteWorkflowService::class)->prepareEditablePayload($this->getRecord(), $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('post')
                ->label('Xuất kho')
                ->icon('heroicon-o-arrow-up-on-square')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === MaterialIssueNote::STATUS_DRAFT)
                ->form([
                    Textarea::make('reason')
                        ->label('Ghi chú xuất kho')
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    $warnings = app(MaterialIssueNoteWorkflowService::class)->post(
                        $this->getRecord(),
                        $data['reason'] ?? null,
                        auth()->id(),
                    );

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

                    $this->refreshFormData(['status', 'posted_at', 'posted_by']);
                }),
            Action::make('cancel')
                ->label('Hủy phiếu')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->visible(fn (): bool => $this->record->status === MaterialIssueNote::STATUS_DRAFT)
                ->form([
                    Textarea::make('reason')
                        ->label('Lý do hủy')
                        ->rows(3)
                        ->required(),
                ])
                ->successNotificationTitle('Đã hủy phiếu xuất')
                ->action(function (array $data): void {
                    app(MaterialIssueNoteWorkflowService::class)->cancel(
                        $this->getRecord(),
                        $data['reason'] ?? null,
                        auth()->id(),
                    );

                    $this->refreshFormData(['status', 'posted_at', 'posted_by']);
                }),
        ];
    }
}
