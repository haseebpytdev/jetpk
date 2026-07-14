<?php

namespace Tests\Unit\Services\Suppliers\PiaNdc;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\PiaNdcResponseNormalizer;
use App\Services\Suppliers\PiaNdc\PiaNdcXmlParser;
use Tests\TestCase;

class PiaNdcAirShoppingNormalizerTest extends TestCase
{
    public function test_khi_isb_fixture_parses_and_normalizes_offers(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doAirShopping_OW_res.xml'));
        $parser = new PiaNdcXmlParser;
        $parsed = $parser->parse($xml ?: '');

        $this->assertNull($parsed['soap_fault']);
        $this->assertCount(8, $parsed['parsed']['offers']);

        $connection = $this->piaConnection();
        $normalized = app(PiaNdcResponseNormalizer::class)->normalizeSearchResponse($parsed, $connection, 'test-corr');

        $this->assertCount(8, $normalized);
        $this->assertSame('KHI', $normalized[0]->origin);
        $this->assertSame('ISB', $normalized[0]->destination);
    }

    public function test_khi_isb_pk308_direct_offer_details(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doAirShopping_OW_res.xml'));
        $parser = new PiaNdcXmlParser;
        $parsed = $parser->parse($xml ?: '');
        $connection = $this->piaConnection();
        $normalized = app(PiaNdcResponseNormalizer::class)->normalizeSearchResponse($parsed, $connection, 'test-corr');

        $pk308 = collect($normalized)->first(
            fn ($offer) => str_contains((string) $offer->flight_number, '308'),
        );
        $this->assertNotNull($pk308);
        $this->assertSame(0, $pk308->stops);
        $this->assertCount(1, $pk308->segments);
        $this->assertSame(44510.0, $pk308->fare_breakdown->supplier_total);
        $this->assertSame('PKR', $pk308->fare_breakdown->currency);
        $this->assertSame('ECO LIGHT', $pk308->fare_family);
        $this->assertSame('20 KG', $pk308->baggage->checked);
    }

    public function test_khi_isb_connecting_offer_preserves_segments_in_order(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doAirShopping_OW_res.xml'));
        $parser = new PiaNdcXmlParser;
        $parsed = $parser->parse($xml ?: '');
        $connection = $this->piaConnection();
        $normalized = app(PiaNdcResponseNormalizer::class)->normalizeSearchResponse($parsed, $connection, 'test-corr');

        $connecting = collect($normalized)->first(
            fn ($offer) => $offer->stops === 1
                && $offer->origin === 'KHI'
                && $offer->destination === 'ISB'
                && count($offer->segments) === 2,
        );
        $this->assertNotNull($connecting);
        $this->assertSame('KHI', $connecting->segments[0]['origin']);
        $this->assertSame('UET', $connecting->segments[0]['destination']);
        $this->assertSame('UET', $connecting->segments[1]['origin']);
        $this->assertSame('ISB', $connecting->segments[1]['destination']);
    }

    public function test_isb_dxb_fixture_parses_three_offers(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doAirShopping_ISB_DXB_res.xml'));
        $parser = new PiaNdcXmlParser;
        $parsed = $parser->parse($xml ?: '');

        $this->assertCount(3, $parsed['parsed']['offers']);

        $connection = $this->piaConnection();
        $normalized = app(PiaNdcResponseNormalizer::class)->normalizeSearchResponse($parsed, $connection, 'test-corr');

        $this->assertCount(3, $normalized);
        $pk233 = collect($normalized)->first(
            fn ($offer) => str_contains((string) $offer->flight_number, '233'),
        );
        $this->assertNotNull($pk233);
        $this->assertSame(95787.0, $pk233->fare_breakdown->supplier_total);
        $this->assertSame('PKR', $pk233->fare_breakdown->currency);
        $this->assertSame('ECO LIGHT', $pk233->fare_family);
    }

    public function test_normalized_offer_preserves_provider_context_for_offer_price(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doAirShopping_OW_res.xml'));
        $parser = new PiaNdcXmlParser;
        $parsed = $parser->parse($xml ?: '');
        $connection = $this->piaConnection();
        $normalized = app(PiaNdcResponseNormalizer::class)->normalizeSearchResponse($parsed, $connection, 'test-corr');

        $first = $normalized[0];
        $context = is_array($first->raw_payload['provider_context'] ?? null)
            ? $first->raw_payload['provider_context']
            : [];

        $this->assertNotSame('', $context['shopping_response_ref_id'] ?? '');
        $this->assertSame('b00fe7be-88f0-4de3-b455-28b5aa20f767', $context['shopping_response_ref_id']);
        $this->assertSame($parsed['parsed']['offers'][0]['offer_id'], $context['offer_ref_id']);
        $this->assertSame('OfferItem-1', $context['offer_item_ref_id']);
        $this->assertStringStartsWith('pia-ndc-', $first->offer_id);
        $this->assertSame('test-corr', $context['search_correlation_id']);
        $this->assertNotSame([], $context['offer_item_refs'] ?? []);
        $this->assertNotSame([], $context['pax_journey_ref_ids'] ?? []);
        $this->assertNotSame([], $context['pax_segment_ref_ids'] ?? []);
    }

    public function test_parser_counts_journey_overview_refs(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doAirShopping_OW_res.xml'));
        $parser = new PiaNdcXmlParser;
        $parsed = $parser->parse($xml ?: '');

        $this->assertSame(['KHI-ISB-J3'], $parsed['parsed']['offers'][2]['journey_refs'] ?? null);
        $this->assertSame('LOW1', $parsed['parsed']['offers'][2]['offer_items'][0]['fare_basis'] ?? null);
        $this->assertSame('L', $parsed['parsed']['offers'][2]['offer_items'][0]['rbd'] ?? null);
    }

    public function test_normalizes_offer_price_response_provider_context(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOfferPrice_OW_res.xml'));
        $parser = new PiaNdcXmlParser;
        $parsed = $parser->parse($xml ?: '');
        $sourceContext = [
            'shopping_response_ref_id' => 'b00fe7be-88f0-4de3-b455-28b5aa20f767',
            'offer_ref_id' => 'raw-hitit-offer-id',
            'offer_item_ref_id' => 'OfferItem-13',
            'pax_ref_id' => 'ADTPax-1',
            'owner_code' => 'PK',
        ];

        $normalized = app(PiaNdcResponseNormalizer::class)->normalizeOfferPriceResponse($parsed, $sourceContext, 44510.0);

        $this->assertSame(1, $normalized['priced_offer_count']);
        $this->assertSame(44510.0, $normalized['total_amount']);
        $this->assertSame(44510.0, $normalized['offer_price_total']);
        $this->assertSame(44510.0, $normalized['air_shopping_total']);
        $this->assertTrue($normalized['fare_comparison_available']);
        $this->assertSame(0.0, $normalized['fare_difference']);
        $this->assertSame('PKR', $normalized['currency']);
        $this->assertTrue($normalized['commercially_valid_price']);
        $this->assertFalse($normalized['zero_price']);
        $this->assertFalse($normalized['fee_only_price']);
        $this->assertFalse($normalized['partial_price']);
        $context = $normalized['provider_context'];
        $this->assertSame('b00fe7be-88f0-4de3-b455-28b5aa20f767', $context['shopping_response_ref_id']);
        $this->assertSame('raw-hitit-offer-id', $context['offer_ref_id']);
        $this->assertStringContainsString('MGJiNDJmY2Q', (string) ($context['priced_offer_ref_id'] ?? ''));
        $this->assertSame('OfferItem-13', $context['offer_item_ref_id']);
    }

    public function test_normalizes_zero_price_offer_price_as_not_commercially_valid(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOfferPrice_zero_res.xml'));
        $parser = new PiaNdcXmlParser;
        $parsed = $parser->parse($xml ?: '');

        $normalized = app(PiaNdcResponseNormalizer::class)->normalizeOfferPriceResponse($parsed, [
            'shopping_response_ref_id' => 'shop-ref',
            'offer_ref_id' => 'offer-1',
            'owner_code' => 'PK',
        ]);

        $this->assertTrue($normalized['raw_priced_offer_present']);
        $this->assertTrue($normalized['zero_price']);
        $this->assertFalse($normalized['commercially_valid_price']);
        $this->assertSame(0.0, $normalized['total_amount']);
        $this->assertNotEmpty($normalized['provider_warnings']);
        $warningMessages = array_column($normalized['provider_warnings'], 'message');
        $this->assertContains('OfferPrice returned zero total amount.', $warningMessages);
    }

    public function test_normalizes_fee_only_offer_price_as_partial_not_commercially_valid(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOfferPrice_fee_only_res.xml'));
        $parser = new PiaNdcXmlParser;
        $parsed = $parser->parse($xml ?: '');

        $normalized = app(PiaNdcResponseNormalizer::class)->normalizeOfferPriceResponse($parsed, [
            'shopping_response_ref_id' => 'shop-ref',
            'offer_ref_id' => 'offer-1',
            'owner_code' => 'PK',
        ], 31131.0);

        $this->assertTrue($normalized['raw_priced_offer_present']);
        $this->assertFalse($normalized['zero_price']);
        $this->assertTrue($normalized['fee_only_price']);
        $this->assertTrue($normalized['partial_price']);
        $this->assertFalse($normalized['commercially_valid_price']);
        $this->assertSame(1484.0, $normalized['offer_price_total']);
        $this->assertSame(1484.0, $normalized['fee_amount_total']);
        $this->assertSame(['SF'], $normalized['fee_descriptions']);
        $this->assertSame(31131.0, $normalized['air_shopping_total']);
        $warningMessages = array_column($normalized['provider_warnings'], 'message');
        $this->assertContains('OfferPrice returned fee-only amount, not full fare.', $warningMessages);
    }

    public function test_full_fare_outside_air_shopping_tolerance_is_not_commercially_valid(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOfferPrice_OW_res.xml'));
        $parser = new PiaNdcXmlParser;
        $parsed = $parser->parse($xml ?: '');

        $normalized = app(PiaNdcResponseNormalizer::class)->normalizeOfferPriceResponse($parsed, [], 31131.0);

        $this->assertFalse($normalized['commercially_valid_price']);
        $this->assertTrue($normalized['fare_comparison_available']);
        $this->assertSame(44510.0, $normalized['offer_price_total']);
        $this->assertSame(31131.0, $normalized['air_shopping_total']);
    }

    public function test_normalizes_order_create_response_booking_reference_fields(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCreate_OW_res.xml'));
        $parser = new PiaNdcXmlParser;
        $parsed = $parser->parse($xml ?: '');

        $normalized = app(PiaNdcResponseNormalizer::class)->normalizeBookingResponse($parsed, [
            'offer_ref_id' => 'source-offer',
        ]);

        $this->assertSame('7UU0J3', $normalized['pnr']);
        $this->assertSame('7UU0J3', $normalized['booking_reference']);
        $this->assertSame('PK/7UU0J3', $normalized['airline_locator']);
        $this->assertSame('OPENED', $normalized['order_status']);
    }

    private function piaConnection(): SupplierConnection
    {
        $connection = new SupplierConnection([
            'id' => 19,
            'provider' => SupplierProvider::PiaNdc,
            'is_active' => true,
        ]);
        $connection->exists = true;

        return $connection;
    }
}
