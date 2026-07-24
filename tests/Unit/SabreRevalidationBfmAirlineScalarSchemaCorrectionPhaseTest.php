<?php

namespace Tests\Unit;

use App\Enums\SupplierProvider;
use App\Services\Suppliers\Sabre\Diagnostics\SabreRevalidationPayloadStructuralSchemaComparator;
use App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder;
use Tests\TestCase;

/**
 * Phase SABRE-REVALIDATION-BFM-AIRLINE-SCALAR-SCHEMA-CORRECTION-1
 */
class SabreRevalidationBfmAirlineScalarSchemaCorrectionPhaseTest extends TestCase
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

    public function test_bfm_revalidate_v1_emits_scalar_airline_marketing_and_operating(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $payload = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        $firstFlight = data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.Flight.0');
        $this->assertIsArray($firstFlight);
        $this->assertIsString(data_get($firstFlight, 'Airline.Marketing'));
        $this->assertIsString(data_get($firstFlight, 'Airline.Operating'));
        $this->assertNull(data_get($firstFlight, 'Airline.Marketing.Code'));
        $this->assertNull(data_get($firstFlight, 'Airline.Operating.Code'));
    }

    public function test_schema_validator_rejects_object_airline_marketing_before_http(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $tampered = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        data_set(
            $tampered,
            'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.Flight.0.Airline.Marketing',
            ['Code' => 'QR'],
        );

        $blocked = $builder->evaluateRevalidationPayloadSchema($tampered);
        $this->assertFalse($blocked['revalidation_payload_schema_valid']);
        $this->assertSame(
            SabreRevalidationPayloadBuilder::REASON_INVALID_AIRLINE_MARKETING_TYPE,
            $blocked['payload_schema_reason_code'],
        );
        $this->assertFalse($blocked['airline_marketing_type_valid']);
        $this->assertContains(
            '$.OTA_AirLowFareSearchRQ.OriginDestinationInformation[0].TPA_Extensions.Flight[0].Airline.Marketing',
            $blocked['invalid_schema_paths'],
        );
        $this->assertSame(1, $blocked['invalid_schema_type_count']);
    }

    public function test_corrected_bfm_payload_passes_airline_scalar_schema(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $payload = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        $schema = $builder->evaluateRevalidationPayloadSchema($payload);

        $this->assertTrue($schema['revalidation_payload_schema_valid']);
        $this->assertTrue($schema['airline_marketing_type_valid']);
        $this->assertTrue($schema['airline_operating_type_valid']);
        $this->assertSame([], $schema['invalid_schema_paths']);
        $this->assertContains('Marketing', $schema['airline_child_keys']);
        $this->assertContains('Operating', $schema['airline_child_keys']);
    }

    public function test_safe_structural_output_excludes_raw_carrier_values(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $payload = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        $schema = $builder->evaluateRevalidationPayloadSchema($payload);
        $encoded = json_encode($schema);

        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('"QR"', $encoded);
        $this->assertStringNotContainsString('633', $encoded);
        $this->assertStringNotContainsString('NLHR1R1S', $encoded);
    }

    public function test_two_segment_qr_itinerary_linkage_and_fare_context_remain(): void
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

    public function test_payload_freeze_fingerprint_changes_after_airline_scalar_correction(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $draft = $this->twoSegmentConnectingDraft();
        $corrected = $builder->buildPayload($draft, 'bfm_revalidate_v1');
        $legacyObject = $builder->buildPayload($draft, 'bfm_revalidate_v1');
        data_set(
            $legacyObject,
            'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.Flight.0.Airline.Marketing',
            ['Code' => 'QR'],
        );
        data_set(
            $legacyObject,
            'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.Flight.1.Airline.Marketing',
            ['Code' => 'QR'],
        );
        data_set(
            $legacyObject,
            'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.Flight.0.Airline.Operating',
            ['Code' => 'QR'],
        );
        data_set(
            $legacyObject,
            'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.Flight.1.Airline.Operating',
            ['Code' => 'QR'],
        );

        $correctedFp = $builder->revalidationPayloadFreezeFingerprint($corrected, $draft);
        $legacyFp = $builder->revalidationPayloadFreezeFingerprint($legacyObject, $draft);

        $this->assertNotSame($correctedFp, $legacyFp);
    }

    public function test_invalid_airline_type_produces_schema_block_fields_without_http(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $tampered = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        data_set(
            $tampered,
            'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.Flight.0.Airline.Marketing',
            ['Code' => 'QR'],
        );

        $schema = $builder->evaluateRevalidationPayloadSchema($tampered);
        $this->assertFalse($schema['revalidation_payload_schema_valid']);
        $this->assertSame(
            SabreRevalidationPayloadBuilder::REASON_INVALID_AIRLINE_MARKETING_TYPE,
            $schema['payload_schema_reason_code'],
        );
        $this->assertSame(1, $schema['invalid_schema_type_count']);
    }

    public function test_iati_like_style_still_uses_object_airline_shape(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $payload = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'iati_like_bfm_revalidate_v1');
        $firstFlight = data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.Flight.0');
        $this->assertIsArray(data_get($firstFlight, 'Airline.Marketing'));
        $this->assertIsString(data_get($firstFlight, 'Airline.Marketing.Code'));
    }

    public function test_structural_comparator_reports_compatible_bfm_without_raw_carrier(): void
    {
        $comparator = $this->app->make(SabreRevalidationPayloadStructuralSchemaComparator::class);
        $report = $comparator->compareForDraft($this->twoSegmentConnectingDraft());
        $styles = $report['styles'] ?? [];
        $this->assertSame('compatible', $styles['bfm_revalidate_v1']['schema_compatibility_verdict']);
        $this->assertTrue($styles['bfm_revalidate_v1']['airline_marketing_type_valid']);
        $this->assertStringNotContainsString('QR', json_encode($report));
    }
}
