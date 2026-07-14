<?php

namespace App\Console\Commands;

use App\Models\ClientProfile;
use App\Services\Client\ClientProfileResolver;
use App\Services\Client\CurrentClientContext;
use App\Services\Homepage\JetpkHomepageRouteFareRefreshService;
use Illuminate\Console\Command;

/**
 * Refreshes JetPK homepage trending route dynamic fares (read-only search).
 */
class JetpkHomepageRouteFaresRefreshCommand extends Command
{
    protected $signature = 'jetpk:homepage-route-fares-refresh
                            {--profile= : Client profile slug}
                            {--dry-run : Perform search without persisting results}';

    protected $description = 'Refresh JetPakistan homepage trending route fares (travel date = today + 7 days).';

    public function handle(
        JetpkHomepageRouteFareRefreshService $refreshService,
        ClientProfileResolver $profileResolver,
        CurrentClientContext $clientContext,
    ): int {
        $profile = $this->resolveProfile($profileResolver, $clientContext);
        if ($profile === null) {
            $this->error('Client profile not found.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $summary = $refreshService->refreshProfile($profile, ! $dryRun);

        $this->line('refreshed='.$summary['refreshed'].' success='.$summary['success'].' failed='.$summary['failed'].' skipped='.$summary['skipped']);

        foreach ($summary['results'] as $row) {
            $this->line(sprintf(
                '%s %s→%s depart=%s return=%s results=%s fare=%s status=%s',
                $row['route_id'] ?? '-',
                $row['origin'] ?? '-',
                $row['destination'] ?? '-',
                $row['departure_date'] ?? '-',
                $row['return_date'] ?? '-',
                $row['result_count'] ?? '-',
                isset($row['chosen_fare']) ? (string) $row['chosen_fare'] : '-',
                $row['status'] ?? '-',
            ));
        }

        return self::SUCCESS;
    }

    private function resolveProfile(ClientProfileResolver $resolver, CurrentClientContext $context): ?ClientProfile
    {
        $slug = trim((string) $this->option('profile'));
        if ($slug !== '') {
            return ClientProfile::query()->where('slug', $slug)->where('is_active', true)->first();
        }

        return $context->get() ?? $resolver->resolveDefault();
    }
}
