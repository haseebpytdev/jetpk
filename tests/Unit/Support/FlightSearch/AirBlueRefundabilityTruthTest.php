<?php

namespace Tests\Unit\Support\FlightSearch;

use App\Http\Controllers\Frontend\FlightController;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use App\Support\FlightSearch\ItineraryFareConsolidator;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Offline proof that AirBlue Zapways unknown refundability does not leak as Non-refundable.
 */
class AirBlueRefundabilityTruthTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function unknownAirBlueOffer(): array
    {
        return [
            'offer_id' => 'airblue-ota-ev-test',
            'id' => 'airblue-ota-ev-test',
            'supplier_provider' => 'airblue',
            'supplier_connection_id' => 22,
            'airline_code' => 'PA',
            'airline_name' => 'AirBlue',
            'flight_number' => '201',
            'origin' => 'ISB',
            'destination' => 'KHI',
            'departure_at' => '2026-12-29T10:45:00',
            'arrival_at' => '2026-12-29T12:45:00',
            'cabin' => 'economy',
            'fare_family' => 'EV',
            'refundable' => false,
            'stops' => 0,
            'duration_h' => 2,
            'duration_m' => 0,
            'baggage' => '',
            'currency' => 'PKR',
            'pricing_currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'base_fare' => 22000,
            'taxes' => 3000,
            'supplier_total_source' => 25000,
            'final_customer_price' => 25000,
            'displayed_price' => 25000,
            'provider_context' => [
                'refundability_known' => false,
                'fare_type_code' => 'EV',
                'rbd' => 'U',
            ],
            'raw_payload' => [
                'provider_context' => [
                    'refundability_known' => false,
                    'fare_type_code' => 'EV',
                    'rbd' => 'U',
                ],
            ],
            'segments' => [[
                'airline_code' => 'PA',
                'flight_number' => '201',
                'origin' => 'ISB',
                'destination' => 'KHI',
                'departure_at' => '2026-12-29T10:45:00',
                'arrival_at' => '2026-12-29T12:45:00',
                'rbd' => 'U',
                'fare_type' => 'EV',
                'cabin_class' => 'Y',
            ]],
        ];
    }

    #[Test]
    public function test_pre_fix_contract_would_have_forced_false_as_non_refundable(): void
    {
        // Document the leak: boolean false was previously treated as supplier truth.
        $offer = $this->unknownAirBlueOffer();
        $this->assertFalse((bool) ($offer['refundable'] ?? false));
        $this->assertSame('Non-refundable', ((bool) ($offer['refundable'] ?? false)) ? 'Refundable' : 'Non-refundable');
        $this->assertTrue(((bool) ($offer['refundable'] ?? false)) === false);
    }

    #[Test]
    public function test_public_results_row_omits_refundable_when_airblue_unknown(): void
    {
        $controller = app(FlightController::class);
        $method = new ReflectionMethod(FlightController::class, 'mapOfferForResultsApi');
        $method->setAccessible(true);

        $mismatch = 0;
        $samples = [];
        $row = $method->invokeArgs($controller, [
            $this->unknownAirBlueOffer(),
            ['criteria' => ['origin' => 'ISB', 'destination' => 'KHI', 'trip_type' => 'one_way']],
            'search-test',
            Request::create('/flights/results/data', 'GET'),
            [],
            [],
            [],
            &$mismatch,
            &$samples,
        ]);

        $this->assertArrayNotHasKey('refundable', $row);
        $this->assertNull(data_get($row, 'fare_summary_display.refund_status'));
    }

    #[Test]
    public function test_filter_meta_does_not_count_unknown_airblue_as_non_refundable(): void
    {
        $controller = app(FlightController::class);
        $method = new ReflectionMethod(FlightController::class, 'buildFilterMeta');
        $method->setAccessible(true);

        $meta = $method->invoke($controller, [$this->unknownAirBlueOffer()], [], []);
        $refundableCount = 0;
        $nonRefundableCount = 0;
        foreach ($meta['refundable'] as $bucket) {
            if (($bucket['value'] ?? null) === true) {
                $refundableCount = (int) ($bucket['count'] ?? 0);
            }
            if (($bucket['value'] ?? null) === false) {
                $nonRefundableCount = (int) ($bucket['count'] ?? 0);
            }
        }

        $this->assertSame(0, $refundableCount);
        $this->assertSame(0, $nonRefundableCount);
    }

    #[Test]
    public function test_unknown_airblue_matches_neither_refundable_filter(): void
    {
        $offer = $this->unknownAirBlueOffer();
        $this->assertFalse(FlightOfferDisplayPresenter::isRefundabilityKnown($offer));
        $this->assertFalse(ItineraryFareConsolidator::offerMatchesRefundableFilter($offer, '1'));
        $this->assertFalse(ItineraryFareConsolidator::offerMatchesRefundableFilter($offer, '0'));
    }

    #[Test]
    public function test_known_sabre_non_refundable_still_matches_non_refundable_filter(): void
    {
        $offer = [
            'supplier_provider' => 'sabre',
            'airline_code' => 'EK',
            'refundable' => false,
            'stops' => 0,
            'final_customer_price' => 100000,
            'segments' => [],
        ];
        $this->assertTrue(FlightOfferDisplayPresenter::isRefundabilityKnown($offer));
        $this->assertFalse(ItineraryFareConsolidator::offerMatchesRefundableFilter($offer, '1'));
        $this->assertTrue(ItineraryFareConsolidator::offerMatchesRefundableFilter($offer, '0'));
        $this->assertSame(
            'Non-refundable',
            FlightOfferDisplayPresenter::buildFareSummaryDisplay($offer, 'Emirates', null, null, null)['refund_status']
        );
    }

    #[Test]
    public function test_known_sabre_refundable_still_matches_refundable_filter(): void
    {
        $offer = [
            'supplier_provider' => 'sabre',
            'airline_code' => 'EK',
            'refundable' => true,
            'segments' => [],
        ];
        $this->assertTrue(ItineraryFareConsolidator::offerMatchesRefundableFilter($offer, '1'));
        $this->assertFalse(ItineraryFareConsolidator::offerMatchesRefundableFilter($offer, '0'));
    }
}
