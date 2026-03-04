<?php

namespace App\Console\Commands;

use App\Services\PopupAnnouncementDispatchService;
use Illuminate\Console\Command;

class DispatchDuePopupAnnouncements extends Command
{
    protected $signature = 'popups:dispatch-due {--strict : Trả về exit code 1 nếu dispatch thất bại hoặc không bật module}';

    protected $description = 'Phát các popup announcement đến user theo role + chi nhánh';

    public function __construct(private readonly PopupAnnouncementDispatchService $dispatchService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $report = $this->dispatchService->dispatchDueAnnouncements();

        $this->line(sprintf(
            'popup_enabled=%s announcements_processed=%d announcements_expired=%d deliveries_created=%d deliveries_expired=%d',
            $report['enabled'] ? 'true' : 'false',
            $report['announcements_processed'],
            $report['announcements_expired'],
            $report['deliveries_created'],
            $report['deliveries_expired'],
        ));

        if ($this->option('strict') && ! $report['enabled']) {
            $this->error('Popup module đang tắt, strict mode trả về lỗi.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
