<?php

namespace Tests\Unit\Services\Suppliers\PiaNdc;

use App\Data\FlightSearchRequestData;
use App\Services\Suppliers\PiaNdc\PiaNdcXmlBuilder;
use Tests\TestCase;

class PiaNdcXmlBuilderTest extends TestCase
{
    public function test_air_shopping_one_way_contains_party_and_route(): void
    {
        $builder = new PiaNdcXmlBuilder;
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
        $builder = new PiaNdcXmlBuilder;
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

        preg_match_all('/<OriginDestCriteria>.*?<\/OriginDestCriteria>/s', $xml, $matches);
        $this->assertCount(2, $matches[0], 'Round-trip must build outbound and return OriginDestCriteria blocks.');

        $outbound = $matches[0][0];
        $this->assertStringContainsString('<IATA_LocationCode>KHI</IATA_LocationCode>', $outbound);
        $this->assertStringContainsString('<IATA_LocationCode>ISB</IATA_LocationCode>', $outbound);
        $this->assertStringContainsString('<Date>2025-05-28</Date>', $outbound);

        $returnLeg = $matches[0][1];
        $this->assertStringContainsString('<IATA_LocationCode>ISB</IATA_LocationCode>', $returnLeg);
        $this->assertStringContainsString('<IATA_LocationCode>KHI</IATA_LocationCode>', $returnLeg);
        $this->assertStringContainsString('<Date>2025-05-31</Date>', $returnLeg);
    }

    public function test_order_create_includes_selected_offer_refs(): void
    {
        $builder = new PiaNdcXmlBuilder;
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

    public function test_diagnostic_contact_normalizes_pakistan_phone_for_supplier_xml(): void
    {
        $builder = new PiaNdcXmlBuilder;
        $contact = $builder->buildDiagnosticContact([
            'email' => 'test@example.com',
            'phone' => '+92 03211234567',
        ]);

        $this->assertSame('92', $contact['phone_country']);
        $this->assertSame('3211234567', $contact['phone_number']);
        $this->assertSame('', $contact['phone_area']);

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
            $contact,
        );

        $this->assertStringContainsString('<CountryDialingCode>92</CountryDialingCode>', $xml);
        $this->assertStringContainsString('<PhoneNumber>3211234567</PhoneNumber>', $xml);
        $this->assertStringNotContainsString('<PhoneNumber>+92', $xml);
        $this->assertStringNotContainsString('<PhoneNumber>0321', $xml);
    }

    public function test_general_params_request_contains_party_block(): void
    {
        $builder = new PiaNdcXmlBuilder;
        $xml = $builder->buildGeneralParamsRequest([
            'agency_id' => 'SELENS',
            'agency_name' => 'NDC GATEWAY',
        ]);

        $this->assertStringContainsString('IATA_GeneralParamsRQ', $xml);
        $this->assertStringContainsString('<AgencyID>SELENS</AgencyID>', $xml);
    }

    public function test_airline_profile_request_contains_owner_code(): void
    {
        $builder = new PiaNdcXmlBuilder;
        $xml = $builder->buildAirlineProfileRequest([
            'agency_id' => 'SELENS',
            'agency_name' => 'NDC GATEWAY',
            'owner_code' => 'PK',
            'language_code' => 'EN',
        ], 'ISB');

        $this->assertStringContainsString('IATA_AirlineProfileRQ', $xml);
        $this->assertStringContainsString('<OwnerCode>PK</OwnerCode>', $xml);
        $this->assertStringContainsString('<IATA_LocationCode>ISB</IATA_LocationCode>', $xml);
    }

    public function test_offer_price_builds_selected_offer_with_pax_refs(): void
    {
        $builder = new PiaNdcXmlBuilder;
        $xml = $builder->buildOfferPriceRequest(
            [
                'agency_id' => 'SELENS',
                'agency_name' => 'NDC GATEWAY',
                'currency' => 'PKR',
                'language_code' => 'EN',
                'owner_code' => 'PK',
            ],
            [
                'shopping_response_ref_id' => 'b00fe7be-88f0-4de3-b455-28b5aa20f767',
                'offer_ref_id' => 'raw-hitit-offer-id',
                'owner_code' => 'PK',
                'offer_item_refs' => [
                    ['offer_item_ref_id' => 'OfferItem-13', 'pax_ref_id' => 'ADTPax-1'],
                ],
            ],
        );

        $this->assertStringContainsString('IATA_OfferPriceRQ', $xml);
        $this->assertStringContainsString('<OfferRefID>raw-hitit-offer-id</OfferRefID>', $xml);
        $this->assertStringContainsString('<OfferItemRefID>OfferItem-13</OfferItemRefID>', $xml);
        $this->assertStringContainsString('<PaxRefID>ADTPax-1</PaxRefID>', $xml);
        $this->assertStringContainsString('<OwnerCode>PK</OwnerCode>', $xml);
        $this->assertStringContainsString('b00fe7be-88f0-4de3-b455-28b5aa20f767', $xml);
        $this->assertStringContainsString('<PaxID>ADTPax-1</PaxID>', $xml);
        $this->assertStringContainsString('<PricedOffer>', $xml);
    }

    public function test_offer_price_shape_without_priced_offer_wrapper(): void
    {
        $builder = new PiaNdcXmlBuilder;
        $context = [
            'shopping_response_ref_id' => 'shop-ref',
            'offer_ref_id' => 'offer-1',
            'owner_code' => 'PK',
            'offer_item_ref_id' => 'OfferItem-1',
            'pax_ref_id' => 'ADTPax-1',
        ];
        $config = [
            'agency_id' => 'SELENS',
            'agency_name' => 'NDC GATEWAY',
            'currency' => 'PKR',
            'language_code' => 'EN',
            'owner_code' => 'PK',
        ];

        $xml = $builder->buildOfferPriceRequest($config, $context, 'selected_offer_without_priced_offer_wrapper');

        $this->assertStringContainsString('<SelectedOffer>', $xml);
        $this->assertStringNotContainsString('<PricedOffer>', $xml);
    }

    public function test_offer_price_shape_with_offer_id_tag(): void
    {
        $builder = new PiaNdcXmlBuilder;
        $xml = $builder->buildOfferPriceRequest(
            [
                'agency_id' => 'SELENS',
                'agency_name' => 'NDC GATEWAY',
                'currency' => 'PKR',
                'language_code' => 'EN',
                'owner_code' => 'PK',
            ],
            [
                'shopping_response_ref_id' => 'shop-ref',
                'offer_ref_id' => 'offer-1',
                'owner_code' => 'PK',
                'offer_item_ref_id' => 'OfferItem-1',
                'pax_ref_id' => 'ADTPax-1',
            ],
            'selected_offer_with_offer_id_tag',
        );

        $this->assertStringContainsString('<OfferID>offer-1</OfferID>', $xml);
        $this->assertStringNotContainsString('<OfferRefID>', $xml);
    }

    public function test_offer_price_shape_with_shopping_response_object(): void
    {
        $builder = new PiaNdcXmlBuilder;
        $xml = $builder->buildOfferPriceRequest(
            [
                'agency_id' => 'SELENS',
                'agency_name' => 'NDC GATEWAY',
                'currency' => 'PKR',
                'language_code' => 'EN',
                'owner_code' => 'PK',
            ],
            [
                'shopping_response_ref_id' => 'shop-ref',
                'offer_ref_id' => 'offer-1',
                'owner_code' => 'PK',
                'offer_item_ref_id' => 'OfferItem-1',
                'pax_ref_id' => 'ADTPax-1',
            ],
            'selected_offer_with_shopping_response_object',
        );

        $this->assertStringContainsString('<ShoppingResponse>', $xml);
        $this->assertStringContainsString('<ShoppingResponseRefID>shop-ref</ShoppingResponseRefID>', $xml);
        $this->assertStringNotContainsString('<PricedOffer>', $xml);
    }

    public function test_ticketing_order_change_uses_configured_mco_fields(): void
    {
        $builder = new PiaNdcXmlBuilder;
        $xml = $builder->buildTicketingOrderChangeRequest(
            [
                'agency_id' => 'SELENS',
                'agency_name' => 'NDC GATEWAY',
                'currency' => 'PKR',
                'language_code' => 'EN',
                'mco_invoice_number' => 'INV4000007708',
                'payment_type' => 'MCO',
            ],
            'ORDER1',
            'PK',
            ['amount' => 1000.00, 'currency' => 'PKR', 'ticket_id' => 'INV4000007708', 'type_code' => 'MCO'],
        );

        $this->assertStringContainsString('<DocType>MCO</DocType>', $xml);
        $this->assertStringContainsString('<TicketID>INV4000007708</TicketID>', $xml);
        $this->assertStringContainsString('<TypeCode>MCO</TypeCode>', $xml);
        $this->assertStringContainsString('CurCode="PKR"', $xml);
    }
}
