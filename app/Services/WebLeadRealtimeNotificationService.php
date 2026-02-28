<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use App\Models\WebLeadIngestion;
use App\Support\ClinicRuntimeSettings;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WebLeadRealtimeNotificationService
{
    public function notify(WebLeadIngestion $ingestion, Customer $customer, bool $created): void
    {
        if (! ClinicRuntimeSettings::webLeadRealtimeNotificationEnabled()) {
            return;
        }

        $roles = ClinicRuntimeSettings::webLeadRealtimeNotificationRoles();

        if ($roles === []) {
            return;
        }

        $recipients = $this->resolveRecipients(
            roleNames: $roles,
        );

        if ($recipients->isEmpty()) {
            return;
        }

        $title = $created
            ? 'Web Lead mới từ website'
            : 'Web Lead website đã hợp nhất';
        $branchLabel = $customer->branch?->name ?? 'Chưa xác định chi nhánh';
        $body = sprintf(
            'Khách %s - %s | Chi nhánh: %s | Request ID: %s',
            $customer->full_name ?: 'Chưa có tên',
            $customer->phone ?: 'Chưa có số điện thoại',
            $branchLabel,
            $ingestion->request_id,
        );

        foreach ($recipients as $recipient) {
            try {
                $databaseNotification = Notification::make()
                    ->title($title)
                    ->body($body)
                    ->success()
                    ->toDatabase();

                $recipient->notifyNow($databaseNotification, ['database']);
                DatabaseNotificationsSent::dispatch($recipient);
            } catch (\Throwable $throwable) {
                Log::warning('Không thể gửi realtime notification cho web lead.', [
                    'ingestion_id' => $ingestion->id,
                    'customer_id' => $customer->id,
                    'recipient_id' => $recipient->id,
                    'message' => $throwable->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  array<int, string>  $roleNames
     * @return Collection<int, User>
     */
    protected function resolveRecipients(array $roleNames): Collection
    {
        return User::query()
            ->role($roleNames)
            ->select('users.*')
            ->distinct('users.id')
            ->get();
    }
}
