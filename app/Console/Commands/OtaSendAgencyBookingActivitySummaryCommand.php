<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Services\Communication\AdminReportMailerService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class OtaSendAgencyBookingActivitySummaryCommand extends Command
{
    protected $signature = 'ota:send-agency-booking-activity-summary
                            {--agency= : Agency slug (required unless --all-active-agencies)}
                            {--all-active-agencies : Send for each active agency (scheduler-safe; no --force)}
                            {--from= : Optional start datetime (Y-m-d or Y-m-d H:i:s)}
                            {--to= : Optional end datetime (Y-m-d or Y-m-d H:i:s)}
                            {--force : Resend even if the same period was already logged}';

    protected $description = 'Send agency booking activity summary to agency admins (manual/on-demand or scheduled all-agencies)';

    public function handle(AdminReportMailerService $service): int
    {
        $allActiveAgencies = (bool) $this->option('all-active-agencies');
        $agencySlug = trim((string) $this->option('agency'));

        if ($allActiveAgencies && $agencySlug !== '') {
            $this->error('Use either --agency=AGENCY_SLUG or --all-active-agencies, not both.');

            return self::FAILURE;
        }

        if ($allActiveAgencies) {
            return $this->sendForAllActiveAgencies($service);
        }

        if ($agencySlug === '') {
            $this->error('The --agency=AGENCY_SLUG option is required unless --all-active-agencies is used.');

            return self::FAILURE;
        }

        $agency = Agency::query()->where('slug', $agencySlug)->first();
        if ($agency === null) {
            $this->warn('No agency matched slug: '.$agencySlug);

            return self::FAILURE;
        }

        $this->sendForAgency($service, $agency);

        return self::SUCCESS;
    }

    private function sendForAllActiveAgencies(AdminReportMailerService $service): int
    {
        $query = $this->activeAgenciesQuery();
        if (! $query->exists()) {
            $this->warn('No active agencies matched the filter.');

            return self::FAILURE;
        }

        $sentCount = 0;
        $query->orderBy('id')->chunkById(50, function ($agencies) use ($service, &$sentCount): void {
            foreach ($agencies as $agency) {
                $this->sendForAgency($service, $agency);
                $sentCount++;
            }
        });

        $this->line('Agency booking activity summary queued/sent for '.$sentCount.' active agency/agencies.');

        return self::SUCCESS;
    }

    private function sendForAgency(AdminReportMailerService $service, Agency $agency): void
    {
        $forceResend = (bool) $this->option('force');
        $timezone = $agency->timezone ?? config('app.timezone');
        $end = $this->parseBoundary((string) $this->option('to'), $timezone, endOfDay: true)
            ?? CarbonImmutable::now($timezone);
        $start = $this->parseBoundary((string) $this->option('from'), $timezone, endOfDay: false)
            ?? $end->copy()->subDay();

        $service->sendAgencyBookingActivitySummary($agency, $start, $end, $forceResend);
        $this->line('Agency booking activity summary queued/sent for '.$agency->slug.' ('.$start->toDateTimeString().' — '.$end->toDateTimeString().')');
    }

    private function activeAgenciesQuery()
    {
        $query = Agency::query();
        $table = (new Agency)->getTable();

        if (Schema::hasColumn($table, 'is_active')) {
            $query->where('is_active', true);
        } elseif (Schema::hasColumn($table, 'status')) {
            $query->where('status', 'active');
        }

        return $query;
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
