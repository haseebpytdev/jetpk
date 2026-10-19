<?php

namespace Tests\Support\AirBlue;

use Illuminate\Support\Facades\Http;

/**
 * Hard no-network harness for AirBlue/Zapways tests.
 *
 * Prevents accidental outbound HTTP to Zapways or any other host.
 */
trait AirBlueOfflineHttp
{
    protected function setUpAirBlueOfflineHttp(): void
    {
        // Do not call Http::fake() with no args — that accepts all URLs and defeats the guard.
        Http::preventStrayRequests();
    }
}
