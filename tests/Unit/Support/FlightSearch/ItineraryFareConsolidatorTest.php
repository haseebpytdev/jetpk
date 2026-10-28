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

    /**
     * @return array<string, mixed>
     */
    protected function baseAirBlueOffer(string $id, string $flightNumber, string $depart, string $arrive, string $fareType, float $supplierTotal, string $fareBasis): array
    {
        return [
            'offer_id' => $id,
            'supplier_provider' => 'airblue',
            'supplier_connection_id' => 22,
            'validating_carrier' => 'PA',
            'primary_display_carrier' => 'PA',
            'airline_code' => 'PA',
            'airline_name' => 'AirBlue',
            'flight_number' => $flightNumber,
            'cabin' => 'economy',
            'fare_family' => $fareType,
            'stops' => 0,
            'departure_at' => $depart,
            'arrival_at' => $arrive,
            'final_customer_price' => $supplierTotal,
            'supplier_total_source' => $supplierTotal,
            'refundable' => false,
            'baggage' => ['checked' => null, 'cabin' => null, 'summary' => null],
            'fare_breakdown' => [
                'supplier_total' => $supplierTotal,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'segments' => [[
                'airline_code' => 'PA',
                'operating_airline_code' => 'PA',
                'flight_number' => $flightNumber,
                'origin' => 'ISB',
                'destination' => 'KHI',
                'departure_at' => $depart,
                'arrival_at' => $arrive,
                'rbd' => 'U',
                'booking_class' => 'U',
                'fare_type' => $fareType,
                'cabin_class' => 'Y',
            ]],
            'provider_context' => [
                'fare_type_code' => $fareType,
                'rbd' => 'U',
                'fare_basis' => $fareBasis,
                'refundability_known' => false,
            ],
            'raw_payload' => [
                'provider_context' => [
                    'fare_type_code' => $fareType,
                    'rbd' => 'U',
                    'fare_basis' => $fareBasis,
                    'refundability_known' => false,
                    'priced_itineraries' => [['offer_ref' => $id]],
                ],
                'customer_display_fields' => [
                    'booking_class' => 'U',
                    'fare_basis' => $fareBasis,
                    'fare_type' => $fareType,
                ],
            ],
        ];
    }

    #[Test]
    public function test_airblue_same_itinerary_faretypes_consolidate_without_cross_flight_bleed(): void
    {
        $offers = [
            $this->baseAirBlueOffer('ab-ev', '201', '2026-12-29T10:45:00', '2026-12-29T12:45:00', 'EV', 25000, 'UEV1'),
            $this->baseAirBlueOffer('ab-ef', '201', '2026-12-29T10:45:00', '2026-12-29T12:45:00', 'EF', 26000, 'UEF1'),
            $this->baseAirBlueOffer('ab-ex', '201', '2026-12-29T10:45:00', '2026-12-29T12:45:00', 'EX', 27100, 'UEX1'),
            $this->baseAirBlueOffer('ab-207', '207', '2026-12-29T19:30:00', '2026-12-29T21:30:00', 'EF', 26000, 'UEF1'),
        ];

        $consolidated = ItineraryFareConsolidator::consolidate($offers);
        $this->assertCount(2, $consolidated);

        $parents = [];
        foreach ($consolidated as $row) {
            $parents[(string) ($row['flight_number'] ?? '')] = $row;
        }

        $this->assertTrue(ItineraryFareConsolidator::isConsolidatedParent($parents['201']));
        $this->assertCount(3, $parents['201']['fare_family_options']);
        $names = array_map(static fn ($o) => (string) ($o['name'] ?? ''), $parents['201']['fare_family_options']);
        $this->assertSame(['EV', 'EF', 'EX'], $names);
        $this->assertSame(25000.0, (float) $parents['201']['fare_family_options'][0]['price_total']);
        $this->assertSame('U', $parents['201']['fare_family_options'][0]['booking_class']);
        $this->assertSame('EV', $parents['201']['fare_family_options'][0]['brand_code']);
        $this->assertArrayNotHasKey('refundable_display', $parents['201']['fare_family_options'][0]);

        $this->assertFalse(ItineraryFareConsolidator::isConsolidatedParent($parents['207']));
        $this->assertSame('EF', $parents['207']['fare_family']);

        $unknown = $this->baseAirBlueOffer('ab-ev', '201', '2026-12-29T10:45:00', '2026-12-29T12:45:00', 'EV', 25000, 'UEV1');
        $this->assertFalse(ItineraryFareConsolidator::offerMatchesRefundableFilter($unknown, '1'));
        $this->assertFalse(ItineraryFareConsolidator::offerMatchesRefundableFilter($unknown, '0'));
        $this->assertFalse(ItineraryFareConsolidator::offerMatchesRefundableFilter($parents['201'], '1'));
        $this->assertFalse(ItineraryFareConsolidator::offerMatchesRefundableFilter($parents['201'], '0'));
    }

    #[Test]
    public function test_airblue_for_storage_consolidates_airblue_only_and_leaves_other_suppliers_raw(): void
    {
        $airblue = [
            $this->baseAirBlueOffer('ab-ev', '201', '2026-12-29T10:45:00', '2026-12-29T12:45:00', 'EV', 25000, 'UEV1'),
            $this->baseAirBlueOffer('ab-ef', '201', '2026-12-29T10:45:00', '2026-12-29T12:45:00', 'EF', 26000, 'UEF1'),
            $this->baseAirBlueOffer('ab-ex', '201', '2026-12-29T10:45:00', '2026-12-29T12:45:00', 'EX', 27100, 'UEX1'),
        ];
        $sabre = $this->baseIatiOffer('sabre-1', 90000, '20 kg', 'sk1');
        $sabre['supplier_provider'] = 'sabre';
        $sabre['id'] = 'sabre-1';
        $sabre['offer_id'] = 'sabre-1';
        $iatiA = $this->baseIatiOffer('iati_offer_20', 80000, '20 kg', 'fare-key-20');
        $iatiB = $this->baseIatiOffer('iati_offer_30', 85435, '30 kg', 'fare-key-30');
        $pia = $this->baseIatiOffer('pia-1', 70000, '20 kg', 'pia-k1');
        $pia['supplier_provider'] = 'pia_ndc';
        $pia['id'] = 'pia-1';
        $pia['offer_id'] = 'pia-1';
        $duffel = $this->baseIatiOffer('duffel-1', 75000, '20 kg', 'duffel-k1');
        $duffel['supplier_provider'] = 'duffel';
        $duffel['id'] = 'duffel-1';
        $duffel['offer_id'] = 'duffel-1';

        $mixed = array_merge($airblue, [$sabre, $iatiA, $iatiB, $pia, $duffel]);
        $beforeIds = array_map(
            static fn (array $o): string => (string) ($o['offer_id'] ?? $o['id'] ?? ''),
            $mixed,
        );

        $stored = ItineraryFareConsolidator::consolidateAirblueForStorage($mixed);

        $airblueStored = array_values(array_filter(
            $stored,
            static fn (array $o): bool => strtolower((string) ($o['supplier_provider'] ?? '')) === 'airblue',
        ));
        $this->assertCount(1, $airblueStored);
        $this->assertTrue(ItineraryFareConsolidator::isConsolidatedParent($airblueStored[0]));
        $this->assertCount(3, $airblueStored[0]['fare_family_options'] ?? []);

        $nonAirblue = array_values(array_filter(
            $stored,
            static fn (array $o): bool => strtolower((string) ($o['supplier_provider'] ?? '')) !== 'airblue',
        ));
        $nonAirblueIds = array_map(
            static fn (array $o): string => (string) ($o['offer_id'] ?? $o['id'] ?? ''),
            $nonAirblue,
        );
        sort($nonAirblueIds);
        $expectedNonAirblue = ['sabre-1', 'iati_offer_20', 'iati_offer_30', 'pia-1', 'duffel-1'];
        sort($expectedNonAirblue);
        $this->assertSame($expectedNonAirblue, $nonAirblueIds);

        foreach ($nonAirblue as $row) {
            $this->assertFalse(
                ItineraryFareConsolidator::isConsolidatedParent($row),
                'non-AirBlue offer must remain raw at storage scope',
            );
        }

        // Broad consolidate would collapse the IATI pair; storage scope must not.
        $broad = ItineraryFareConsolidator::consolidate($mixed);
        $broadIatiParents = array_values(array_filter(
            $broad,
            static fn (array $o): bool => strtolower((string) ($o['supplier_provider'] ?? '')) === 'iati'
                && ItineraryFareConsolidator::isConsolidatedParent($o),
        ));
        $this->assertNotSame([], $broadIatiParents);
        $storedIatiParents = array_values(array_filter(
            $stored,
            static fn (array $o): bool => strtolower((string) ($o['supplier_provider'] ?? '')) === 'iati'
                && ItineraryFareConsolidator::isConsolidatedParent($o),
        ));
        $this->assertSame([], $storedIatiParents);

        $this->assertContains('sabre-1', $beforeIds);
        $this->assertContains('sabre-1', $nonAirblueIds);
    }
}
