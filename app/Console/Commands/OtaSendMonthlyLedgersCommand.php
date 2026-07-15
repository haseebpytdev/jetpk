<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Services\Communication\AdminReportMailerService;
use Illuminate\Console\Command;

class OtaSendMonthlyLedgersCommand extends Command
{
    protected $signature = 'ota:send-monthly-ledgers {--agency= : Optional agency slug}';

    protected $description = 'Send monthly OTA ledger notification emails';

    public function handle(AdminReportMailerService $service): int
    {
        $query = Agency::query();
        if (filled($this->option('agency'))) {
            $query->where('slug', (string) $this->option('agency'));
        }

        $agencies = $query->with('communicationSetting')->get();
        foreach ($agencies as $agency) {
            if (! ($agency->communicationSetting?->monthly_ledger_enabled ?? false)) {
                continue;
            }
            $service->sendMonthlyLedgers($agency);
            $this->line('Monthly ledgers sent for '.$agency->slug);
        }

        return self::SUCCESS;
    }
}
