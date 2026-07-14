<?php

namespace App\Console\Commands;

use App\Services\Client\ClientPrefixedRouteRegistrar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class OtaClientRouteParityStatusCommand extends Command
{
    protected $signature = 'ota:client-route-parity-status
                            {--client=haseeb-master : Default deployment client slug}
                            {--target=jetpk : Example non-default client slug for sample URIs}';

    protected $description = 'MC-7B client route parity status — registered parity routes and exclusion summary';

    public function handle(ClientPrefixedRouteRegistrar $registrar): int
    {
        $clientSlug = trim((string) $this->option('client'));
        $targetSlug = trim((string) $this->option('target'));

        if ($clientSlug === '' || $targetSlug === '') {
            $this->error('Options --client and --target must not be empty.');

            return self::FAILURE;
        }

        $enabled = (bool) config('client_route_parity.enabled', true);
        $haseebParity = (bool) config('client_route_parity.allow_haseeb_master_prefixed_parity', true);

        $this->info('Client route parity status (MC-7B)');
        $this->line('enabled='.($enabled ? 'true' : 'false'));
        $this->line('allow_haseeb_master_prefixed_parity='.($haseebParity ? 'true' : 'false'));
        $this->line('host_guard_enabled='.(config('client_route_parity.host_guard_enabled') ? 'true' : 'false'));
        $this->newLine();

        $stats = $registrar->statsFromRegistry();

        $this->info(sprintf('Total parity routes registered: %d', $stats['total_registered']));
        $this->line(sprintf('Excluded high-risk (not registered): %d', $stats['excluded_high_risk_count']));
        $this->line(sprintf('Route collision count: %d', $stats['collision_count']));
        $this->newLine();

        $this->info('By classification');
        if ($stats['by_classification'] === []) {
            $this->line('  (none)');
        } else {
            arsort($stats['by_classification']);
            foreach ($stats['by_classification'] as $classification => $count) {
                $this->line(sprintf('  %-24s %d', $classification, $count));
            }
        }

        $this->newLine();
        $this->info('By portal');
        if ($stats['by_portal'] === []) {
            $this->line('  (none)');
        } else {
            arsort($stats['by_portal']);
            foreach ($stats['by_portal'] as $portal => $count) {
                $this->line(sprintf('  %-24s %d', $portal, $count));
            }
        }

        $this->newLine();
        $this->info('Sample routes');
        $this->line('  php artisan route:list --name=client.parity.login');
        $this->line('  php artisan route:list --path='.$targetSlug.'/groups/search');
        $this->line('  php artisan route:list --path='.$clientSlug.'/login');

        $this->newLine();
        $this->info('Sample prefixed URIs');
        foreach (['login', 'admin', 'groups/search', 'lookup-booking'] as $suffix) {
            $this->line(sprintf('  /%s/%s', $targetSlug, $suffix));
        }

        if ($enabled && ! Route::has('client.parity.login')) {
            $this->warn('Expected parity route client.parity.login is not registered.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
