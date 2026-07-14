<?php

namespace Tests\Unit\Support\Sabre;

use App\Support\Sabre\SabrePassengerRecordsPayloadDigest;
use Tests\TestCase;

class SabrePassengerRecordsPayloadDigestTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function syntheticQrWire(bool $withValidatingCarrier = true, bool $withBrand = true): array
    {
        $pq = [
            'PassengerType' => [
                ['Code' => 'ADT', 'Quantity' => '1'],
            ],
        ];
        if ($withBrand) {
            $pq['Brand'] = [['content' => 'ECLASSIC']];
        }
        if ($withValidatingCarrier) {
            $optionalQualifiers = [
                'FlightQualifiers' => [
                    'VendorPrefs' => ['Airline' => ['Code' => 'QR']],
                ],
                'PricingQualifiers' => $pq,
            ];
        } else {
            $optionalQualifiers = [
                'PricingQualifiers' => $pq,
            ];
        }

        return [
            'CreatePassengerNameRecordRQ' => [
                'version' => '2.4.0',
                'haltOnAirPriceError' => true,
                'AirPrice' => [
                    [
                        'PriceRequestInformation' => [
                            'Retain' => true,
                            'OptionalQualifiers' => $optionalQualifiers,
                        ],
                    ],
                ],
                'AirBook' => [
                    'OriginDestinationInformation' => [
                        'FlightSegment' => [
                            [
                                'DepartureDateTime' => '2026-07-23T03:10:00',
                                'ArrivalDateTime' => '2026-07-23T06:40:00',
                                'FlightNumber' => '621',
                                'NumberInParty' => '1',
                                'ResBookDesigCode' => 'O',
                                'Status' => 'NN',
                                'MarketingAirline' => ['Code' => 'QR', 'FlightNumber' => '621'],
                                'OperatingAirline' => ['Code' => 'QR'],
                                'OriginLocation' => ['LocationCode' => 'LHE'],
                                'DestinationLocation' => ['LocationCode' => 'DOH'],
                            ],
                            [
                                'DepartureDateTime' => '2026-07-23T07:40:00',
                                'ArrivalDateTime' => '2026-07-23T10:10:00',
                                'FlightNumber' => '1190',
                                'NumberInParty' => '1',
                                'ResBookDesigCode' => 'O',
                                'Status' => 'NN',
                                'MarketingAirline' => ['Code' => 'QR', 'FlightNumber' => '1190'],
                                'OperatingAirline' => ['Code' => 'QR'],
                                'OriginLocation' => ['LocationCode' => 'DOH'],
                                'DestinationLocation' => ['LocationCode' => 'JED'],
                            ],
                        ],
                    ],
                ],
                'TravelItineraryAddInfo' => ['AgencyInfo' => ['Ticketing' => ['TicketType' => 'TL']]],
                'PostProcessing' => ['EndTransaction' => ['Source' => ['ReceivedFrom' => 'OTA']]],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function syntheticContextSegments(): array
    {
        return [
            [
                'index' => 0,
                'marketing_carrier' => 'QR',
                'flight_number' => '621',
                'origin' => 'LHE',
                'destination' => 'DOH',
                'booking_class' => 'O',
                'fare_basis_code' => 'OJPKP1RI',
                'departure_datetime' => '2026-07-23T03:10:00',
            ],
            [
                'index' => 1,
                'marketing_carrier' => 'QR',
                'flight_number' => '1190',
                'origin' => 'DOH',
                'destination' => 'JED',
                'booking_class' => 'O',
                'fare_basis_code' => 'OJPKP1RI',
                'departure_datetime' => '2026-07-23T07:40:00',
            ],
        ];
    }

    public function test_digest_extracts_airbook_segment_carrier_rbd_route_safely(): void
    {
        $wire = $this->syntheticQrWire();
        $digest = app(SabrePassengerRecordsPayloadDigest::class)->digest($wire, [
            'endpoint_path' => '/v2.4.0/passenger/records?mode=create',
            'payload_style' => 'iati_like_cpnr_v2_4_gds',
            'passenger_count' => 1,
            'selected_context_segments' => $this->syntheticContextSegments(),
            'api_draft' => ['validating_carrier' => 'QR', 'passengers' => [['type' => 'ADT']]],
            'validating_carrier' => 'QR',
            'brand_code' => 'ECLASSIC',
        ]);

        $this->assertTrue($digest['has_air_book']);
        $this->assertTrue($digest['has_air_price']);
        $this->assertFalse($digest['has_enhanced_air_book']);
        $this->assertCount(2, $digest['airbook_segment_digest']);
        $this->assertSame('QR', $digest['airbook_segment_digest'][0]['marketing_carrier']);
        $this->assertSame('O', $digest['airbook_segment_digest'][0]['booking_class']);
        $this->assertSame('LHE', $digest['airbook_segment_digest'][0]['origin']);
        $this->assertSame('DOH', $digest['airbook_segment_digest'][0]['destination']);
        $this->assertSame('JED', $digest['airbook_segment_digest'][1]['destination']);
    }

    public function test_digest_extracts_airprice_validating_carrier_safely(): void
    {
        $digest = app(SabrePassengerRecordsPayloadDigest::class)->digest(
            $this->syntheticQrWire(true, true),
            [
                'selected_context_segments' => $this->syntheticContextSegments(),
                'api_draft' => ['validating_carrier' => 'QR'],
                'validating_carrier' => 'QR',
            ],
        );

        $this->assertSame('QR', $digest['airprice_digest']['validating_carrier']);
        $this->assertTrue($digest['airprice_digest']['brand_present_on_wire']);
        $ptcCodes = $digest['airprice_digest']['type_codes'] ?? [];
        $this->assertIsArray($ptcCodes);
        $this->assertContains('ADT', $ptcCodes);
    }

    public function test_digest_flags_rbd_carrier_mismatch_and_missing_airprice_validating_carrier(): void
    {
        $wire = $this->syntheticQrWire(false, false);
        $wire['CreatePassengerNameRecordRQ']['AirBook']['OriginDestinationInformation']['FlightSegment'][0]['ResBookDesigCode'] = 'X';

        $digest = app(SabrePassengerRecordsPayloadDigest::class)->digest($wire, [
            'selected_context_segments' => $this->syntheticContextSegments(),
            'api_draft' => ['validating_carrier' => 'QR'],
            'validating_carrier' => 'QR',
        ]);

        $this->assertTrue($digest['no_fares_rbd_carrier_risk']);
        $this->assertContains('rbd_context_payload_mismatch', $digest['no_fares_rbd_carrier_risk_reasons']);
        $this->assertContains('airprice_missing_validating_carrier', $digest['no_fares_rbd_carrier_risk_reasons']);
        $this->assertContains('airprice_missing_brand_or_fare_qualifier', $digest['no_fares_rbd_carrier_risk_reasons']);
        $this->assertFalse($digest['context_comparison']['rbd_match']);
        $this->assertTrue($digest['hard_no_fares_rbd_carrier_risk']);
        $this->assertFalse($digest['airprice_validating_carrier_present']);
    }

    public function test_digest_legacy_revalidation_signal_is_warning_not_hard_risk(): void
    {
        $digest = app(SabrePassengerRecordsPayloadDigest::class)->digest(
            $this->syntheticQrWire(true, true),
            [
                'selected_context_segments' => $this->syntheticContextSegments(),
                'api_draft' => ['validating_carrier' => 'QR'],
                'validating_carrier' => 'QR',
                'brand_code' => 'ECLASSIC',
                'legacy_revalidation_signal_used' => true,
            ],
        );

        $this->assertFalse($digest['hard_no_fares_rbd_carrier_risk']);
        $this->assertFalse($digest['no_fares_rbd_carrier_risk']);
        $this->assertContains('legacy_revalidation_signal_used', $digest['warning_reasons']);
        $this->assertTrue($digest['airprice_validating_carrier_present']);
        $this->assertTrue($digest['context_comparison']['validating_carrier_match']);
    }

    public function test_digest_flags_brand_mismatch_as_hard_risk(): void
    {
        $wire = $this->syntheticQrWire(true, true);
        data_set(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.Brand',
            [['content' => 'ECONVENIEN']]
        );
        $digest = app(SabrePassengerRecordsPayloadDigest::class)->digest($wire, [
            'selected_context_segments' => $this->syntheticContextSegments(),
            'api_draft' => ['validating_carrier' => 'QR'],
            'validating_carrier' => 'QR',
            'brand_code' => 'ECLASSIC',
            'validated_offer_brand_code' => 'ECLASSIC',
        ]);

        $this->assertFalse($digest['brand_match']);
        $this->assertSame('brand_context_payload_mismatch', $digest['brand_mismatch_reason']);
        $this->assertContains('brand_context_payload_mismatch', $digest['hard_no_fares_rbd_carrier_risk_reasons']);
    }

    public function test_command_summary_from_digest_returns_slim_safe_fields(): void
    {
        $digest = app(SabrePassengerRecordsPayloadDigest::class)->digest(
            $this->syntheticQrWire(),
            [
                'endpoint_path' => '/v2.4.0/passenger/records?mode=create',
                'payload_style' => 'iati_like_cpnr_v2_4_gds',
                'selected_context_segments' => $this->syntheticContextSegments(),
                'api_draft' => ['validating_carrier' => 'QR'],
                'validating_carrier' => 'QR',
                'brand_code' => 'ECLASSIC',
            ],
        );

        $summary = app(SabrePassengerRecordsPayloadDigest::class)->commandSummaryFromDigest($digest);
        $this->assertTrue($summary['payload_digest_available']);
        $this->assertSame(2, $summary['airbook_segment_count']);
        $this->assertTrue($summary['airprice_present']);
        $this->assertTrue($summary['airprice_validating_carrier_present']);
        $this->assertSame('QR', $summary['airprice_validating_carrier']);
        $this->assertTrue($summary['validating_carrier_match']);
        $this->assertSame('pass', $summary['cpnr_schema_validation_status']);
        $this->assertTrue($summary['post_f9i_payload_digest_clean']);
    }

    public function test_digest_output_contains_no_raw_payload_or_pii_keys(): void
    {
        $digest = app(SabrePassengerRecordsPayloadDigest::class)->digest(
            $this->syntheticQrWire(),
            [
                'selected_context_segments' => $this->syntheticContextSegments(),
                'api_draft' => [
                    'validating_carrier' => 'QR',
                    'passengers' => [['email' => 'hidden@example.com', 'first_name' => 'Secret']],
                ],
            ],
        );

        $encoded = json_encode($digest);
        $this->assertStringNotContainsString('hidden@example.com', $encoded);
        $this->assertStringNotContainsString('Secret', $encoded);
        $this->assertStringNotContainsString('raw_payload', $encoded);
        $this->assertStringNotContainsString('request_body', $encoded);
    }
}
