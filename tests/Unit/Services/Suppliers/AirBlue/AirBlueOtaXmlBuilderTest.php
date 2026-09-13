<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Data\FlightSearchRequestData;
use App\Services\Suppliers\AirBlue\AirBlueOtaXmlBuilder;
use Tests\TestCase;

class AirBlueOtaXmlBuilderTest extends TestCase
{
    public function test_air_low_fare_search_contains_pos_and_route(): void
    {
        $builder = new AirBlueOtaXmlBuilder;
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
            'client_id' => 'CLIENT',
            'client_key' => 'KEY',
            'agent_type' => '5',
            'agent_id' => 'AGENT',
            'agent_password' => 'secret',
            'service_target' => 'Production',
            'service_version' => '1.04',
        ];

        $xml = $builder->buildAirLowFareSearchRequest($request, $config);

        $this->assertStringContainsString('AirLowFareSearch', $xml);
        $this->assertStringContainsString('ERSP_UserID="CLIENT/KEY"', $xml);
        $this->assertStringContainsString('LocationCode="KHI"', $xml);
        $this->assertStringContainsString('LocationCode="ISB"', $xml);
        $this->assertStringContainsString('Version="1.04"', $xml);
        $this->assertStringContainsString('Target="Production"', $xml);
    }

    public function test_read_request_contains_pnr_and_instance(): void
    {
        $builder = new AirBlueOtaXmlBuilder;
        $config = [
            'client_id' => 'CLIENT',
            'client_key' => 'KEY',
            'agent_type' => '5',
            'agent_id' => 'AGENT',
            'agent_password' => 'secret',
            'service_target' => 'Test',
            'service_version' => '1.04',
        ];

        $xml = $builder->buildReadRequest($config, 'ABC123', 'INST1');

        $this->assertStringContainsString('OTA_ReadRQ', $xml);
        $this->assertStringContainsString('ID="ABC123"', $xml);
        $this->assertStringContainsString('Instance="INST1"', $xml);
    }
}
