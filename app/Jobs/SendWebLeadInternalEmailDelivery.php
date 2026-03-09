<?php

namespace App\Jobs;

use App\Models\WebLeadEmailDelivery;
use App\Services\WebLeadInternalEmailNotificationService;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWebLeadInternalEmailDelivery implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $deliveryId,
    ) {}

    public function tries(): int
    {
        return ClinicRuntimeSettings::webLeadInternalEmailMaxAttempts();
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        $delay = ClinicRuntimeSettings::webLeadInternalEmailRetryDelayMinutes() * 60;

        return [$delay];
    }

    public function handle(WebLeadInternalEmailNotificationService $service): void
    {
        $result = $service->processDelivery($this->deliveryId);

        if (
            ($result['status'] ?? null) === WebLeadEmailDelivery::STATUS_RETRYABLE
            && ($result['delay_seconds'] ?? 0) > 0
        ) {
            $this->release((int) $result['delay_seconds']);
        }
    }
}
