<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\PatientLoyaltyService;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunLoyaltyProgram extends Command
{
    protected $signature = 'growth:run-loyalty-program {--date= : Ngày chạy (Y-m-d)} {--dry-run : Chỉ preview, không ghi DB}';

    protected $description = 'Đồng bộ loyalty points theo doanh thu và referral bonus.';

    public function __construct(
        protected PatientLoyaltyService $loyaltyService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy automation loyalty.',
        );

        $dryRun = (bool) $this->option('dry-run');
        $asOf = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->endOfDay()
            : now()->endOfDay();

        $summary = $this->loyaltyService->runProgram(
            asOf: $asOf,
            persist: ! $dryRun,
            actorId: auth()->id(),
        );

        if (! $dryRun) {
            AuditLog::record(
                entityType: AuditLog::ENTITY_AUTOMATION,
                entityId: 0,
                action: AuditLog::ACTION_RUN,
                actorId: auth()->id(),
                metadata: [
                    'command' => 'growth:run-loyalty-program',
                    'as_of' => $asOf->toDateString(),
                    'summary' => $summary,
                ],
            );
        }

        $mode = $dryRun ? 'DRY RUN' : 'APPLY';
        $this->info(
            "[{$mode}] Loyalty processed. ".
            'revenue_earned='.$summary['revenue_earned'].
            ', revenue_skipped='.$summary['revenue_skipped'].
            ', referral_linked='.$summary['referral_linked'].
            ', referral_rewarded='.$summary['referral_rewarded'].
            ', referral_skipped='.$summary['referral_skipped'],
        );

        return self::SUCCESS;
    }
}
