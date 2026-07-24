<?php

namespace Tests\Unit;

use App\Enums\SupplierProvider;
use App\Services\Suppliers\Sabre\Diagnostics\SabreRevalidationPayloadStructuralSchemaComparator;
use App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder;
use Tests\TestCase;

/**
 * Phase SABRE-REVALIDATION-BFM-FLIGHTSEGMENT-SCHEMA-CORRECTION-1
 */
class SabreRevalidationBfmFlightSegmentSchemaCorrectionPhaseTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function twoSegmentConnectingDraft(): array
    {
        $depart = '2026-09-15';

        return [
            'provider' => SupplierProvider::Sabre->value,
            'selected_offer_id' => 'qr-lhe-jed-offer',
            'supplier_offer_id' => 'qr-lhe-jed-offer',
            'validating_carrier' => 'QR',
            'fare' => ['amount' => 980.0, 'currency' => 'PKR'],
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DOH',
                    'departure_at' => $depart.'T02:30:00',
                    'arrival_at' => $depart.'T04:45:00',
                    'carrier' => 'QR',
                    'operating_airline_code' => 'QR',
                    'flight_number' => '633',
                    'booking_class' => 'N',
                    'fare_basis_code' => 'NLHR1R1S',
                    'segment_cabin_code' => 'Y',
                ],
                [
                    'origin' => 'DOH',
                    'destination' => 'JED',
                    'departure_at' => $depart.'T08:10:00',
                    'arrival_at' => $depart.'T10:35:00',
                    'carrier' => 'QR',
                    'operating_airline_code' => 'QR',
                    'flight_number' => '1184',
                    'booking_class' => 'N',
                    'fare_basis_code' => 'NLHR1R1S',
                    'segment_cabin_code' => 'Y',
                ],
            ],
            'passengers' => [['type' => 'ADT']],
            '_sabre_shop_context' => [
                'itinerary_ref' => '12',
                'leg_refs' => [1, 2],
                'schedule_refs' => [10, 11],
                'fare_component_refs' => [5],
                'pricing_information_ref' => 'pi-qr-1',
                'booking_classes' => ['N', 'N'],
                'fare_basis_codes' => ['NLHR1R1S', 'NLHR1R1S'],
            ],
            '_sabre_shop_identifiers' => [
                'pseudo_city_code' => 'TEST',
            ],
        ];
    }

    public function test_bfm_revalidate_v1_has_no_invalid_direct_flight_segment_on_odi(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $payload = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        $schema = $builder->evaluateRevalidationPayloadSchema($payload);

        $this->assertTrue($schema['revalidation_payload_schema_valid']);
        $this->assertNull($schema['payload_schema_reason_code']);
        $this->assertFalse($schema['contains_invalid_direct_flight_segment']);
        $this->assertTrue($schema['airline_marketing_type_valid']);
        $this->assertTrue($schema['airline_operating_type_valid']);
        $this->assertFalse($schema['contains_unsupported_segment_number']);
        $this->assertFalse($schema['contains_unsupported_resbookdesigcode']);
        $this->assertFalse($schema['contains_unsupported_fare_basis_code']);
        $this->assertFalse($schema['contains_unsupported_cabin_code']);
        $this->assertSame([], $schema['unsupported_flight_child_keys']);
        $this->assertNotContains('ResBookDesigCode', $schema['flight_child_keys']);
        $this->assertNotContains('FlightSegment', $schema['origin_destination_child_keys']);
        $this->assertContains('TPA_Extensions', $schema['origin_destination_child_keys']);

        $flights = $builder->wireableRequestPayload($payload);
        $this->assertNull(data_get($flights, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.FlightSegment'));
        $this->assertCount(2, data_get($flights, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.Flight'));
    }

    public function test_schema_validator_rejects_legacy_direct_flight_segment_shape_before_http(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $invalid = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'shop_replay_selected_itinerary_v1');
        $schema = $builder->evaluateRevalidationPayloadSchema($invalid);

        $this->assertTrue($schema['contains_invalid_direct_flight_segment']);
        $this->assertTrue($schema['revalidation_payload_schema_valid']);

        $tampered = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        $odi = data_get($tampered, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0');
        $this->assertIsArray($odi);
        $flight = data_get($odi, 'TPA_Extensions.Flight.0');
        $this->assertIsArray($flight);
        data_set($tampered, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0', [
            'FlightSegment' => $flight,
        ]);
        $blocked = $builder->evaluateRevalidationPayloadSchema($tampered);
        $this->assertFalse($blocked['revalidation_payload_schema_valid']);
        $this->assertSame(
            SabreRevalidationPayloadBuilder::REASON_INVALID_FLIGHTSEGMENT_LOCATION,
            $blocked['payload_schema_reason_code'],
        );
    }

    public function test_tampered_bfm_payload_is_blocked_by_schema_contract(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $tampered = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        $flight = data_get($tampered, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.Flight.0');
        $this->assertIsArray($flight);
        data_set($tampered, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0', [
            'FlightSegment' => $flight,
        ]);

        $blocked = $builder->evaluateRevalidationPayloadSchema($tampered);
        $this->assertFalse($blocked['revalidation_payload_schema_valid']);
        $this->assertSame(
            SabreRevalidationPayloadBuilder::REASON_INVALID_FLIGHTSEGMENT_LOCATION,
            $blocked['payload_schema_reason_code'],
        );
        $this->assertTrue($blocked['contains_invalid_direct_flight_segment']);
    }

    public function test_two_segment_lhe_doh_jed_itinerary_and_linkage_fields_remain(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $draft = $this->twoSegmentConnectingDraft();
        $payload = $builder->buildPayload($draft, 'bfm_revalidate_v1');
        $safe = $builder->safePayloadSummary($payload);

        $this->assertSame(2, $safe['segment_count']);
        $this->assertTrue($safe['has_booking_class']);
        $this->assertTrue($safe['has_fare_basis']);
        $this->assertTrue($safe['has_leg_refs']);
        $this->assertTrue($safe['has_schedule_refs']);
        $this->assertTrue($safe['has_fare_component_refs']);
        $this->assertTrue($safe['has_itinerary_reference']);
        $this->assertSame(['LHE→DOH', 'DOH→JED'], $safe['segment_routes']);
    }

    public function test_payload_freeze_fingerprint_changes_when_odi_child_keys_change(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $draft = $this->twoSegmentConnectingDraft();
        $corrected = $builder->buildPayload($draft, 'bfm_revalidate_v1');
        $legacyStyle = $builder->buildPayload($draft, 'shop_replay_selected_itinerary_v1');

        $correctedFp = $builder->revalidationPayloadFreezeFingerprint($corrected, $draft);
        $legacyFp = $builder->revalidationPayloadFreezeFingerprint($legacyStyle, $draft);

        $this->assertNotSame($correctedFp, $legacyFp);
        $this->assertSame(24, strlen($correctedFp));
    }

    public function test_structural_schema_comparator_reports_styles_without_raw_payload(): void
    {
        $comparator = $this->app->make(SabreRevalidationPayloadStructuralSchemaComparator::class);
        $report = $comparator->compareForDraft($this->twoSegmentConnectingDraft());
        $styles = $report['styles'] ?? [];
        $this->assertArrayHasKey('bfm_revalidate_v1', $styles);
        $this->assertArrayHasKey('shop_replay_selected_itinerary_v1', $styles);
        $this->assertFalse($styles['bfm_revalidate_v1']['origin_destination_contains_flight_segment']);
        $this->assertTrue($styles['shop_replay_selected_itinerary_v1']['origin_destination_contains_flight_segment']);
        $this->assertSame('compatible', $styles['bfm_revalidate_v1']['schema_compatibility_verdict']);
        $this->assertStringNotContainsString('NLHR1R1S', json_encode($report));
        $this->assertStringNotContainsString('authorization', json_encode($report));
    }
}
