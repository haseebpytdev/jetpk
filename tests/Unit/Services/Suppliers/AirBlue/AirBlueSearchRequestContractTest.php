<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Data\FlightSearchRequestData;
use App\Services\Suppliers\AirBlue\AirBlueOtaXmlBuilder;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueValidationException;

class AirBlueSearchRequestContractTest extends AirBlueOfflineTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function baseConfig(): array
    {
        return [
            'client_id' => 'CLIENT',
            'client_key' => 'KEY',
            'agent_type' => '5',
            'agent_id' => 'AGENT',
            'agent_password' => 'secret',
            'service_target' => 'Test',
            'service_version' => '1.04',
            'protocol_version' => '2.0',
            'namespace' => 'http://www.opentravel.org/OTA/2003/05',
        ];
    }

    public function test_builds_one_way_and_return_with_pax_mix(): void
    {
        $builder = new AirBlueOtaXmlBuilder;
        $config = $this->baseConfig();

        $ow = $builder->buildAirLowFareSearchRequest(new FlightSearchRequestData(
            origin: 'ISB',
            destination: 'DXB',
            departure_date: '2026-11-01',
            adults: 1,
            children: 1,
            infants: 1,
        ), $config);

        $this->assertStringContainsString('LocationCode="ISB"', $ow);
        $this->assertStringContainsString('LocationCode="DXB"', $ow);
        $this->assertStringContainsString('Code="ADT" Quantity="1"', $ow);
        $this->assertStringContainsString('Code="CHD" Quantity="1"', $ow);
        $this->assertStringContainsString('Code="INF" Quantity="1"', $ow);
        $this->assertSame(1, substr_count($ow, 'OriginDestinationInformation RPH='));

        $rt = $builder->buildAirLowFareSearchRequest(new FlightSearchRequestData(
            origin: 'ISB',
            destination: 'DXB',
            departure_date: '2026-11-01',
            return_date: '2026-11-10',
            trip_type: 'return',
            adults: 2,
        ), $config);

        $this->assertSame(2, substr_count($rt, 'OriginDestinationInformation RPH='));
        $this->assertStringContainsString('2026-11-10T00:00:00', $rt);
    }

    public function test_multicity_uses_explicit_segments(): void
    {
        $xml = (new AirBlueOtaXmlBuilder)->buildAirLowFareSearchRequest(new FlightSearchRequestData(
            origin: 'ISB',
            destination: 'KHI',
            departure_date: '2026-11-01',
            trip_type: 'multi_city',
            adults: 1,
            segments: [
                ['origin' => 'ISB', 'destination' => 'KHI', 'departure_date' => '2026-11-01'],
                ['origin' => 'KHI', 'destination' => 'DXB', 'departure_date' => '2026-11-05'],
                ['origin' => 'DXB', 'destination' => 'ISB', 'departure_date' => '2026-11-12'],
            ],
        ), $this->baseConfig());

        $this->assertSame(3, substr_count($xml, 'OriginDestinationInformation RPH='));
        $this->assertStringContainsString('LocationCode="KHI"', $xml);
        $this->assertStringContainsString('LocationCode="DXB"', $xml);
    }

    public function test_rejects_infants_exceeding_adults(): void
    {
        $this->expectException(AirBlueValidationException::class);
        (new AirBlueOtaXmlBuilder)->buildAirLowFareSearchRequest(new FlightSearchRequestData(
            origin: 'ISB',
            destination: 'DXB',
            departure_date: '2026-11-01',
            adults: 1,
            infants: 2,
        ), $this->baseConfig());
    }
}
