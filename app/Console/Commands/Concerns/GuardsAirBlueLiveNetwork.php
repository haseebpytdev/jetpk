<?php

namespace App\Console\Commands\Concerns;

/**
 * Fail-closed gate for AirBlue artisan commands that can reach Zapways.
 *
 * Requires both AIRBLUE_CLI_LIVE_ENABLED=true and --allow-live.
 * Live certification remains DEFERRED_GO_LIVE_GATE.
 */
trait GuardsAirBlueLiveNetwork
{
    protected function guardAirBlueLiveNetwork(string $operation): bool
    {
        $envAllowed = filter_var(env('AIRBLUE_CLI_LIVE_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        $flagAllowed = (bool) $this->option('allow-live');

        if ($envAllowed && $flagAllowed) {
            return true;
        }

        $this->error(sprintf(
            'Blocked: %s requires --allow-live and AIRBLUE_CLI_LIVE_ENABLED=true. Live Airblue/Zapways calls are deferred to the go-live gate.',
            $operation,
        ));

        return false;
    }
}
