<?php

namespace Tests\Feature\GroupTicketing;

use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlightCheckoutRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_flight_checkout_routes_remain_available(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $this->artisan('route:list', ['--name' => 'booking'])
            ->assertExitCode(0);

        $this->assertNotNull(route('flights.search', [], false));
    }
}
