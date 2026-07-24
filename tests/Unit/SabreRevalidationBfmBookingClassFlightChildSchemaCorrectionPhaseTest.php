<?php

namespace Tests\Unit;

use App\Enums\SupplierProvider;
use App\Services\Suppliers\Sabre\Diagnostics\SabreRevalidationPayloadStructuralSchemaComparator;
use App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder;
use Tests\TestCase;

/**
 * Phase SABRE-REVALIDATION-BFM-BOOKING-CLASS-FLIGHT-CHILD-SCHEMA-CORRECTION-1
 */
class SabreRevalidationBfmBookingClassFlightChildSchemaCorrectionPhaseTest extends TestCase
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

    public function test_bfm_revalidate_v1_omits_resbookdesigcode_under_flight(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $payload = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        $flights = data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.Flight');
        $this->assertIsArray($flights);
        foreach ($flights as $flight) {
            $this->assertIsArray($flight);
            $this->assertArrayNotHasKey('ResBookDesigCode', $flight);
            $this->assertArrayHasKey('ClassOfService', $flight);
        }
    }

    public function test_schema_validator_rejects_resbookdesigcode_before_http(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $tampered = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        data_set(
            $tampered,
            'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.Flight.0.ResBookDesigCode',
            'N',
        );

        $blocked = $builder->evaluateRevalidationPayloadSchema($tampered);
        $this->assertFalse($blocked['revalidation_payload_schema_valid']);
        $this->assertSame(
            SabreRevalidationPayloadBuilder::REASON_UNSUPPORTED_RESBOOKDESIGCODE,
            $blocked['payload_schema_reason_code'],
        );
        $this->assertTrue($blocked['contains_unsupported_resbookdesigcode']);
        $this->assertContains('ResBookDesigCode', $blocked['unsupported_flight_child_keys']);
        $this->assertContains(
            '$.OTA_AirLowFareSearchRQ.OriginDestinationInformation[0].TPA_Extensions.Flight[0].ResBookDesigCode',
            $blocked['invalid_schema_paths'],
        );
    }

    public function test_corrected_payload_preserves_booking_class_and_fare_basis_context(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $payload = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        $schema = $builder->evaluateRevalidationPayloadSchema($payload);

        $this->assertTrue($schema['revalidation_payload_schema_valid']);
        $this->assertFalse($schema['contains_unsupported_resbookdesigcode']);
        $this->assertTrue($schema['booking_class_context_present']);
        $this->assertTrue($schema['fare_basis_context_present']);
        $this->assertStringContainsString('ota_flight_class_of_service', (string) $schema['booking_class_context_location']);
        $this->assertStringContainsString('shop_context_booking_classes', (string) $schema['booking_class_context_location']);
        $this->assertStringContainsString('shop_context_fare_basis_codes', (string) $schema['fare_basis_context_location']);
        $this->assertStringContainsString('fare_context_fare_basis_codes', (string) $schema['fare_basis_context_location']);
        $this->assertNotContains('FareBasisCode', $schema['flight_child_keys']);
    }

    public function test_two_segment_qr_itinerary_order_and_number_remain(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $payload = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        $flights = data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.Flight');
        $this->assertIsArray($flights);
        $this->assertSame('LHE', data_get($flights, '0.OriginLocation.LocationCode'));
        $this->assertSame('DOH', data_get($flights, '0.DestinationLocation.LocationCode'));
        $this->assertSame(633, data_get($flights, '0.Number'));
        $this->assertSame('DOH', data_get($flights, '1.OriginLocation.LocationCode'));
        $this->assertSame('JED', data_get($flights, '1.DestinationLocation.LocationCode'));
        $this->assertSame(1184, data_get($flights, '1.Number'));
    }

    public function test_linkage_and_fare_reference_coverage_remain(): void
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

    public function test_payload_freeze_fingerprint_changes_after_resbookdesigcode_removal(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $draft = $this->twoSegmentConnectingDraft();
        $corrected = $builder->buildPayload($draft, 'bfm_revalidate_v1');
        $legacy = $builder->buildPayload($draft, 'bfm_revalidate_v1');
        data_set(
            $legacy,
            'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.Flight.0.ResBookDesigCode',
            'N',
        );
        data_set(
            $legacy,
            'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.Flight.1.ResBookDesigCode',
            'N',
        );

        $correctedFp = $builder->revalidationPayloadFreezeFingerprint($corrected, $draft);
        $legacyFp = $builder->revalidationPayloadFreezeFingerprint($legacy, $draft);

        $this->assertNotSame($correctedFp, $legacyFp);
    }

    public function test_safe_structural_output_excludes_raw_booking_class_values(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $payload = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        $schema = $builder->evaluateRevalidationPayloadSchema($payload);
        $encoded = json_encode($schema);

        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('"N"', $encoded);
        $this->assertStringNotContainsString('NLHR1R1S', $encoded);
    }

    public function test_structural_comparator_reports_compatible_bfm_without_resbookdesigcode(): void
    {
        $comparator = $this->app->make(SabreRevalidationPayloadStructuralSchemaComparator::class);
        $report = $comparator->compareForDraft($this->twoSegmentConnectingDraft());
        $bfm = $report['styles']['bfm_revalidate_v1'] ?? [];
        $this->assertSame('compatible', $bfm['schema_compatibility_verdict'] ?? null);
        $this->assertFalse($bfm['contains_unsupported_resbookdesigcode'] ?? true);
        $this->assertTrue($bfm['booking_class_context_present'] ?? false);
        $this->assertTrue($bfm['fare_basis_context_present'] ?? false);
    }
}
