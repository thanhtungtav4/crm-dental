<?php

namespace App\Filament\Resources\WebLeadEmailDeliveries\Pages;

use App\Filament\Resources\WebLeadEmailDeliveries\WebLeadEmailDeliveryResource;
use App\Services\WebLeadInternalEmailNotificationService;
use App\Support\ClinicRuntimeSettings;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewWebLeadEmailDelivery extends ViewRecord
{
    protected static string $resource = WebLeadEmailDeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resend')
                ->label('Gửi lại')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->visible(fn (): bool => ClinicRuntimeSettings::webLeadInternalEmailEnabled()
                    && (auth()->user()?->can('update', $this->record) ?? false))
                ->action(function (): void {
                    app(WebLeadInternalEmailNotificationService::class)->resend(
                        delivery: $this->record,
                        actorId: auth()->id(),
                    );

                    Notification::make()
                        ->title('Đã xếp lại email nội bộ vào queue')
                        ->body('Delivery sẽ được worker gửi lại theo SMTP runtime hiện tại.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
