<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueOtaResponseNormalizer;
use App\Services\Suppliers\AirBlue\AirBlueOtaXmlParser;
use Tests\TestCase;

class AirBlueOtaResponseNormalizerTest extends AirBlueOfflineTestCase
{
    public function test_normalizes_search_offers_with_pa_carrier(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/airblue/ota_air_low_fare_search_success.xml'));
        $this->assertIsString($xml);

        $parsed = (new AirBlueOtaXmlParser)->parse($xml);
        $connection = new SupplierConnection([
            'provider' => SupplierProvider::Airblue,
            'environment' => SupplierEnvironment::Sandbox,
        ]);
        $connection->id = 7;

        $offers = (new AirBlueOtaResponseNormalizer)->normalizeSearchResponse($parsed, $connection, 'corr-1');

        $this->assertCount(1, $offers);
        $this->assertSame('PA', $offers[0]->airline_code);
        $this->assertSame('KHI', $offers[0]->origin);
        $this->assertSame('ISB', $offers[0]->destination);
        $this->assertSame(14500.0, $offers[0]->fare_breakdown->supplier_total);
        $this->assertSame('zapways_ota', $offers[0]->raw_payload['provider_context']['api_channel']);
        $context = $offers[0]->raw_payload['provider_context'];
        $this->assertNotEmpty($context['priced_itineraries'][0]['fare_breakdowns']);
        $this->assertSame(12000.0, $context['priced_itineraries'][0]['fare_breakdowns'][0]['base']);
        $this->assertNull($offers[0]->baggage->checked);
        $this->assertNull($offers[0]->baggage->cabin);
        $this->assertSame('economy', $offers[0]->cabin);
        $this->assertSame([], $offers[0]->branded_fares);
    }

    public function test_preserves_exact_faretype_rbd_cabin_and_fare_basis_for_variants(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/airblue/zapways/v3/ota_air_low_fare_search_v3_branded_variants.xml'));
        $this->assertIsString($xml);

        $parsed = (new AirBlueOtaXmlParser)->parse($xml);
        $connection = new SupplierConnection([
            'provider' => SupplierProvider::Airblue,
            'environment' => SupplierEnvironment::Sandbox,
            'credentials' => [
                'protocol_version' => '3.0',
                'environment' => 'test',
            ],
        ]);
        $connection->id = 22;

        $offers = (new AirBlueOtaResponseNormalizer)->normalizeSearchResponse($parsed, $connection, 'corr-branded');
        $this->assertCount(4, $offers);

        $pa201 = array_values(array_filter(
            $offers,
            static fn ($o) => (string) $o->flight_number === '201'
        ));
        $this->assertCount(3, $pa201);

        $byFareType = [];
        foreach ($pa201 as $offer) {
            $byFareType[(string) $offer->fare_family] = $offer;
        }

        ksort($byFareType);
        $this->assertSame(['EF', 'EV', 'EX'], array_keys($byFareType));

        $ev = $byFareType['EV'];
        $this->assertSame('PA', $ev->airline_code);
        $this->assertSame('ISB', $ev->origin);
        $this->assertSame('KHI', $ev->destination);
        $this->assertSame('economy', $ev->cabin);
        $this->assertSame(25000.0, $ev->fare_breakdown->supplier_total);
        $this->assertSame('PKR', $ev->fare_breakdown->currency);
        $this->assertSame([], $ev->branded_fares);
        $this->assertNull($ev->baggage->checked);
        $this->assertNull($ev->baggage->cabin);
        $this->assertFalse($ev->refundable);
        $this->assertSame('EV', $ev->raw_payload['provider_context']['fare_type_code'] ?? null);
        $this->assertSame('U', $ev->raw_payload['provider_context']['rbd'] ?? null);
        $this->assertSame('UEV1', $ev->raw_payload['provider_context']['fare_basis'] ?? null);
        $this->assertSame('U', $ev->raw_payload['customer_display_fields']['booking_class'] ?? null);
        $this->assertSame('EV', $ev->segments[0]['fare_type'] ?? null);
        $this->assertSame('U', $ev->segments[0]['rbd'] ?? null);
        $this->assertSame('Y', $ev->segments[0]['cabin_class'] ?? null);
        $this->assertSame('ISB', $ev->segments[0]['origin'] ?? null);
        $this->assertSame('KHI', $ev->segments[0]['destination'] ?? null);
        $this->assertSame('PA', $ev->segments[0]['airline_code'] ?? null);

        $this->assertSame(26000.0, $byFareType['EF']->fare_breakdown->supplier_total);
        $this->assertSame(27100.0, $byFareType['EX']->fare_breakdown->supplier_total);

        $pa207 = array_values(array_filter(
            $offers,
            static fn ($o) => (string) $o->flight_number === '207'
        ));
        $this->assertCount(1, $pa207);
        $this->assertSame('EF', $pa207[0]->fare_family);
        $this->assertSame('2026-12-29T19:30:00', $pa207[0]->departure_at);
    }

    public function test_normalize_cancel_response_uses_parsed_payload(): void
    {
        $normalizer = new AirBlueOtaResponseNormalizer;
        $ok = $normalizer->normalizeCancelResponse([
            'parsed' => ['seats' => [], 'items' => []],
            'errors' => [],
            'soap_fault' => null,
        ]);
        $this->assertSame('cancelled', $ok['cancellation_status']);
        $this->assertTrue($ok['held_seats_removed']);

        $failed = $normalizer->normalizeCancelResponse([
            'parsed' => [],
            'errors' => [['code' => 'cancel_denied', 'message' => 'denied']],
            'soap_fault' => null,
        ]);
        $this->assertSame('failed', $failed['cancellation_status']);
        $this->assertSame('cancel_denied', $failed['error_code']);
    }
}
