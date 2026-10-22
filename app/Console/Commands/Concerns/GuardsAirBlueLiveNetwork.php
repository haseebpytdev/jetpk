<?php

namespace App\Console\Commands\Concerns;

/**
 * Explicit operator confirmation for AirBlue Artisan commands that reach Zapways.
 *
 * There is no global AirBlue network kill switch. Normal/public supplier eligibility
 * is controlled by SupplierConnection state and AirBlueConnectionSearchPolicy.
 * The existing --allow-live option is retained as a backwards-compatible CLI
 * acknowledgement for intentional supplier calls; it is not an environment gate.
 */
trait GuardsAirBlueLiveNetwork
{
    protected function guardAirBlueLiveNetwork(string $operation): bool
    {
        if ((bool) $this->option('allow-live')) {
            return true;
        }

        $this->error(sprintf(
            'Blocked: %s requires explicit --allow-live acknowledgement for this Artisan supplier call.',
            $operation,
        ));

        return false;
    }
}
