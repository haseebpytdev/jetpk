<?php

namespace Tests\Feature;

use App\Data\NormalizedFlightOfferData;
use App\Models\SupplierConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Sabre\SabreGdsShoppingNormalizationMatrixSupport;
use Tests\TestCase;

/**
 * Phase 18D: deterministic Sabre GDS shopping normalization matrix (fixture-driven).
 */
class SabreGdsShoppingNormalizationMatrixPhase18DTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>  $expect
     */
    #[DataProvider('oneWayMatrixProvider')]
    public function test_one_way_normalization_matrix(
        string $label,
        string $fixture,
        array $criteria,
        array $expect,
    ): void {
        $connection = SupplierConnection::factory()->create();
        $offers = SabreGdsShoppingNormalizationMatrixSupport::normalizeFixture($fixture, $criteria, $connection);
        $this->assertNotEmpty($offers, $label);
        SabreGdsShoppingNormalizationMatrixSupport::assertNoDuplicateOfferIds($offers, $label);

        $offer = $offers[0];
        $this->assertInstanceOf(NormalizedFlightOfferData::class, $offer, $label);
        $this->assertSame($expect['origin'], $offer->origin, $label.' origin');
        $this->assertSame($expect['destination'], $offer->destination, $label.' destination');
        $this->assertSame($expect['segment_count'], count($offer->segments), $label.' segment_count');
        $this->assertSame($expect['stops'], $offer->stops, $label.' stops');
        $this->assertGreaterThan(0, (float) $offer->fare_breakdown->supplier_total, $label.' supplier_total');
        $this->assertNotSame('', trim((string) $offer->fare_breakdown->currency), $label.' currency');

        SabreGdsShoppingNormalizationMatrixSupport::assertSegmentChain($offer->segments, $label);
        SabreGdsShoppingNormalizationMatrixSupport::assertSegmentsHaveRequiredFields($offer->segments, $label);

        if (isset($expect['first_marketing'])) {
            $this->assertSame(
                $expect['first_marketing'],
                strtoupper((string) ($offer->segments[0]['airline_code'] ?? '')),
                $label.' first marketing carrier',
            );
        }

        if (isset($expect['fare_family'])) {
            $this->assertSame($expect['fare_family'], (string) ($offer->fare_family ?? ''), $label.' fare_family');
        }

        if (($expect['baggage_present'] ?? false) === true) {
            $bagSummary = trim((string) ($offer->baggage->summary ?? ''));
            $this->assertNotSame('', $bagSummary, $label.' baggage summary');
        }

        $repeat = SabreGdsShoppingNormalizationMatrixSupport::normalizeFixture($fixture, $criteria, $connection);
        SabreGdsShoppingNormalizationMatrixSupport::assertSignatureStableAcrossRuns([$offer, $repeat[0]], $label);
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>  $expect
     */
    #[DataProvider('returnMatrixProvider')]
    public function test_return_normalization_matrix(
        string $label,
        string $fixture,
        array $criteria,
        array $expect,
    ): void {
        $offers = SabreGdsShoppingNormalizationMatrixSupport::normalizeFixture($fixture, $criteria);
        $this->assertNotEmpty($offers, $label);
        $offer = $offers[0];

        $this->assertSame($expect['origin'], $offer->origin, $label.' origin');
        $this->assertSame($expect['destination'], $offer->destination, $label.' destination');
        $this->assertGreaterThanOrEqual($expect['min_segments'], count($offer->segments), $label.' min_segments');
        SabreGdsShoppingNormalizationMatrixSupport::assertSegmentChain($offer->segments, $label);

        $firstDep = (string) ($offer->segments[0]['departure_at'] ?? '');
        $this->assertStringStartsWith($criteria['depart_date'], substr($firstDep, 0, 10), $label.' outbound dep date');

        if (isset($expect['overnight_segment'])) {
            $seg = $offer->segments[$expect['overnight_segment']];
            $depDay = substr((string) ($seg['departure_at'] ?? ''), 0, 10);
            $arrDay = substr((string) ($seg['arrival_at'] ?? ''), 0, 10);
            $this->assertNotSame($depDay, $arrDay, $label.' overnight rollover');
        }
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    #[DataProvider('passengerMatrixProvider')]
    public function test_passenger_mix_preserves_offer_structure(string $label, array $criteria): void
    {
        $offers = SabreGdsShoppingNormalizationMatrixSupport::normalizeFixture(
            'tests/Fixtures/sabre_bfm_v4_segment_order_baggage_brand.json',
            $criteria,
        );
        $this->assertNotEmpty($offers, $label);
        $offer = $offers[0];
        $this->assertGreaterThan(0, (float) $offer->fare_breakdown->supplier_total, $label);
        $this->assertSame('PKR', strtoupper((string) $offer->fare_breakdown->currency), $label);
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    #[DataProvider('cabinMatrixProvider')]
    public function test_cabin_request_maps_to_normalized_cabin(string $label, array $criteria, string $expectedCabin): void
    {
        $offers = SabreGdsShoppingNormalizationMatrixSupport::normalizeFixture(
            'tests/Fixtures/sabre_search_response.json',
            $criteria,
        );
        $this->assertNotEmpty($offers, $label);
        $this->assertSame($expectedCabin, strtolower((string) $offers[0]->cabin), $label);
    }

    public function test_missing_optional_brand_does_not_invent_authoritative_brand(): void
    {
        $offers = SabreGdsShoppingNormalizationMatrixSupport::normalizeFixture(
            'tests/Fixtures/sabre_search_response.json',
            [
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-06-10',
                'adults' => 1,
                'cabin' => 'economy',
            ],
        );
        $offer = $offers[0];
        $this->assertTrue(
            $offer->fare_family === null || trim((string) $offer->fare_family) === '',
            'fixture without brand must not fabricate fare_family',
        );
    }

    public static function oneWayMatrixProvider(): array
    {
        $baseOw = [
            'trip_type' => 'one_way',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'cabin' => 'economy',
            'currency' => 'PKR',
        ];

        return [
            'ow_direct_same_carrier' => [
                'ow_direct_same_carrier',
                'tests/Fixtures/sabre_search_response.json',
                array_merge($baseOw, ['origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => '2026-06-10']),
                ['origin' => 'LHE', 'destination' => 'DXB', 'segment_count' => 1, 'stops' => 0, 'first_marketing' => 'PK'],
            ],
            'ow_one_connection' => [
                'ow_one_connection',
                'tests/Fixtures/sabre_bfm_v4_two_segment_connecting_refs.json',
                array_merge($baseOw, ['origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => '2026-09-01']),
                ['origin' => 'LHE', 'destination' => 'DXB', 'segment_count' => 2, 'stops' => 1],
            ],
            'ow_mixed_carrier_baggage_brand' => [
                'ow_mixed_carrier_baggage_brand',
                'tests/Fixtures/sabre_bfm_v4_segment_order_baggage_brand.json',
                array_merge($baseOw, ['origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => '2026-09-01']),
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'segment_count' => 2,
                    'stops' => 1,
                    'first_marketing' => 'PK',
                    'fare_family' => 'MAIN',
                    'baggage_present' => true,
                ],
            ],
            'ow_overnight_connecting' => [
                'ow_overnight_connecting',
                'tests/Fixtures/sabre_bfm_v4_lhe_ist_doh_time_only_20260530.json',
                array_merge($baseOw, ['origin' => 'LHE', 'destination' => 'DOH', 'depart_date' => '2026-05-30']),
                ['origin' => 'LHE', 'destination' => 'DOH', 'segment_count' => 2, 'stops' => 1],
            ],
        ];
    }

    public static function returnMatrixProvider(): array
    {
        $baseRt = [
            'trip_type' => 'round_trip',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'cabin' => 'economy',
            'currency' => 'PKR',
            'return_date' => '2026-10-15',
        ];

        return [
            'rt_connecting_connecting_overnight' => [
                'rt_connecting_connecting_overnight',
                'tests/Fixtures/sabre_bfm_v4_round_trip_time_only_lhe_mel.json',
                array_merge($baseRt, ['origin' => 'LHE', 'destination' => 'MEL', 'depart_date' => '2026-10-01']),
                ['origin' => 'LHE', 'destination' => 'LHE', 'min_segments' => 4, 'overnight_segment' => 2],
            ],
        ];
    }

    public static function passengerMatrixProvider(): array
    {
        $base = [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-09-01',
            'cabin' => 'economy',
            'currency' => 'PKR',
        ];

        return [
            'adult_only' => ['adult_only', array_merge($base, ['adults' => 1, 'children' => 0, 'infants' => 0])],
            'adult_child' => ['adult_child', array_merge($base, ['adults' => 1, 'children' => 1, 'infants' => 0])],
            'adult_infant' => ['adult_infant', array_merge($base, ['adults' => 1, 'children' => 0, 'infants' => 1])],
            'adult_child_infant' => ['adult_child_infant', array_merge($base, ['adults' => 2, 'children' => 1, 'infants' => 1])],
        ];
    }

    public static function cabinMatrixProvider(): array
    {
        $base = [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-06-10',
            'adults' => 1,
            'currency' => 'PKR',
        ];

        return [
            'economy' => ['economy', array_merge($base, ['cabin' => 'economy']), 'economy'],
            'premium_economy' => ['premium_economy', array_merge($base, ['cabin' => 'premium_economy']), 'premium_economy'],
            'business' => ['business', array_merge($base, ['cabin' => 'business']), 'business'],
            'first' => ['first', array_merge($base, ['cabin' => 'first']), 'first'],
        ];
    }
}
