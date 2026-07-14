<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Services\Communication\AdminReportMailerService;
use Illuminate\Console\Command;

class OtaSendDailyReportCommand extends Command
{
    protected $signature = 'ota:send-daily-report {--agency= : Optional agency slug}';

    protected $description = 'Send daily OTA admin report emails';

    public function handle(AdminReportMailerService $service): int
    {
        $query = Agency::query();
        if (filled($this->option('agency'))) {
            $query->where('slug', (string) $this->option('agency'));
        }

        $agencies = $query->with('communicationSetting')->get();
        foreach ($agencies as $agency) {
            if (! ($agency->communicationSetting?->daily_report_enabled ?? false)) {
                continue;
            }
            $service->sendDailyReport($agency);
            $this->line('Daily report sent for '.$agency->slug);
        }

        return self::SUCCESS;
    }
}
