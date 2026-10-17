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
