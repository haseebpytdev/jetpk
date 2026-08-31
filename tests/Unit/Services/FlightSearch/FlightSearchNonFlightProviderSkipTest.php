<?php

namespace Tests\Unit\Services\FlightSearch;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\FlightSearch\FlightSearchService;
use ReflectionMethod;
use Tests\TestCase;

class FlightSearchNonFlightProviderSkipTest extends TestCase
{
    public function test_smtp_and_group_only_providers_are_skipped_as_non_flight(): void
    {
        $service = app(FlightSearchService::class);
        $skip = new ReflectionMethod(FlightSearchService::class, 'shouldSkipSupplierConnection');
        $skip->setAccessible(true);
        $reason = new ReflectionMethod(FlightSearchService::class, 'resolveConnectionSkipReason');
        $reason->setAccessible(true);

        foreach ([
            SupplierProvider::Smtp,
            SupplierProvider::GoogleOauth,
            SupplierProvider::AlHaider,
            SupplierProvider::Amadeus,
            SupplierProvider::Travelport,
        ] as $provider) {
            $connection = new SupplierConnection([
                'provider' => $provider,
                'is_active' => true,
                'status' => 'active',
            ]);

            $this->assertTrue(
                $skip->invoke($service, $connection, 'public_guest'),
                $provider->value.' must be skipped from flight search fan-out',
            );
            $this->assertSame(
                'non_flight_provider',
                $reason->invoke($service, $connection, 'public_guest'),
            );
        }
    }
}
