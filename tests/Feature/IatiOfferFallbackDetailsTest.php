<?php

namespace Tests\Feature;

use App\Data\BaggageAllowanceData;
use App\Data\FareBreakdownData;
use App\Data\NormalizedFlightOfferData;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Iati\IatiResponseNormalizer;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use App\Support\FlightSearch\FlightOfferFallbackDetailsPresenter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IatiOfferFallbackDetailsTest extends TestCase
{
    #[Test]
    public function test_single_fare_iati_offer_exposes_fallback_details_not_branded_options(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/iati/search_response_oneway.json')),
            true,
        );

        $offers = app(IatiResponseNormalizer::class)->normalizeSearchResponse(
            $fixture,
            $this->connection(),
            'corr-fallback-1',
            1,
            0,
            0,
        );

        $this->assertCount(1, $offers);
        $offer = $offers[0]->toArray();
        $this->assertSame([], $offer['branded_fares']);
        $this->assertSame('YLOWPK', $offer['fare_basis'] ?? null);
        $this->assertSame('Y', $offer['booking_class'] ?? null);
        $this->assertNotEmpty($offer['refund_rule'] ?? null);

        $presentation = FlightOfferDisplayPresenter::buildPresentation($offer, [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'trip_type' => 'one_way',
        ], []);

        $this->assertFalse($presentation['has_branded_fares']);
        $this->assertTrue($presentation['has_fallback_details']);
        $this->assertTrue($presentation['fallback_detail_sections_present']['overview']);
        $this->assertTrue($presentation['fallback_detail_sections_present']['baggage']);
    }

    #[Test]
    public function test_multi_fare_iati_offer_maps_branded_options(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/iati/search_response_multi_fare.json')),
            true,
        );

        $offers = app(IatiResponseNormalizer::class)->normalizeSearchResponse(
            $fixture,
            $this->connection(),
            'corr-brand-1',
            1,
            0,
            0,
        );

        $this->assertCount(1, $offers);
        $offer = $offers[0]->toArray();
        $this->assertCount(2, $offer['branded_fares']);

        $presentation = FlightOfferDisplayPresenter::buildPresentation($offer, [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'trip_type' => 'one_way',
        ], []);

        $this->assertTrue($presentation['has_branded_fares']);
        $this->assertGreaterThanOrEqual(2, count($presentation['fare_family_options_display']));
        $this->assertSame('Economy Saver', $presentation['fare_family_options_display'][0]['name'] ?? null);
    }

    #[Test]
    public function test_fallback_presenter_includes_fare_breakdown_and_supplier_sections(): void
    {
        $offer = (new NormalizedFlightOfferData(
            offer_id: 'iati_test_offer',
            supplier_provider: 'iati',
            supplier_connection_id: 12,
            airline_code: 'EK',
            airline_name: 'Emirates',
            flight_number: '623',
            origin: 'LHE',
            destination: 'DXB',
            departure_at: '2026-07-18T08:00:00Z',
            arrival_at: '2026-07-18T11:30:00Z',
            duration_minutes: 210,
            stops: 0,
            cabin: 'economy',
            fare_family: 'Economy Saver',
            refundable: false,
            seats_left: null,
            segments: [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'airline_code' => 'EK',
                'flight_number' => '623',
                'booking_class' => 'Y',
                'fare_basis' => 'YLOWPK',
            ]],
            baggage: new BaggageAllowanceData(checked: '30 kg', cabin: '7 kg', summary: '30 kg / 7 kg'),
            fare_breakdown: new FareBreakdownData(
                base_fare: 250.0,
                taxes: 70.0,
                supplier_fees: 0.0,
                supplier_total: 320.0,
                currency: 'USD',
            ),
            raw_payload: [
                'customer_display_fields' => [
                    'refund_rule' => 'REFUND before departure: NOT_PERMITTED',
                    'change_rule' => 'CHANGE before departure: CHARGEABLE',
                    'fare_basis' => 'YLOWPK',
                    'booking_class' => 'Y',
                ],
            ],
        ))->toArray();

        $offer['supplier_total'] = 320.0;
        $offer['base_fare'] = 250.0;
        $offer['taxes'] = 70.0;
        $offer['final_customer_price'] = 89716.0;
        $offer['displayed_price'] = 89716;

        $fallback = FlightOfferFallbackDetailsPresenter::buildForOffer($offer, [
            'departure_airport_code' => 'LHE',
            'arrival_airport_code' => 'DXB',
            'itinerary_duration_display' => '3h 30m',
            'stops_display' => 'Direct',
            'baggage_checked_display' => '30 kg',
            'baggage_cabin_display' => '7 kg',
            'segments_display' => $offer['segments'],
        ]);

        $this->assertTrue($fallback['has_fallback_details']);
        $this->assertSame('YLOWPK', $fallback['fallback_details']['fare_rules']['fare_basis'] ?? null);
        $this->assertSame(320.0, $fallback['fallback_details']['fare_breakdown']['supplier_total'] ?? null);
    }

    #[Test]
    public function test_results_blade_uses_fallback_flight_details_when_no_branded_options(): void
    {
        $src = file_get_contents(resource_path('views/frontend/flights/results.blade.php'));

        $this->assertStringContainsString('has_fallback_details', $src);
        $this->assertStringContainsString('fallback_details', $src);
        $this->assertStringContainsString('hasBrandedOptions', $src);
        $this->assertStringContainsString('ota-flight-fallback-details.js', $src);
    }

    private function connection(): SupplierConnection
    {
        return new SupplierConnection([
            'id' => 12,
            'provider' => SupplierProvider::Iati,
            'environment' => SupplierEnvironment::Sandbox,
        ]);
    }
}
