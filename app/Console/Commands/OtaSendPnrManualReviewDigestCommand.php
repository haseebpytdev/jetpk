<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Services\Communication\AdminReportMailerService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class OtaSendPnrManualReviewDigestCommand extends Command
{
    protected $signature = 'ota:send-pnr-manual-review-digest
                            {--agency= : Optional agency slug}
                            {--from= : Optional start datetime (Y-m-d or Y-m-d H:i:s)}
                            {--to= : Optional end datetime (Y-m-d or Y-m-d H:i:s)}
                            {--force : Resend even if the same period was already logged}';

    protected $description = 'Send PNR / manual-review operational digest to platform admins (manual/on-demand)';

    public function handle(AdminReportMailerService $service): int
    {
        $query = Agency::query();
        if (filled($this->option('agency'))) {
            $query->where('slug', (string) $this->option('agency'));
        }

        $agencies = $query->get();
        if ($agencies->isEmpty()) {
            $this->warn('No agencies matched the filter.');

            return self::FAILURE;
        }

        $forceResend = (bool) $this->option('force');

        foreach ($agencies as $agency) {
            $timezone = $agency->timezone ?? config('app.timezone');
            $end = $this->parseBoundary((string) $this->option('to'), $timezone, endOfDay: true)
                ?? CarbonImmutable::now($timezone);
            $start = $this->parseBoundary((string) $this->option('from'), $timezone, endOfDay: false)
                ?? $end->copy()->subDay();

            $service->sendPnrManualReviewDigest($agency, $start, $end, $forceResend);
            $this->line('PNR/manual-review digest queued/sent for '.$agency->slug.' ('.$start->toDateTimeString().' — '.$end->toDateTimeString().')');
        }

        return self::SUCCESS;
    }

    private function parseBoundary(string $value, string $timezone, bool $endOfDay): ?CarbonImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $parsed = CarbonImmutable::parse($value, $timezone);

        return strlen($value) <= 10
            ? ($endOfDay ? $parsed->endOfDay() : $parsed->startOfDay())
            : $parsed;
    }
}
