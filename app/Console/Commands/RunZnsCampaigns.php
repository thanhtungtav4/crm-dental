<?php

namespace App\Console\Commands;

use App\Models\ZnsCampaign;
use App\Models\ZnsCampaignDelivery;
use App\Services\ZnsCampaignRunnerService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class RunZnsCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zns:run-campaigns {--campaign_id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Chạy campaign ZNS scheduled/running và failed có delivery retryable tới hạn.';

    /**
     * Execute the console command.
     */
    public function handle(ZnsCampaignRunnerService $runner): int
    {
        $campaignId = $this->option('campaign_id');
        $hasValidationFailure = false;

        $query = ZnsCampaign::query()
            ->when(
                filled($campaignId),
                fn ($builder) => $builder->whereKey((int) $campaignId),
                fn ($builder) => $builder
                    ->where(function ($campaignQuery): void {
                        $campaignQuery
                            ->where(function ($liveCampaignQuery): void {
                                $liveCampaignQuery
                                    ->whereIn('status', [ZnsCampaign::STATUS_SCHEDULED, ZnsCampaign::STATUS_RUNNING])
                                    ->where(function ($innerQuery): void {
                                        $innerQuery->whereNull('scheduled_at')
                                            ->orWhere('scheduled_at', '<=', now());
                                    });
                            })
                            ->orWhere(function ($failedCampaignQuery): void {
                                $failedCampaignQuery
                                    ->where('status', ZnsCampaign::STATUS_FAILED)
                                    ->whereHas('deliveries', function ($deliveryQuery): void {
                                        $deliveryQuery
                                            ->where('status', ZnsCampaignDelivery::STATUS_FAILED)
                                            ->whereNotNull('next_retry_at')
                                            ->where('next_retry_at', '<=', now());
                                    });
                            });
                    })
            )
            ->orderBy('scheduled_at')
            ->orderBy('id');

        $campaigns = $query->get();
        if ($campaigns->isEmpty()) {
            $this->info('Không có campaign ZNS nào cần chạy.');

            return self::SUCCESS;
        }

        foreach ($campaigns as $campaign) {
            try {
                $result = $runner->runCampaign($campaign);
            } catch (ValidationException $exception) {
                $hasValidationFailure = true;
                $message = collect($exception->errors())
                    ->flatten()
                    ->filter(static fn (mixed $item): bool => is_string($item) && trim($item) !== '')
                    ->implode(' ');

                $this->error(sprintf(
                    '[%s] skipped: %s',
                    $campaign->code ?? 'N/A',
                    $message !== '' ? $message : $exception->getMessage(),
                ));

                continue;
            }

            $this->info(sprintf(
                '[%s] processed=%d sent=%d failed=%d skipped=%d',
                $campaign->code,
                $result['processed'],
                $result['sent'],
                $result['failed'],
                $result['skipped'],
            ));
        }

        return $hasValidationFailure ? self::FAILURE : self::SUCCESS;
    }
}
