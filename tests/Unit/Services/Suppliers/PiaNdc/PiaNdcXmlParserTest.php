<?php

namespace Tests\Unit\Services\Suppliers\PiaNdc;

use App\Services\Suppliers\PiaNdc\PiaNdcXmlParser;
use Tests\TestCase;

class PiaNdcXmlParserTest extends TestCase
{
    public function test_parses_air_shopping_fixture(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doAirShopping_OW_res.xml'));
        $parser = new PiaNdcXmlParser;
        $parsed = $parser->parse($xml ?: '');

        $this->assertNull($parsed['soap_fault']);
        $this->assertSame(
            'b00fe7be-88f0-4de3-b455-28b5aa20f767',
            $parsed['parsed']['shopping_response_ref_id'] ?? null,
        );
        $this->assertCount(8, $parsed['parsed']['offers']);
        $this->assertNotSame([], $parsed['parsed']['offers'][0]['journey_refs'] ?? []);
    }

    public function test_parses_order_create_fixture_order_id(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCreate_OW_res.xml'));
        $parser = new PiaNdcXmlParser;
        $parsed = $parser->parse($xml ?: '');

        $this->assertSame('7UU0J3', $parsed['parsed']['order']['order_id'] ?? null);
    }

    public function test_parses_order_create_booking_refs(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCreate_OW_res.xml'));
        $parser = new PiaNdcXmlParser;
        $parsed = $parser->parse($xml ?: '');

        $refs = $parsed['parsed']['booking_refs'] ?? [];
        $this->assertNotEmpty($refs);
        $this->assertSame('7UU0J3', $refs[0]['booking_id'] ?? null);
        $this->assertSame('PK', $refs[0]['airline_desig_code'] ?? null);
    }

    public function test_parses_offer_price_success_fixture(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOfferPrice_OW_res.xml'));
        $parser = new PiaNdcXmlParser;
        $parsed = $parser->parse($xml ?: '');

        $this->assertNull($parsed['soap_fault']);
        $this->assertCount(1, $parsed['parsed']['priced_offers'] ?? []);
        $summary = $parsed['parsed']['offer_price_summary'] ?? [];
        $this->assertSame(44510.0, $summary['total'] ?? null);
        $this->assertSame('PKR', $summary['currency'] ?? null);
        $this->assertStringContainsString('MGJiNDJmY2Q', (string) ($summary['priced_offer_ref_id'] ?? ''));
    }

    public function test_parses_offer_price_fee_only_fixture(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOfferPrice_fee_only_res.xml'));
        $parser = new PiaNdcXmlParser;
        $parsed = $parser->parse($xml ?: '');

        $this->assertNull($parsed['soap_fault']);
        $summary = $parsed['parsed']['offer_price_summary'] ?? [];
        $this->assertSame(0.0, $summary['base'] ?? null);
        $this->assertSame(0.0, $summary['tax'] ?? null);
        $this->assertSame(1484.0, $summary['total'] ?? null);
        $this->assertSame(1484.0, $summary['fee_amount_total'] ?? null);
        $this->assertSame(['SF'], $summary['fee_descriptions'] ?? null);
        $this->assertCount(1, $summary['fees'] ?? []);
        $this->assertSame('SF', $summary['fees'][0]['desc_text'] ?? null);
    }

    public function test_parses_offer_price_error_fixture(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doOfferPrice_error_res.xml'));
        $parser = new PiaNdcXmlParser;
        $parsed = $parser->parse($xml ?: '');

        $this->assertSame('OFFER_EXPIRED', $parsed['errors'][0]['code'] ?? null);
        $this->assertStringContainsString('no longer available', (string) ($parsed['errors'][0]['message'] ?? ''));
    }

    public function test_parses_void_ticket_coupon_status_codes(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/pia-ndc/doVoidTicket_res.xml'));
        $parser = new PiaNdcXmlParser;
        $parsed = $parser->parse($xml ?: '');
        $tickets = $parsed['parsed']['ticket_doc_infos'] ?? [];

        $this->assertCount(1, $tickets);
        $this->assertSame('2142417439146', $tickets[0]['ticket_number'] ?? null);
        $this->assertSame(['V'], $tickets[0]['coupon_status_codes'] ?? null);
    }
}
