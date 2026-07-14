<?php

namespace Tests\Unit;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreBookingPayloadBuilder;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use Tests\TestCase;

class SabreIatiLikeCpnrStyleSelectionTest extends TestCase
{
    protected function minimalOffer(): array
    {
        return [
            'id' => 'offer-test-1',
            'supplier_connection_id' => 1,
            'validating_carrier' => 'EK',
            'raw_payload' => ['distribution_model' => 'gds'],
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
        ];
    }

    protected function minimalDraft(): array
    {
        return [
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
            'contact' => ['email' => 'booker@example.com', 'phone' => '3001234567'],
        ];
    }

    protected function sabreConnection(): SupplierConnection
    {
        $connection = new SupplierConnection;
        $connection->id = 1;
        $connection->provider = SupplierProvider::Sabre;

        return $connection;
    }

    protected function certifiedRouteSelection(): array
    {
        return [
            'category' => SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_DIRECT_SAME_CARRIER,
            'route_status' => SabreCertifiedRouteSelector::STATUS_CERTIFIED,
            'live_booking_allowed' => true,
            'endpoint_path' => SabreCertifiedRouteSelector::ENDPOINT_PASSENGER_RECORDS_V25_CREATE,
            'payload_style' => SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
        ];
    }

    public function test_does_not_select_iati_when_config_unset_and_certified_flag_off(): void
    {
        config([
            'suppliers.sabre.booking_payload_style' => null,
            'suppliers.sabre.cpnr_iati_style_certified_gds_enabled' => false,
        ]);

        $service = app(SabreBookingService::class);
        $decision = $service->decidePassengerRecordsPayloadStyle(
            $this->minimalOffer(),
            $this->minimalDraft(),
            $this->sabreConnection(),
            $this->certifiedRouteSelection(),
        );

        $this->assertFalse($decision['iati_like_selection_considered']);
        $this->assertFalse($decision['iati_like_selected']);
        $this->assertSame(SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1, $decision['selected_style']);
        $this->assertStringContainsString('v2.5.0', (string) $decision['selected_endpoint_path']);
    }

    public function test_selects_iati_when_config_forces_and_context_complete(): void
    {
        config([
            'suppliers.sabre.booking_payload_style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            'suppliers.sabre.cpnr_iati_style_certified_gds_enabled' => false,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_mode' => 'pnr_only',
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
        ]);

        $service = app(SabreBookingService::class);
        $decision = $service->decidePassengerRecordsPayloadStyle(
            $this->minimalOffer(),
            $this->minimalDraft(),
            $this->sabreConnection(),
            null,
        );

        $this->assertTrue($decision['iati_like_selection_considered']);
        $this->assertTrue($decision['iati_like_eligible']);
        $this->assertTrue($decision['iati_like_selected']);
        $this->assertSame(SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS, $decision['selected_style']);
        $this->assertStringContainsString('v2.4.0', (string) $decision['selected_endpoint_path']);
        $this->assertFalse($decision['manual_review_required']);
    }

    public function test_does_not_select_iati_when_pcc_missing(): void
    {
        config(['suppliers.sabre.booking_payload_style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS]);

        $draft = $this->minimalDraft();
        unset($draft['_sabre_pseudo_city_code']);
        $draft['supplier_connection_id'] = 0;

        $service = app(SabreBookingService::class);
        $decision = $service->decidePassengerRecordsPayloadStyle(
            $this->minimalOffer(),
            $draft,
            null,
            null,
        );

        $this->assertFalse($decision['iati_like_eligible']);
        $this->assertFalse($decision['iati_like_selected']);
        $this->assertContains('pcc_missing', $decision['reasons']);
        $this->assertTrue($decision['manual_review_required']);
    }

    public function test_does_not_select_iati_when_rbd_missing(): void
    {
        config(['suppliers.sabre.booking_payload_style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS]);

        $draft = $this->minimalDraft();
        $draft['segments'][0]['booking_class'] = '';

        $service = app(SabreBookingService::class);
        $decision = $service->decidePassengerRecordsPayloadStyle(
            $this->minimalOffer(),
            $draft,
            $this->sabreConnection(),
            null,
        );

        $this->assertFalse($decision['iati_like_eligible']);
        $this->assertContains('rbd_missing_all_segments', $decision['reasons']);
    }

    public function test_does_not_select_iati_when_route_not_certified(): void
    {
        config([
            'suppliers.sabre.booking_payload_style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            'suppliers.sabre.cpnr_iati_style_certified_gds_enabled' => true,
        ]);

        $pendingRoute = [
            'category' => SabreCertifiedRouteSelector::CATEGORY_RETURN,
            'route_status' => SabreCertifiedRouteSelector::STATUS_PENDING_CERTIFICATION,
            'live_booking_allowed' => false,
            'payload_style' => null,
        ];

        $service = app(SabreBookingService::class);
        $decision = $service->decidePassengerRecordsPayloadStyle(
            $this->minimalOffer(),
            $this->minimalDraft(),
            $this->sabreConnection(),
            $pendingRoute,
        );

        $this->assertTrue($decision['iati_like_selection_considered']);
        $this->assertFalse($decision['iati_like_eligible']);
        $this->assertContains('non_certified_route', $decision['reasons']);
    }

    protected function certifiedConnectingRouteSelection(): array
    {
        return [
            'category' => SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS,
            'route_status' => SabreCertifiedRouteSelector::STATUS_CONTROLLED_CERTIFIED,
            'live_booking_allowed' => false,
            'endpoint_path' => SabreCertifiedRouteSelector::ENDPOINT_PASSENGER_RECORDS_V24_CREATE,
            'payload_style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
        ];
    }

    protected function twoSegmentOffer(): array
    {
        return [
            'id' => 'offer-test-2seg',
            'supplier_connection_id' => 1,
            'origin' => 'LHE',
            'destination' => 'DXB',
            'validating_carrier' => 'PK',
            'raw_payload' => ['distribution_model' => 'gds'],
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'KHI',
                    'carrier' => 'PK',
                    'flight_number' => '301',
                    'departure_at' => '2026-08-01T08:00:00',
                    'arrival_at' => '2026-08-01T10:00:00',
                    'booking_class' => 'Y',
                    'fare_basis_code' => 'YLOW',
                ],
                [
                    'origin' => 'KHI',
                    'destination' => 'DXB',
                    'carrier' => 'PK',
                    'flight_number' => '302',
                    'departure_at' => '2026-08-01T12:00:00',
                    'arrival_at' => '2026-08-01T16:00:00',
                    'booking_class' => 'Y',
                    'fare_basis_code' => 'YLOW',
                ],
            ],
        ];
    }

    protected function twoSegmentDraft(): array
    {
        return [
            'supplier_connection_id' => 1,
            '_sabre_pseudo_city_code' => 'AB12',
            'validating_carrier' => 'PK',
            'segments' => $this->twoSegmentOffer()['segments'],
            'passengers' => [
                [
                    'type' => 'ADT',
                    'first_name' => 'Test',
                    'last_name' => 'Traveler',
                    'gender' => 'MALE',
                    'date_of_birth' => '1990-01-15',
                ],
            ],
            'contact' => ['email' => 'booker@example.com', 'phone' => '3001234567'],
        ];
    }

    public function test_connecting_two_segment_not_considered_when_controlled_flag_off(): void
    {
        config([
            'suppliers.sabre.booking_payload_style' => null,
            'suppliers.sabre.cpnr_iati_style_certified_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => false,
        ]);

        $service = app(SabreBookingService::class);
        $decision = $service->decidePassengerRecordsPayloadStyle(
            $this->twoSegmentOffer(),
            $this->twoSegmentDraft(),
            $this->sabreConnection(),
            $this->certifiedConnectingRouteSelection(),
        );

        $this->assertFalse($decision['iati_like_selection_considered']);
        $this->assertFalse($decision['iati_like_selected']);
    }

    public function test_connecting_two_segment_selects_iati_when_controlled_enabled_and_gds_ready(): void
    {
        config([
            'suppliers.sabre.booking_payload_style' => null,
            'suppliers.sabre.cpnr_iati_style_certified_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_mode' => 'pnr_only',
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
        ]);

        $service = app(SabreBookingService::class);
        $decision = $service->decidePassengerRecordsPayloadStyle(
            $this->twoSegmentOffer(),
            $this->twoSegmentDraft(),
            $this->sabreConnection(),
            $this->certifiedConnectingRouteSelection(),
        );

        $this->assertTrue($decision['iati_like_selection_considered']);
        $this->assertTrue($decision['iati_like_selected']);
        $this->assertSame(SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS, $decision['selected_style']);
        $this->assertStringContainsString('v2.4.0', (string) $decision['selected_endpoint_path']);
    }

    public function test_certified_gds_flag_selects_iati_on_certified_route_when_eligible(): void
    {
        config([
            'suppliers.sabre.booking_payload_style' => null,
            'suppliers.sabre.cpnr_iati_style_certified_gds_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_mode' => 'pnr_only',
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
        ]);

        $service = app(SabreBookingService::class);
        $decision = $service->decidePassengerRecordsPayloadStyle(
            $this->minimalOffer(),
            $this->minimalDraft(),
            $this->sabreConnection(),
            $this->certifiedRouteSelection(),
        );

        $this->assertTrue($decision['iati_like_selected']);
        $this->assertTrue($decision['selected_by_certified_route']);
        $this->assertArrayHasKey('iati_like_reason_code', $decision);
        $this->assertArrayHasKey('cpnr_required_blocks_present', $decision);
    }

    public function test_decision_includes_safe_diagnostic_fields(): void
    {
        config(['suppliers.sabre.booking_payload_style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS]);

        $service = app(SabreBookingService::class);
        $decision = $service->decidePassengerRecordsPayloadStyle(
            $this->minimalOffer(),
            $this->minimalDraft(),
            $this->sabreConnection(),
            null,
        );

        foreach ([
            'selected_payload_style',
            'fallback_payload_style',
            'selected_endpoint_path',
            'selected_endpoint_version',
            'certified_route_result',
            'gds_compatible',
            'pcc_present',
        ] as $key) {
            $this->assertArrayHasKey($key, $decision, "Missing decision key: {$key}");
        }
    }

    public function test_resolve_passenger_records_endpoint_for_attempt_uses_iati_v24_when_selected(): void
    {
        $service = app(SabreBookingService::class);
        $reflection = new \ReflectionClass($service);
        $styleProp = $reflection->getProperty('attemptPassengerRecordsStyleDecision');
        $styleProp->setAccessible(true);
        $styleProp->setValue($service, [
            'selected_endpoint_path' => '/v2.4.0/passenger/records?mode=create',
            'selected_style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            'iati_like_selected' => true,
        ]);

        $method = $reflection->getMethod('resolvePassengerRecordsEndpointPathForAttempt');
        $method->setAccessible(true);

        $this->assertSame('/v2.4.0/passenger/records?mode=create', $method->invoke($service));
    }

    public function test_endpoint_persistence_slice_records_actual_and_selected_paths(): void
    {
        config(['suppliers.sabre.booking_path' => '/v2.5.0/passenger/records?mode=create']);

        $service = app(SabreBookingService::class);
        $reflection = new \ReflectionClass($service);
        $styleProp = $reflection->getProperty('attemptPassengerRecordsStyleDecision');
        $styleProp->setAccessible(true);
        $styleProp->setValue($service, [
            'selected_endpoint_path' => '/v2.4.0/passenger/records?mode=create',
            'selected_payload_style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            'selected_style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            'fallback_style' => SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
            'iati_like_selected' => true,
        ]);

        $method = $reflection->getMethod('passengerRecordsEndpointPersistenceSlice');
        $method->setAccessible(true);
        $slice = $method->invoke($service, '/v2.4.0/passenger/records?mode=create');

        $this->assertSame('/v2.4.0/passenger/records?mode=create', $slice['endpoint_path']);
        $this->assertSame('/v2.4.0/passenger/records?mode=create', $slice['actual_endpoint_path']);
        $this->assertSame('/v2.4.0/passenger/records?mode=create', $slice['selected_endpoint_path']);
        $this->assertStringContainsString('v2.5.0', (string) ($slice['configured_traditional_endpoint_path'] ?? ''));
    }

    public function test_resolve_endpoint_summary_prefers_booking_result_after_attempt_state_cleared(): void
    {
        config([
            'suppliers.sabre.booking_path' => '/v2.5.0/passenger/records?mode=create',
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
        ]);

        $service = app(SabreBookingService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('resolveEndpointSummaryPreferringBookingResult');
        $method->setAccessible(true);

        $result = [
            'endpoint_path' => '/v2.4.0/passenger/records?mode=create',
            'actual_endpoint_path' => '/v2.4.0/passenger/records?mode=create',
            'selected_endpoint_path' => '/v2.4.0/passenger/records?mode=create',
            'payload_schema' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
        ];

        $summary = $method->invoke($service, $result, 0);

        $this->assertSame('/v2.4.0/passenger/records?mode=create', $summary['endpoint_path']);
    }

    public function test_traditional_style_endpoint_remains_v25(): void
    {
        config(['suppliers.sabre.booking_path' => '/v2.5.0/passenger/records?mode=create']);

        $service = app(SabreBookingService::class);
        $reflection = new \ReflectionClass($service);
        $styleProp = $reflection->getProperty('attemptPassengerRecordsStyleDecision');
        $styleProp->setAccessible(true);
        $styleProp->setValue($service, [
            'selected_endpoint_path' => '/v2.5.0/passenger/records?mode=create',
            'selected_style' => SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
            'iati_like_selected' => false,
        ]);

        $method = $reflection->getMethod('resolvePassengerRecordsEndpointPathForAttempt');
        $method->setAccessible(true);

        $this->assertSame('/v2.5.0/passenger/records?mode=create', $method->invoke($service));
    }
}
