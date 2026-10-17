<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class AirBlueOfflineHttpGuardTest extends AirBlueOfflineTestCase
{
    public function test_stray_http_requests_are_blocked(): void
    {
        $this->expectException(\Illuminate\Http\Client\StrayRequestException::class);
        $this->expectExceptionMessageMatches('/without a matching fake/i');

        Http::get('https://otatest4.zapways.com/v2/ota');
    }
}
