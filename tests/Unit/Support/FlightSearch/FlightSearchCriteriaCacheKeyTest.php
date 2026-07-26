<?php

namespace Tests\Unit\Support\FlightSearch;

use App\Support\FlightSearch\FlightSearchCriteriaCacheKey;
use App\Support\FlightSearch\TravellerCountRules;
use Tests\TestCase;

class FlightSearchCriteriaCacheKeyTest extends TestCase
{
    private FlightSearchCriteriaCacheKey $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = app(FlightSearchCriteriaCacheKey::class);
    }

    public function test_equivalent_normalized_requests_produce_same_fingerprint(): void
    {
        $baseCriteria = [
            'trip_type' => 'one_way',
            'origin' => 'lhe',
            'destination' => 'jed',
            'depart_date' => '2026-08-15',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'cabin' => 'economy',
            'currency' => 'pkr',
            'direct_only' => false,
            'nearby_airports' => false,
        ];
        $context = [
            'client_slug' => 'jetpk',
            'agency_id' => 7,
            'source_channel' => 'public_guest',
            'supplier_connection_scope' => [
                ['connection_id' => 3, 'provider' => 'sabre', 'lanes' => ['gds']],
            ],
        ];

        $a = $this->builder->build($baseCriteria, $context);
        $b = $this->builder->build([
            'trip_type' => 'one_way',
            'from' => 'LHE',
            'to' => 'JED',
            'depart' => '2026-08-15',
            'adults' => 1,
            'cabin' => 'ECONOMY',
            'currency' => 'PKR',
        ], $context);

        $this->assertSame($a['fingerprint'], $b['fingerprint']);
        $this->assertSame($a['cache_key'], $b['cache_key']);
    }

    public function test_one_way_and_return_searches_do_not_collide(): void
    {
        $context = ['agency_id' => 1, 'client_slug' => 'jetpk'];
        $oneWay = $this->builder->build([
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-09-01',
            'adults' => 1,
            'cabin' => 'economy',
        ], $context);
        $return = $this->builder->build([
            'trip_type' => 'round_trip',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-09-01',
            'return_date' => '2026-09-10',
            'adults' => 1,
            'cabin' => 'economy',
        ], $context);

        $this->assertNotSame($oneWay['fingerprint'], $return['fingerprint']);
    }

    public function test_passenger_mixes_do_not_collide(): void
    {
        $context = ['agency_id' => 1];
        $criteria = [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-09-01',
            'cabin' => 'economy',
        ];

        $adultOnly = $this->builder->build($criteria + ['adults' => 1, 'children' => 0, 'infants' => 0], $context);
        $withChild = $this->builder->build($criteria + ['adults' => 1, 'children' => 1, 'infants' => 0], $context);
        $withInfant = $this->builder->build($criteria + ['adults' => 1, 'children' => 0, 'infants' => 1], $context);

        $this->assertNotSame($adultOnly['fingerprint'], $withChild['fingerprint']);
        $this->assertNotSame($adultOnly['fingerprint'], $withInfant['fingerprint']);
        $this->assertNotSame($withChild['fingerprint'], $withInfant['fingerprint']);
    }

    public function test_cabin_classes_do_not_collide(): void
    {
        $context = ['agency_id' => 1];
        $base = [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-09-01',
            'adults' => 1,
        ];

        $economy = $this->builder->build($base + ['cabin' => 'economy'], $context);
        $business = $this->builder->build($base + ['cabin' => 'business'], $context);

        $this->assertNotSame($economy['fingerprint'], $business['fingerprint']);
    }

    public function test_currency_values_do_not_collide(): void
    {
        $context = ['agency_id' => 1];
        $base = [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-09-01',
            'adults' => 1,
            'cabin' => 'economy',
        ];

        $pkr = $this->builder->build($base + ['currency' => 'PKR'], $context);
        $usd = $this->builder->build($base + ['currency' => 'USD'], $context);

        $this->assertNotSame($pkr['fingerprint'], $usd['fingerprint']);
    }

    public function test_nearby_origin_flag_does_not_collide_with_fixed_origin(): void
    {
        $context = ['agency_id' => 1];
        $base = [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-09-01',
            'adults' => 1,
            'cabin' => 'economy',
        ];

        $fixed = $this->builder->build($base + ['nearby_airports' => false], $context);
        $nearby = $this->builder->build($base + ['nearby_airports' => true], $context);

        $this->assertNotSame($fixed['fingerprint'], $nearby['fingerprint']);
    }

    public function test_direct_only_does_not_collide_with_unrestricted(): void
    {
        $context = ['agency_id' => 1];
        $base = [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-09-01',
            'adults' => 1,
            'cabin' => 'economy',
        ];

        $unrestricted = $this->builder->build($base + ['direct_only' => false], $context);
        $direct = $this->builder->build($base + ['direct_only' => true], $context);

        $this->assertNotSame($unrestricted['fingerprint'], $direct['fingerprint']);
    }

    public function test_flexible_dates_do_not_collide_with_exact_dates(): void
    {
        $context = ['agency_id' => 1];
        $base = [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-09-01',
            'adults' => 1,
            'cabin' => 'economy',
        ];

        $exact = $this->builder->build($base + ['flexible_dates' => false], $context);
        $flex = $this->builder->build($base + ['flexible_dates' => true], $context);

        $this->assertNotSame($exact['fingerprint'], $flex['fingerprint']);
    }

    public function test_different_clients_and_agencies_do_not_collide(): void
    {
        $criteria = [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-09-01',
            'adults' => 1,
            'cabin' => 'economy',
        ];

        $jetpk = $this->builder->build($criteria, ['client_slug' => 'jetpk', 'agency_id' => 1]);
        $otherClient = $this->builder->build($criteria, ['client_slug' => 'other', 'agency_id' => 1]);
        $otherAgency = $this->builder->build($criteria, ['client_slug' => 'jetpk', 'agency_id' => 2]);

        $this->assertNotSame($jetpk['fingerprint'], $otherClient['fingerprint']);
        $this->assertNotSame($jetpk['fingerprint'], $otherAgency['fingerprint']);
    }

    public function test_sabre_gds_and_ndc_lanes_do_not_collide(): void
    {
        $criteria = [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-09-01',
            'adults' => 1,
            'cabin' => 'economy',
        ];
        $scopeGds = [
            ['connection_id' => 5, 'provider' => 'sabre', 'lanes' => ['gds']],
        ];
        $scopeNdc = [
            ['connection_id' => 5, 'provider' => 'sabre', 'lanes' => ['ndc']],
        ];

        $gds = $this->builder->build($criteria, [
            'agency_id' => 1,
            'supplier_connection_scope' => $scopeGds,
        ]);
        $ndc = $this->builder->build($criteria, [
            'agency_id' => 1,
            'supplier_connection_scope' => $scopeNdc,
        ]);

        $this->assertNotSame($gds['fingerprint'], $ndc['fingerprint']);
    }

    public function test_safe_summary_excludes_secrets_and_is_log_safe(): void
    {
        $built = $this->builder->build([
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-09-01',
            'adults' => 2,
            'children' => 1,
            'infants' => 0,
            'cabin' => 'economy',
        ], ['agency_id' => 1, 'client_slug' => 'jetpk']);

        $summary = $built['summary'];
        $encoded = json_encode($summary);
        $this->assertIsString($encoded);
        $this->assertArrayHasKey('trip_type', $summary);
        $this->assertArrayHasKey('fingerprint_prefix', $summary);
        $this->assertArrayNotHasKey('passenger_name', $summary);
        $this->assertArrayNotHasKey('email', $summary);
    }

    public function test_traveller_normalization_matches_rules_in_cache_key(): void
    {
        $counts = TravellerCountRules::normalizeCounts(2, 0, 3);
        $this->assertSame(2, $counts['infants']);

        $built = $this->builder->build([
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-09-01',
            'adults' => 2,
            'children' => 0,
            'infants' => 3,
            'cabin' => 'economy',
        ], ['agency_id' => 1]);

        $this->assertSame(2, $built['normalized']['infants']);
    }

    public function test_multi_city_segments_are_order_sensitive(): void
    {
        $context = ['agency_id' => 1];
        $first = $this->builder->build([
            'trip_type' => 'multi_city',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => '2026-09-01'],
                ['origin' => 'DXB', 'destination' => 'LHR', 'departure_date' => '2026-09-05'],
            ],
            'adults' => 1,
            'cabin' => 'economy',
        ], $context);
        $reversed = $this->builder->build([
            'trip_type' => 'multi_city',
            'segments' => [
                ['origin' => 'DXB', 'destination' => 'LHR', 'departure_date' => '2026-09-05'],
                ['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => '2026-09-01'],
            ],
            'adults' => 1,
            'cabin' => 'economy',
        ], $context);

        $this->assertNotSame($first['fingerprint'], $reversed['fingerprint']);
    }
}
