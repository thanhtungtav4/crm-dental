<?php

namespace App\Console\Commands;

use App\Models\ZnsCampaign;
use App\Services\ZnsCampaignRunnerService;
use Illuminate\Console\Command;

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
    protected $description = 'Chạy campaign ZNS đang scheduled/running với idempotency delivery log.';

    /**
     * Execute the console command.
     */
    public function handle(ZnsCampaignRunnerService $runner): int
    {
        $campaignId = $this->option('campaign_id');

        $query = ZnsCampaign::query()
            ->when(
                filled($campaignId),
                fn ($builder) => $builder->whereKey((int) $campaignId),
                fn ($builder) => $builder
                    ->whereIn('status', [ZnsCampaign::STATUS_SCHEDULED, ZnsCampaign::STATUS_RUNNING])
                    ->where(function ($innerQuery): void {
                        $innerQuery->whereNull('scheduled_at')
                            ->orWhere('scheduled_at', '<=', now());
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
            $result = $runner->runCampaign($campaign);
            $this->info(sprintf(
                '[%s] processed=%d sent=%d failed=%d skipped=%d',
                $campaign->code,
                $result['processed'],
                $result['sent'],
                $result['failed'],
                $result['skipped'],
            ));
        }

        return self::SUCCESS;
    }
}
