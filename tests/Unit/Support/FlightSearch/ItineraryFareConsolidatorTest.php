<?php

namespace Tests\Unit\Support\FlightSearch;

use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use App\Support\FlightSearch\ItineraryFareConsolidator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItineraryFareConsolidatorTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function baseIatiOffer(string $id, int $price, string $checkedKg, string $fareKey): array
    {
        return [
            'offer_id' => $id,
            'supplier_provider' => 'iati',
            'supplier_connection_id' => 7,
            'validating_carrier' => 'PF',
            'primary_display_carrier' => 'PF',
            'airline_code' => 'PF',
            'cabin' => 'economy',
            'stops' => 0,
            'departure_at' => '2026-07-16T13:20:00',
            'arrival_at' => '2026-07-16T15:40:00',
            'final_customer_price' => $price,
            'supplier_total_source' => $price - 1000,
            'refundable' => false,
            'baggage' => ['checked' => $checkedKg, 'cabin' => '7 kg'],
            'fare_breakdown' => [
                'supplier_total' => $price - 1000,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'segments' => [[
                'airline_code' => 'PF',
                'operating_airline_code' => 'PF',
                'flight_number' => 'PF-752',
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-07-16T13:20:00',
                'arrival_at' => '2026-07-16T15:40:00',
            ]],
            'raw_payload' => [
                'provider_context' => [
                    'departure_fare_key' => $fareKey,
                    'return_fare_key' => null,
                ],
            ],
        ];
    }

    #[Test]
    public function test_consolidates_identical_iati_itineraries_into_one_parent_with_two_fare_options(): void
    {
        $offers = [
            $this->baseIatiOffer('iati_offer_30', 85435, '30 kg', 'fare-key-30'),
            $this->baseIatiOffer('iati_offer_40', 87735, '40 kg', 'fare-key-40'),
        ];

        $signatureA = ItineraryFareConsolidator::signatureForOffer($offers[0]);
        $signatureB = ItineraryFareConsolidator::signatureForOffer($offers[1]);
        $this->assertSame($signatureA, $signatureB);

        $consolidated = ItineraryFareConsolidator::consolidate($offers);
        $this->assertCount(1, $consolidated);

        $parent = $consolidated[0];
        $this->assertTrue(ItineraryFareConsolidator::isConsolidatedParent($parent));
        $this->assertSame('iati_offer_30', $parent['offer_id']);
        $this->assertCount(2, $parent['fare_family_options']);

        $presentation = FlightOfferDisplayPresenter::buildBrandedFaresPresentationFields(
            FlightOfferDisplayPresenter::buildFareFamilyOptionsDisplay($parent),
            $parent,
        );
        $this->assertTrue($presentation['has_fare_choice_options']);
        $this->assertTrue($presentation['has_multiple_fare_choices']);
        $this->assertCount(2, $presentation['fare_family_options_display']);
        $this->assertSame('30 kg', $presentation['fare_family_options_display'][0]['check_in_summary'] ?? null);
        $this->assertSame('40 kg', $presentation['fare_family_options_display'][1]['check_in_summary'] ?? null);
    }

    #[Test]
    public function test_grouped_option_selection_resolves_source_offer_fare_key(): void
    {
        $offers = [
            $this->baseIatiOffer('iati_offer_30', 85435, '30 kg', 'fare-key-30'),
            $this->baseIatiOffer('iati_offer_40', 87735, '40 kg', 'fare-key-40'),
        ];
        $parent = ItineraryFareConsolidator::consolidate($offers)[0];
        $options = FlightOfferDisplayPresenter::buildFareFamilyOptionsDisplay($parent);
        $expensiveKey = (string) ($options[1]['option_key'] ?? '');
        $this->assertNotSame('', $expensiveKey);

        $selection = FlightOfferDisplayPresenter::applySelectedFareFamilyOptionToOffer($parent, $expensiveKey);
        $this->assertNull($selection['error_code']);
        $this->assertSame('fare-key-40', data_get($selection['offer'], 'raw_payload.provider_context.departure_fare_key'));
        $this->assertSame('iati_offer_30', $selection['offer']['offer_id']);
    }

    #[Test]
    public function test_different_departure_times_do_not_group(): void
    {
        $a = $this->baseIatiOffer('a', 80000, '20 kg', 'k1');
        $b = $this->baseIatiOffer('b', 82000, '30 kg', 'k2');
        $b['segments'][0]['departure_at'] = '2026-07-16T18:00:00';
        $b['departure_at'] = '2026-07-16T18:00:00';

        $this->assertNotSame(
            ItineraryFareConsolidator::signatureForOffer($a),
            ItineraryFareConsolidator::signatureForOffer($b),
        );
        $this->assertCount(2, ItineraryFareConsolidator::consolidate([$a, $b]));
    }

    #[Test]
    public function test_different_providers_do_not_group(): void
    {
        $iati = $this->baseIatiOffer('iati-1', 80000, '20 kg', 'k1');
        $sabre = $this->baseIatiOffer('sabre-1', 81000, '20 kg', 'k2');
        $sabre['supplier_provider'] = 'sabre';

        $this->assertCount(2, ItineraryFareConsolidator::consolidate([$iati, $sabre]));
    }

    #[Test]
    public function test_existing_branded_fares_inside_one_offer_are_not_consolidated_with_sibling_offer(): void
    {
        $withBranded = $this->baseIatiOffer('branded-parent', 90000, '20 kg', 'k1');
        $withBranded['branded_fares'] = [
            ['name' => 'Lite', 'price_total' => 90000, 'currency' => 'PKR'],
            ['name' => 'Flex', 'price_total' => 95000, 'currency' => 'PKR'],
        ];
        $single = $this->baseIatiOffer('single', 85000, '30 kg', 'k2');

        $this->assertCount(2, ItineraryFareConsolidator::consolidate([$withBranded, $single]));
    }
}
