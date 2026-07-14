<?php

namespace Tests\Unit;

use App\Services\Suppliers\Sabre\SabreBookingPayloadBuilder;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SabreIatiLikeCpnrV24GdsWireTest extends TestCase
{
    protected function minimalDraft(): array
    {
        return [
            '_valid' => true,
            'supplier_connection_id' => 1,
            '_sabre_pseudo_city_code' => 'AB12',
            'validating_carrier' => 'EK',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'carrier' => 'EK',
                    'flight_number' => '615',
                    'departure_at' => '2026-08-01T08:00:00',
                    'arrival_at' => '2026-08-01T14:00:00',
                    'booking_class' => 'K',
                ],
            ],
            'passengers' => [
                [
                    'type' => 'ADT',
                    'first_name' => 'Test',
                    'last_name' => 'Traveler',
                    'gender' => 'MALE',
                    'date_of_birth' => '1990-01-15',
                ],
            ],
            'contact' => [
                'email' => 'booker@example.com',
                'phone' => '3001234567',
            ],
            '_requires_passport_doc' => false,
            '_sabre_booking_context' => [],
        ];
    }

    public function test_iati_like_wire_version_endpoint_and_style_constants(): void
    {
        config(['suppliers.sabre.booking_payload_style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS]);

        $builder = app(SabreBookingPayloadBuilder::class);
        $this->assertSame(
            SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            $builder->resolvePassengerRecordsBookingPayloadStyle()
        );
        $this->assertSame(
            '/v2.4.0/passenger/records?mode=create',
            $builder->resolvePassengerRecordsCreateEndpointPath(SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS)
        );
    }

    public function test_iati_like_wire_has_cpnr_blocks_and_rbd(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($this->minimalDraft(), [])
        );
        $cpnr = $wire['CreatePassengerNameRecordRQ'] ?? [];
        $this->assertSame('2.4.0', $cpnr['version'] ?? null);
        $this->assertSame('AB12', $cpnr['targetCity'] ?? null);
        $this->assertTrue(($cpnr['haltOnAirPriceError'] ?? false) === true);
        $seg = $cpnr['AirBook']['OriginDestinationInformation']['FlightSegment'] ?? [];
        $first = is_array($seg) && array_is_list($seg) ? $seg[0] : $seg;
        $this->assertSame('K', $first['ResBookDesigCode'] ?? null);
        $this->assertTrue(isset($cpnr['AirPrice']) && is_array($cpnr['AirPrice']));
        $this->assertTrue(isset($cpnr['PostProcessing']['EndTransaction']['Source']['ReceivedFrom']));
        $this->assertSame('7TAW', data_get($cpnr, 'TravelItineraryAddInfo.AgencyInfo.Ticketing.TicketType'));
    }

    public function test_iati_like_wire_includes_airprice_validating_carrier_when_draft_has_vc(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $this->minimalDraft();
        $draft['validating_carrier'] = 'QR';
        $draft['_sabre_booking_context'] = [
            'brand_code' => 'ECONVENIEN',
        ];
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($draft, [])
        );
        $vcNode = data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.FlightQualifiers.VendorPrefs.Airline'
        );
        $this->assertIsArray($vcNode);
        $this->assertSame('QR', $vcNode['Code'] ?? null);
        $this->assertNull(data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.ValidatingCarrier'
        ));
        $brandNode = data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.Brand'
        );
        $this->assertNotEmpty($brandNode);
        $ptNode = data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.PassengerType'
        );
        $this->assertNotEmpty($ptNode);
    }

    public function test_iati_like_wire_maps_per_segment_fare_basis_from_booking_context(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $this->minimalDraft();
        $draft['validating_carrier'] = 'QR';
        $draft['segments'] = [
            [
                'origin' => 'LHE',
                'destination' => 'DOH',
                'carrier' => 'QR',
                'flight_number' => '614',
                'departure_at' => '2026-08-18T04:30:00',
                'arrival_at' => '2026-08-18T06:15:00',
                'booking_class' => 'Y',
                'fare_basis_code' => 'YLOW',
            ],
            [
                'origin' => 'DOH',
                'destination' => 'DXB',
                'carrier' => 'EK',
                'flight_number' => '842',
                'departure_at' => '2026-08-18T08:00:00',
                'arrival_at' => '2026-08-18T09:30:00',
                'booking_class' => 'Y',
                'fare_basis_code' => 'YLOW2',
            ],
        ];
        $draft['_sabre_booking_context'] = [
            'validating_carrier' => 'QR',
            'booking_classes_by_segment' => ['Y', 'Y'],
            'fare_basis_codes_by_segment' => ['YLOW', 'YLOW2'],
        ];
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($draft, [])
        );
        $commandPricing = data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.CommandPricing'
        );
        $this->assertIsArray($commandPricing);
        $this->assertCount(2, $commandPricing);
        $this->assertSame('YLOW', $commandPricing[0]['FareBasis']['Code'] ?? null);
        $this->assertSame('YLOW2', $commandPricing[1]['FareBasis']['Code'] ?? null);
        $this->assertSame('1', $commandPricing[0]['RPH'] ?? null);
        $this->assertSame('2', $commandPricing[1]['RPH'] ?? null);
        $this->assertArrayNotHasKey('Airline', $commandPricing[0] ?? []);
        $this->assertArrayNotHasKey('ResBookDesigCode', $commandPricing[0] ?? []);
        $segmentSelect = data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.ItineraryOptions.SegmentSelect'
        );
        $this->assertIsArray($segmentSelect);
        $this->assertCount(2, $segmentSelect);
        $this->assertSame('1', $segmentSelect[0]['RPH'] ?? null);
        $this->assertSame('1', $segmentSelect[0]['Number'] ?? null);
        $this->assertSame('2', $segmentSelect[1]['RPH'] ?? null);
        $this->assertSame('2', $segmentSelect[1]['Number'] ?? null);
        $pairing = $builder->inspectIatiV24CommandPricingSegmentSelectPairing($wire);
        $this->assertTrue($pairing['command_pricing_segmentselect_pairing_complete'] ?? false);
        $this->assertSame(['1', '2'], $pairing['command_pricing_rph_values'] ?? null);
        $this->assertSame(['1', '2'], $pairing['segment_select_rph_values'] ?? null);
        $schema = $builder->inspectIatiV24CommandPricingSchema($wire);
        $this->assertTrue($schema['command_pricing_schema_valid'] ?? false);
        $this->assertSame('RPH+FareBasis.Code', $schema['command_pricing_allowed_shape'] ?? null);
        $diag = $builder->summarizeTraditionalPnrWirePostBody(
            $wire,
            null,
            SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS
        );
        $this->assertTrue($diag['fare_basis_present'] ?? false);
        $this->assertTrue($diag['wire_airprice_has_fare_basis'] ?? false);
    }

    public function test_iati_like_diagnostics_and_missing_pcc_blocks_context(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $this->minimalDraft();
        unset($draft['_sabre_pseudo_city_code']);
        $draft['supplier_connection_id'] = 0;
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($draft, [])
        );
        $diag = $builder->summarizeTraditionalPnrWirePostBody(
            $wire,
            null,
            SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS
        );
        $this->assertTrue(($diag['is_iati_like_cpnr_style'] ?? false) === true);
        $this->assertSame('2.4.0', $diag['endpoint_version'] ?? null);
        $this->assertFalse($diag['wire_has_target_city'] ?? true);
        $this->assertFalse($diag['target_city_present'] ?? true);

        $draftNoRbd = $this->minimalDraft();
        $draftNoRbd['segments'][0]['booking_class'] = '';
        $wireNoRbd = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($draftNoRbd, [])
        );
        $diagNoRbd = $builder->summarizeTraditionalPnrWirePostBody(
            $wireNoRbd,
            null,
            SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS
        );
        $this->assertFalse($diagNoRbd['wire_flight_segment_has_res_book_desig_code'] ?? true);
    }

    public function test_brand_code_only_when_handoff_provides_safe_token(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $this->minimalDraft();
        $draft['_sabre_booking_context'] = ['brand_code' => 'ECONLIGHT'];
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($draft, [])
        );
        $this->assertSame('ECONLIGHT', data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.Brand.0.content'
        ));

        $draft['_sabre_booking_context'] = ['brand_code' => 'not-valid!'];
        $wire2 = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($draft, [])
        );
        $this->assertNull(data_get($wire2, 'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.Brand'));
    }

    public function test_iati_like_secure_flight_person_name_omits_document_holder(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($this->minimalDraft(), [])
        );
        $secure = data_get(
            $wire,
            'CreatePassengerNameRecordRQ.SpecialReqDetails.SpecialService.SpecialServiceInfo.SecureFlight'
        );
        $this->assertIsArray($secure);
        $this->assertNotEmpty($secure);
        $personName = $secure[0]['PersonName'] ?? null;
        $this->assertIsArray($personName);
        $this->assertArrayHasKey('GivenName', $personName);
        $this->assertArrayHasKey('Surname', $personName);
        $this->assertArrayHasKey('DateOfBirth', $personName);
        $this->assertArrayHasKey('Gender', $personName);
        $this->assertArrayHasKey('NameNumber', $personName);
        $this->assertArrayNotHasKey('DocumentHolder', $personName);
    }

    public function test_iati_like_advance_passenger_person_name_keeps_document_holder_when_passport_required(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $this->minimalDraft();
        $draft['_requires_passport_doc'] = true;
        $draft['passengers'][0]['passport_number'] = 'AB1234567';
        $draft['passengers'][0]['passport_issuing_country'] = 'PK';
        $draft['passengers'][0]['nationality'] = 'PK';
        $draft['passengers'][0]['passport_expiry_date'] = '2031-12-31';
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($draft, [])
        );
        $advancePn = data_get(
            $wire,
            'CreatePassengerNameRecordRQ.SpecialReqDetails.SpecialService.SpecialServiceInfo.AdvancePassenger.0.PersonName'
        );
        $this->assertIsArray($advancePn);
        $this->assertTrue($advancePn['DocumentHolder'] ?? false);
        $securePn = data_get(
            $wire,
            'CreatePassengerNameRecordRQ.SpecialReqDetails.SpecialService.SpecialServiceInfo.SecureFlight.0.PersonName'
        );
        $this->assertIsArray($securePn);
        $this->assertArrayNotHasKey('DocumentHolder', $securePn);
    }

    public function test_iati_like_wire_default_halt_on_status_omits_nn_on_halt_keeps_segment_nn(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($this->minimalDraft(), [])
        );
        $codes = $builder->extractHaltOnStatusCodesFromCpnr($wire['CreatePassengerNameRecordRQ'] ?? []);
        $this->assertNotContains('NN', $codes);
        $this->assertNotContains('WN', $codes);
        $this->assertContains('KK', $codes);
        $seg = data_get($wire, 'CreatePassengerNameRecordRQ.AirBook.OriginDestinationInformation.FlightSegment');
        $first = is_array($seg) && array_is_list($seg) ? $seg[0] : $seg;
        $this->assertSame('NN', $first['Status'] ?? null);
    }

    public function test_iati_like_wire_cert_allow_nn_diagnostic_omits_nn_and_wn(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $this->minimalDraft();
        $draft['_ota_cert_allow_nn_diagnostic'] = true;
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($draft, [])
        );
        $codes = $builder->extractHaltOnStatusCodesFromCpnr($wire['CreatePassengerNameRecordRQ'] ?? []);
        $this->assertNotContains('NN', $codes);
        $this->assertNotContains('WN', $codes);
        $this->assertContains('HL', $codes);
        $this->assertContains('UC', $codes);
        $this->assertContains('KK', $codes);
        $diag = $builder->summarizeTraditionalPnrWirePostBody(
            $wire,
            null,
            SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS
        );
        $this->assertTrue($diag['wire_halt_on_status_nn_omitted'] ?? false);
    }

    public function test_fingerprint_allow_nn_diagnostic_omits_nn_and_wn_from_halt_on_status(): void
    {
        Config::set('suppliers.sabre.traditional_cpnr_airbook_retry_redisplay', false);
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $this->minimalDraft();
        $draft['_ota_cert_allow_nn_diagnostic'] = true;
        $envelope = $builder->buildIatiLikeCpnrV24GdsWire($draft, []);
        $fingerprint = $builder->fingerprintPassengerRecordsFinalPostBody($envelope);

        $this->assertFalse($fingerprint['final_wire_contains_nn_halt']);
        $this->assertFalse($fingerprint['final_wire_contains_wn_halt']);
        $this->assertNotContains('NN', $fingerprint['final_wire_halt_on_status_codes']);
        $this->assertNotContains('WN', $fingerprint['final_wire_halt_on_status_codes']);
        $this->assertContains('NN', $fingerprint['final_wire_flight_segment_statuses']);
        $this->assertFalse($fingerprint['final_wire_retry_rebook_present']);
        $this->assertFalse($fingerprint['final_wire_airbook_redisplay_present']);
        $this->assertTrue($fingerprint['final_wire_post_processing_redisplay_present']);
    }

    public function test_fingerprint_default_omits_nn_halt_and_keeps_segment_nn_status(): void
    {
        Config::set('suppliers.sabre.traditional_cpnr_airbook_retry_redisplay', false);
        $builder = app(SabreBookingPayloadBuilder::class);
        $envelope = $builder->buildIatiLikeCpnrV24GdsWire($this->minimalDraft(), []);
        $fingerprint = $builder->fingerprintPassengerRecordsFinalPostBody($envelope);

        $this->assertFalse($fingerprint['final_wire_contains_nn_halt']);
        $this->assertFalse($fingerprint['final_wire_contains_wn_halt']);
        $this->assertContains('NN', $fingerprint['final_wire_flight_segment_statuses']);
        $this->assertFalse($fingerprint['final_wire_retry_rebook_present']);
        $this->assertFalse($fingerprint['final_wire_airbook_redisplay_present']);
        $this->assertTrue($fingerprint['final_wire_post_processing_redisplay_present']);
        $this->assertGreaterThan(0, $fingerprint['final_wire_halt_on_status_node_count']);
    }

    public function test_fingerprint_matches_preview_wire_diag_for_same_envelope(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $this->minimalDraft();
        $draft['_ota_cert_allow_nn_diagnostic'] = true;
        $envelope = $builder->buildIatiLikeCpnrV24GdsWire($draft, []);
        $stripped = $builder->stripOtaInternalKeysFromBookingWire($envelope);
        $wireDiag = $builder->summarizeTraditionalPnrWirePostBody(
            $stripped,
            null,
            SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS
        );
        $fingerprint = $builder->fingerprintPassengerRecordsFinalPostBody($envelope);

        $previewCodes = array_values((array) ($wireDiag['wire_halt_on_status_codes_sanitized'] ?? []));
        $finalCodes = array_values((array) ($fingerprint['final_wire_halt_on_status_codes'] ?? []));
        sort($previewCodes);
        sort($finalCodes);
        $this->assertSame($previewCodes, $finalCodes);
        $this->assertSame(
            $wireDiag['wire_airbook_has_retry_rebook'] ?? false,
            $fingerprint['final_wire_retry_rebook_present']
        );
        $this->assertSame(
            $wireDiag['wire_post_processing_has_redisplay_reservation'] ?? false,
            $fingerprint['final_wire_post_processing_redisplay_present']
        );
    }

    public function test_traditional_wire_omits_secure_flight_block(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($this->minimalDraft(), [])
        );
        $this->assertNull(data_get(
            $wire,
            'CreatePassengerNameRecordRQ.SpecialReqDetails.SpecialService.SpecialServiceInfo.SecureFlight'
        ));
    }

    public function test_iati_like_wire_matches_known_good_structural_contract(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($this->minimalDraft(), [])
        );
        $seg = data_get($wire, 'CreatePassengerNameRecordRQ.AirBook.OriginDestinationInformation.FlightSegment');
        $first = is_array($seg) && array_is_list($seg) ? $seg[0] : $seg;
        $this->assertIsArray($first);
        $this->assertArrayNotHasKey('MarriageGrp', $first);
        $this->assertArrayNotHasKey('OperatingAirline', $first);
        $this->assertArrayNotHasKey('ActionCode', $first);
        $this->assertSame('0615', $first['FlightNumber'] ?? null);
        $this->assertSame('0615', data_get($first, 'MarketingAirline.FlightNumber'));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', (string) ($first['DepartureDateTime'] ?? ''));
        $this->assertFalse(data_get($wire, 'CreatePassengerNameRecordRQ.AirBook.IgnoreAfter') !== null);
        $this->assertFalse(data_get($wire, 'CreatePassengerNameRecordRQ.PostProcessing.IgnoreAfter') !== null);
        $infant = data_get($wire, 'CreatePassengerNameRecordRQ.TravelItineraryAddInfo.CustomerInfo.PersonName.Infant');
        if ($infant === null) {
            $infant = data_get($wire, 'CreatePassengerNameRecordRQ.TravelItineraryAddInfo.CustomerInfo.PersonName.0.Infant');
        }
        $this->assertFalse($infant);
    }

    public function test_iati_v24_command_pricing_schema_rejects_invalid_row_keys(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = [
            'CreatePassengerNameRecordRQ' => [
                'AirPrice' => [[
                    'PriceRequestInformation' => [
                        'OptionalQualifiers' => [
                            'PricingQualifiers' => [
                                'CommandPricing' => [
                                    [
                                        'RPH' => '1',
                                        'FareBasis' => ['Code' => 'YLOW'],
                                        'Airline' => ['Code' => 'PK'],
                                        'ResBookDesigCode' => 'Y',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]],
            ],
        ];
        $schema = $builder->inspectIatiV24CommandPricingSchema($wire);
        $this->assertFalse($schema['command_pricing_schema_valid'] ?? true);
        $this->assertContains('Airline', $schema['command_pricing_rejected_keys'] ?? []);
        $this->assertContains('ResBookDesigCode', $schema['command_pricing_rejected_keys'] ?? []);
    }

    public function test_iati_v24_command_pricing_schema_allows_itinerary_options_segment_select(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = [
            'CreatePassengerNameRecordRQ' => [
                'AirPrice' => [[
                    'PriceRequestInformation' => [
                        'OptionalQualifiers' => [
                            'PricingQualifiers' => [
                                'ItineraryOptions' => [
                                    'SegmentSelect' => [
                                        ['RPH' => '1', 'Number' => '1'],
                                        ['RPH' => '2', 'Number' => '2'],
                                    ],
                                ],
                                'CommandPricing' => [
                                    ['RPH' => '1', 'FareBasis' => ['Code' => 'YLOW']],
                                    ['RPH' => '2', 'FareBasis' => ['Code' => 'YLOW2']],
                                ],
                            ],
                        ],
                    ],
                ]],
            ],
        ];
        $schema = $builder->inspectIatiV24CommandPricingSchema($wire);
        $this->assertTrue($schema['command_pricing_schema_valid'] ?? false);
        $pairing = $builder->inspectIatiV24CommandPricingSegmentSelectPairing($wire);
        $this->assertTrue($pairing['command_pricing_segmentselect_pairing_complete'] ?? false);
    }

    public function test_iati_v24_pairing_incomplete_when_segment_select_missing(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = [
            'CreatePassengerNameRecordRQ' => [
                'AirPrice' => [[
                    'PriceRequestInformation' => [
                        'OptionalQualifiers' => [
                            'PricingQualifiers' => [
                                'CommandPricing' => [
                                    ['RPH' => '1', 'FareBasis' => ['Code' => 'YLOW']],
                                    ['RPH' => '2', 'FareBasis' => ['Code' => 'YLOW2']],
                                ],
                            ],
                        ],
                    ],
                ]],
            ],
        ];
        $pairing = $builder->inspectIatiV24CommandPricingSegmentSelectPairing($wire);
        $this->assertFalse($pairing['command_pricing_segmentselect_pairing_complete'] ?? true);
        $this->assertSame(['1', '2'], $pairing['command_pricing_segmentselect_missing_rph'] ?? null);
    }

    public function test_iati_v24_command_pricing_schema_rejects_invalid_itinerary_options_keys(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = [
            'CreatePassengerNameRecordRQ' => [
                'AirPrice' => [[
                    'PriceRequestInformation' => [
                        'OptionalQualifiers' => [
                            'PricingQualifiers' => [
                                'ItineraryOptions' => ['UnexpectedKey' => 'x'],
                                'CommandPricing' => [
                                    ['RPH' => '1', 'FareBasis' => ['Code' => 'YLOW']],
                                ],
                            ],
                        ],
                    ],
                ]],
            ],
        ];
        $schema = $builder->inspectIatiV24CommandPricingSchema($wire);
        $this->assertFalse($schema['command_pricing_schema_valid'] ?? true);
        $this->assertContains('ItineraryOptions.UnexpectedKey', $schema['command_pricing_rejected_keys'] ?? []);
    }

    public function test_iati_v24_brand_with_segment_select_emits_rph_aligned_rows(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $this->minimalDraft();
        $draft['validating_carrier'] = 'QR';
        $draft['segments'] = [
            [
                'origin' => 'LHE',
                'destination' => 'DOH',
                'carrier' => 'PK',
                'flight_number' => '751',
                'departure_at' => '2026-08-18T04:30:00',
                'booking_class' => 'Y',
                'fare_basis_code' => 'YLOW',
            ],
            [
                'origin' => 'DOH',
                'destination' => 'DXB',
                'carrier' => 'EK',
                'flight_number' => '842',
                'departure_at' => '2026-08-18T08:00:00',
                'booking_class' => 'Y',
                'fare_basis_code' => 'YLOW2',
            ],
        ];
        $draft['_sabre_booking_context'] = [
            'validating_carrier' => 'QR',
            'brand_code' => 'ECONLIGHT',
            'booking_classes_by_segment' => ['Y', 'Y'],
            'fare_basis_codes_by_segment' => ['YLOW', 'YLOW2'],
        ];
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($draft, [])
        );
        $brand = data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.Brand'
        );
        $this->assertIsArray($brand);
        $this->assertCount(2, $brand);
        $this->assertSame(1, $brand[0]['RPH'] ?? null);
        $this->assertSame('ECONLIGHT', $brand[0]['content'] ?? null);
        $this->assertSame(2, $brand[1]['RPH'] ?? null);
        $this->assertSame('ECONLIGHT', $brand[1]['content'] ?? null);

        $brandDiag = $builder->inspectIatiV24BrandSegmentSelectPairing($wire, 'ECONLIGHT');
        $this->assertTrue($brandDiag['brand_present'] ?? false);
        $this->assertTrue($brandDiag['brand_schema_valid'] ?? false);
        $this->assertTrue($brandDiag['brand_rph_schema_valid'] ?? false);
        $this->assertSame('integer', $brandDiag['brand_rph_type'] ?? null);
        $this->assertTrue($brandDiag['brand_segmentselect_pairing_complete'] ?? false);
        $this->assertTrue($brandDiag['brand_segmentselect_pairing_values_match_normalized'] ?? false);
        $this->assertSame([1, 2], $brandDiag['brand_rph_values_raw'] ?? null);
        $this->assertSame(['1', '2'], $brandDiag['brand_rph_values_normalized'] ?? null);
        $this->assertSame('object_rph_integer_content', $brandDiag['brand_wire_shape'] ?? null);
        $this->assertFalse($brandDiag['brand_omitted_for_mixed_v24_segmentselect'] ?? true);
    }

    public function test_iati_v24_brand_without_rph_fails_pairing_when_segment_select_present(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = [
            'CreatePassengerNameRecordRQ' => [
                'AirPrice' => [[
                    'PriceRequestInformation' => [
                        'OptionalQualifiers' => [
                            'PricingQualifiers' => [
                                'Brand' => [['content' => 'ECONLIGHT']],
                                'ItineraryOptions' => [
                                    'SegmentSelect' => [
                                        ['RPH' => '1', 'Number' => '1'],
                                        ['RPH' => '2', 'Number' => '2'],
                                    ],
                                ],
                                'CommandPricing' => [
                                    ['RPH' => '1', 'FareBasis' => ['Code' => 'YLOW']],
                                    ['RPH' => '2', 'FareBasis' => ['Code' => 'YLOW2']],
                                ],
                            ],
                        ],
                    ],
                ]],
            ],
        ];
        $brandDiag = $builder->inspectIatiV24BrandSegmentSelectPairing($wire, 'ECONLIGHT');
        $this->assertTrue($brandDiag['brand_segmentselect_pairing_required'] ?? false);
        $this->assertFalse($brandDiag['brand_segmentselect_pairing_complete'] ?? true);
        $this->assertTrue($brandDiag['brand_rph_schema_valid'] ?? false);
        $this->assertFalse($brandDiag['brand_schema_valid'] ?? true);
        $this->assertNull($brandDiag['brand_rph_type'] ?? null);
        $this->assertSame(['1', '2'], $brandDiag['brand_segmentselect_missing_rph'] ?? null);
    }

    public function test_iati_v24_string_brand_rph_fails_schema_when_segment_select_present(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = [
            'CreatePassengerNameRecordRQ' => [
                'AirPrice' => [[
                    'PriceRequestInformation' => [
                        'OptionalQualifiers' => [
                            'PricingQualifiers' => [
                                'Brand' => [
                                    ['RPH' => '1', 'content' => 'ECONLIGHT'],
                                    ['RPH' => '2', 'content' => 'ECONLIGHT'],
                                ],
                                'ItineraryOptions' => [
                                    'SegmentSelect' => [
                                        ['RPH' => '1', 'Number' => '1'],
                                        ['RPH' => '2', 'Number' => '2'],
                                    ],
                                ],
                                'CommandPricing' => [
                                    ['RPH' => '1', 'FareBasis' => ['Code' => 'YLOW']],
                                    ['RPH' => '2', 'FareBasis' => ['Code' => 'YLOW2']],
                                ],
                            ],
                        ],
                    ],
                ]],
            ],
        ];
        $brandDiag = $builder->inspectIatiV24BrandSegmentSelectPairing($wire, 'ECONLIGHT');
        $this->assertFalse($brandDiag['brand_rph_schema_valid'] ?? true);
        $this->assertFalse($brandDiag['brand_schema_valid'] ?? true);
        $this->assertSame('string', $brandDiag['brand_rph_type'] ?? null);
        $this->assertSame(
            SabreBookingPayloadBuilder::AIRPRICE_BRAND_RPH_REJECTED_POINTER,
            $brandDiag['brand_schema_rejected_pointer'] ?? null,
        );
    }

    public function test_iati_v24_schema_validator_rejects_string_brand_rph(): void
    {
        $validator = app(\App\Support\Sabre\SabreCpnrIatiWireSchemaValidator::class);
        $result = $validator->validateCpnrEnvelope([
            'CreatePassengerNameRecordRQ' => [
                'AirPrice' => [[
                    'PriceRequestInformation' => [
                        'OptionalQualifiers' => [
                            'PricingQualifiers' => [
                                'PassengerType' => [['Code' => 'ADT', 'Quantity' => '1']],
                                'Brand' => [['RPH' => '1', 'content' => 'ECONLIGHT']],
                            ],
                            'FlightQualifiers' => [
                                'VendorPrefs' => ['Airline' => ['Code' => 'EK']],
                            ],
                        ],
                    ],
                ]],
            ],
        ]);
        $this->assertTrue($result['cpnr_schema_validation_failed'] ?? false);
        $this->assertStringContainsString('/Brand/0/RPH', (string) ($result['cpnr_schema_validation_pointer'] ?? ''));
    }
}
