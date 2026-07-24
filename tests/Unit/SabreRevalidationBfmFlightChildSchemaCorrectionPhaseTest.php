<?php

namespace Tests\Unit;

use App\Enums\SupplierProvider;
use App\Services\Suppliers\Sabre\Diagnostics\SabreRevalidationPayloadStructuralSchemaComparator;
use App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder;
use Tests\TestCase;

/**
 * Phase SABRE-REVALIDATION-BFM-FLIGHT-CHILD-SCHEMA-CORRECTION-1
 */
class SabreRevalidationBfmFlightChildSchemaCorrectionPhaseTest extends TestCase
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

    public function test_bfm_revalidate_v1_omits_segment_number_under_flight(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $payload = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        $flights = data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.Flight');
        $this->assertIsArray($flights);
        $this->assertCount(2, $flights);
        foreach ($flights as $flight) {
            $this->assertIsArray($flight);
            $this->assertArrayNotHasKey('SegmentNumber', $flight);
        }
    }

    public function test_schema_validator_rejects_segment_number_before_http(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $tampered = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        data_set(
            $tampered,
            'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.Flight.0.SegmentNumber',
            1,
        );

        $blocked = $builder->evaluateRevalidationPayloadSchema($tampered);
        $this->assertFalse($blocked['revalidation_payload_schema_valid']);
        $this->assertSame(
            SabreRevalidationPayloadBuilder::REASON_UNSUPPORTED_FLIGHT_SEGMENT_NUMBER,
            $blocked['payload_schema_reason_code'],
        );
        $this->assertTrue($blocked['contains_unsupported_segment_number']);
        $this->assertContains('SegmentNumber', $blocked['unsupported_flight_child_keys']);
        $this->assertContains(
            '$.OTA_AirLowFareSearchRQ.OriginDestinationInformation[0].TPA_Extensions.Flight[0].SegmentNumber',
            $blocked['invalid_schema_paths'],
        );
    }

    public function test_corrected_bfm_payload_passes_flight_child_schema(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $payload = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        $schema = $builder->evaluateRevalidationPayloadSchema($payload);

        $this->assertTrue($schema['revalidation_payload_schema_valid']);
        $this->assertFalse($schema['contains_unsupported_segment_number']);
        $this->assertSame([], $schema['unsupported_flight_child_keys']);
        $this->assertNotContains('SegmentNumber', $schema['flight_child_keys']);
        $this->assertNotContains('ResBookDesigCode', $schema['flight_child_keys']);
        $this->assertNotContains('FareBasisCode', $schema['flight_child_keys']);
        $this->assertContains('Number', $schema['flight_child_keys']);
    }

    public function test_segment_ordering_and_flight_numbers_remain_deterministic(): void
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

    public function test_two_segment_qr_linkage_and_fare_context_remain(): void
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

    public function test_payload_freeze_fingerprint_changes_after_segment_number_removal(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $draft = $this->twoSegmentConnectingDraft();
        $corrected = $builder->buildPayload($draft, 'bfm_revalidate_v1');
        $legacy = $builder->buildPayload($draft, 'bfm_revalidate_v1');
        data_set(
            $legacy,
            'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.Flight.0.SegmentNumber',
            1,
        );
        data_set(
            $legacy,
            'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.Flight.1.SegmentNumber',
            2,
        );

        $correctedFp = $builder->revalidationPayloadFreezeFingerprint($corrected, $draft);
        $legacyFp = $builder->revalidationPayloadFreezeFingerprint($legacy, $draft);

        $this->assertNotSame($correctedFp, $legacyFp);
    }

    public function test_safe_structural_output_excludes_raw_values(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $payload = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        $schema = $builder->evaluateRevalidationPayloadSchema($payload);
        $encoded = json_encode($schema);

        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('"633"', $encoded);
        $this->assertStringNotContainsString('"QR"', $encoded);
        $this->assertStringNotContainsString('NLHR1R1S', $encoded);
    }

    public function test_structural_comparator_reports_compatible_bfm_without_segment_number(): void
    {
        $comparator = $this->app->make(SabreRevalidationPayloadStructuralSchemaComparator::class);
        $report = $comparator->compareForDraft($this->twoSegmentConnectingDraft());
        $bfm = $report['styles']['bfm_revalidate_v1'] ?? [];
        $this->assertSame('compatible', $bfm['schema_compatibility_verdict'] ?? null);
        $this->assertFalse($bfm['contains_unsupported_segment_number'] ?? true);
        $this->assertSame([], $bfm['unsupported_flight_child_keys'] ?? ['unexpected']);
    }
}
