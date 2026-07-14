<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Data\FlightSearchRequestData;
use App\Services\Suppliers\AirBlue\AirBlueXmlBuilder;
use Tests\TestCase;

class AirBlueXmlBuilderTest extends TestCase
{
    public function test_air_shopping_one_way_contains_party_and_route(): void
    {
        $builder = new AirBlueXmlBuilder;
        $request = new FlightSearchRequestData(
            origin: 'KHI',
            destination: 'ISB',
            departure_date: '2025-05-28',
            return_date: null,
            adults: 1,
            children: 0,
            infants: 0,
            cabin: 'Y',
        );
        $config = [
            'agency_id' => 'SELENS',
            'agency_name' => 'NDC GATEWAY',
            'currency' => 'PKR',
            'language_code' => 'EN',
        ];

        $xml = $builder->buildAirShoppingRequest($request, $config);

        $this->assertStringContainsString('IATA_AirShoppingRQ', $xml);
        $this->assertStringContainsString('<AgencyID>SELENS</AgencyID>', $xml);
        $this->assertStringContainsString('<IATA_LocationCode>KHI</IATA_LocationCode>', $xml);
        $this->assertStringContainsString('<IATA_LocationCode>ISB</IATA_LocationCode>', $xml);
    }

    public function test_round_trip_air_shopping_has_two_origin_dest_criteria(): void
    {
        $builder = new AirBlueXmlBuilder;
        $request = new FlightSearchRequestData(
            origin: 'KHI',
            destination: 'ISB',
            departure_date: '2025-05-28',
            return_date: '2025-05-31',
            adults: 2,
            children: 1,
            infants: 1,
            cabin: 'Y',
        );
        $config = [
            'agency_id' => 'SELENS',
            'agency_name' => 'NDC GATEWAY',
            'currency' => 'PKR',
            'language_code' => 'EN',
        ];

        $xml = $builder->buildAirShoppingRequest($request, $config);

        $this->assertSame(2, substr_count($xml, '<OriginDestCriteria>'));
        $this->assertSame(4, substr_count($xml, '<PTC>'));
    }

    public function test_order_create_includes_selected_offer_refs(): void
    {
        $builder = new AirBlueXmlBuilder;
        $xml = $builder->buildOrderCreateRequest(
            [
                'agency_id' => 'SELENS',
                'agency_name' => 'NDC GATEWAY',
                'currency' => 'PKR',
                'language_code' => 'EN',
                'owner_code' => 'PK',
            ],
            [
                'shopping_response_ref_id' => 'abc-123',
                'offer_ref_id' => 'offer-1',
                'owner_code' => 'PK',
                'offer_item_refs' => [
                    ['offer_item_ref_id' => 'OfferItem-1', 'pax_ref_id' => 'ADTPax-1'],
                ],
            ],
            [[
                'pax_id' => 'PAX-ADT1',
                'ptc' => 'ADT',
                'title' => 'MR',
                'given_name' => 'JOHN',
                'surname' => 'DOE',
                'gender' => 'M',
                'birthdate' => '1990-01-01',
                'contact_info_ref_id' => 'Contact-1',
            ]],
            [
                'contact_info_id' => 'Contact-1',
                'email' => 'test@example.com',
                'phone_country' => '92',
                'phone_area' => '21',
                'phone_number' => '1234567',
            ],
        );

        $this->assertStringContainsString('ShoppingResponseRefID', $xml);
        $this->assertStringContainsString('abc-123', $xml);
        $this->assertStringContainsString('OfferItem-1', $xml);
    }
}
