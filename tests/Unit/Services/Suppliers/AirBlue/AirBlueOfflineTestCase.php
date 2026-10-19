<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use Tests\Support\AirBlue\AirBlueOfflineHttp;
use Tests\TestCase;

/**
 * Base for AirBlue unit tests — blocks stray HTTP by default.
 */
abstract class AirBlueOfflineTestCase extends TestCase
{
    use AirBlueOfflineHttp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAirBlueOfflineHttp();
    }
}
