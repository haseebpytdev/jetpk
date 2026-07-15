<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Services\Communication\AdminReportMailerService;
use Illuminate\Console\Command;

class OtaSendMonthlyReportCommand extends Command
{
    protected $signature = 'ota:send-monthly-report {--agency= : Optional agency slug}';

    protected $description = 'Send monthly OTA admin report emails';

    public function handle(AdminReportMailerService $service): int
    {
        $query = Agency::query();
        if (filled($this->option('agency'))) {
            $query->where('slug', (string) $this->option('agency'));
        }

        $agencies = $query->with('communicationSetting')->get();
        foreach ($agencies as $agency) {
            if (! ($agency->communicationSetting?->monthly_report_enabled ?? false)) {
                continue;
            }
            $service->sendMonthlyReport($agency);
            $this->line('Monthly report sent for '.$agency->slug);
        }

        return self::SUCCESS;
    }
}
