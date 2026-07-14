<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Services\Communication\AdminReportMailerService;
use Illuminate\Console\Command;

class OtaSendWeeklyReportCommand extends Command
{
    protected $signature = 'ota:send-weekly-report {--agency= : Optional agency slug}';

    protected $description = 'Send weekly OTA admin report emails';

    public function handle(AdminReportMailerService $service): int
    {
        $query = Agency::query();
        if (filled($this->option('agency'))) {
            $query->where('slug', (string) $this->option('agency'));
        }

        $agencies = $query->with('communicationSetting')->get();
        foreach ($agencies as $agency) {
            if (! ($agency->communicationSetting?->weekly_report_enabled ?? false)) {
                continue;
            }
            $service->sendWeeklyReport($agency);
            $this->line('Weekly report sent for '.$agency->slug);
        }

        return self::SUCCESS;
    }
}
