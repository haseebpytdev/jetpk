<?php

namespace Tests\Unit;

use App\Enums\SupplierProvider;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Services\Suppliers\Sabre\Diagnostics\SabreRevalidationPayloadStructuralSchemaComparator;
use App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder;
use Tests\TestCase;

/**
 * Phase SABRE-REVALIDATION-BFM-ROOT-VERSION-SCHEMA-CORRECTION-1
 */
class SabreRevalidationBfmRootVersionSchemaCorrectionPhaseTest extends TestCase
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
                'brand_code' => 'ECON',
                'selected_brand_code' => 'ECON',
            ],
            '_sabre_shop_identifiers' => [
                'pseudo_city_code' => 'TEST',
            ],
        ];
    }

    public function test_bfm_revalidate_v1_includes_root_version_at_ota_root(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $payload = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');

        $this->assertArrayHasKey('Version', data_get($payload, 'OTA_AirLowFareSearchRQ'));
        $this->assertIsString(data_get($payload, 'OTA_AirLowFareSearchRQ.Version'));
        $this->assertSame(
            SabreRevalidationPayloadBuilder::BFM_REVALIDATE_OTA_VERSION,
            data_get($payload, 'OTA_AirLowFareSearchRQ.Version'),
        );
        $this->assertSame(
            SabreRevalidationPayloadBuilder::BFM_REVALIDATE_OTA_VERSION,
            $builder->bfmRevalidateOtaAirLowFareSearchVersion(),
        );
    }

    public function test_schema_validator_rejects_missing_root_version_before_http(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $tampered = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        unset($tampered['OTA_AirLowFareSearchRQ']['Version']);

        $blocked = $builder->evaluateRevalidationPayloadSchema($tampered);
        $this->assertFalse($blocked['revalidation_payload_schema_valid']);
        $this->assertSame(
            SabreRevalidationPayloadBuilder::REASON_MISSING_OR_INVALID_ROOT_VERSION,
            $blocked['payload_schema_reason_code'],
        );
        $this->assertFalse($blocked['root_version_present']);
        $this->assertFalse($blocked['root_version_type_valid']);
        $this->assertContains('$.OTA_AirLowFareSearchRQ.Version', $blocked['invalid_schema_paths']);
    }

    public function test_schema_validator_rejects_empty_root_version_before_http(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $tampered = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        data_set($tampered, 'OTA_AirLowFareSearchRQ.Version', '');

        $blocked = $builder->evaluateRevalidationPayloadSchema($tampered);
        $this->assertFalse($blocked['revalidation_payload_schema_valid']);
        $this->assertSame(
            SabreRevalidationPayloadBuilder::REASON_MISSING_OR_INVALID_ROOT_VERSION,
            $blocked['payload_schema_reason_code'],
        );
        $this->assertTrue($blocked['root_version_present']);
        $this->assertFalse($blocked['root_version_type_valid']);
    }

    public function test_schema_validator_rejects_object_and_array_root_version_before_http(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);

        foreach ([['major' => '4'], ['major' => '4', 'minor' => '0']] as $invalidVersion) {
            $tampered = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
            data_set($tampered, 'OTA_AirLowFareSearchRQ.Version', $invalidVersion);

            $blocked = $builder->evaluateRevalidationPayloadSchema($tampered);
            $this->assertFalse($blocked['revalidation_payload_schema_valid'], json_encode($invalidVersion));
            $this->assertSame(
                SabreRevalidationPayloadBuilder::REASON_MISSING_OR_INVALID_ROOT_VERSION,
                $blocked['payload_schema_reason_code'],
            );
            $this->assertFalse($blocked['root_version_type_valid']);
        }
    }

    public function test_corrected_payload_reports_root_child_keys_and_preserves_context(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $payload = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        $schema = $builder->evaluateRevalidationPayloadSchema($payload);

        $this->assertTrue($schema['revalidation_payload_schema_valid']);
        $this->assertTrue($schema['root_version_present']);
        $this->assertTrue($schema['root_version_type_valid']);
        $this->assertContains('Version', $schema['root_child_keys']);
        $this->assertFalse($schema['root_target_present']);
        $this->assertTrue($schema['booking_class_context_present']);
        $this->assertTrue($schema['cabin_context_present']);
        $this->assertTrue($schema['fare_basis_context_present']);
        $this->assertTrue($schema['branded_fare_context_present']);
        $this->assertTrue($schema['pricing_context_present']);
        $this->assertTrue($schema['fare_component_references_present']);
    }

    public function test_two_segment_qr_itinerary_and_marketing_numbers_remain(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $payload = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        $flights = data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.Flight');
        $this->assertIsArray($flights);
        $this->assertSame(633, data_get($flights, '0.Number'));
        $this->assertSame(1184, data_get($flights, '1.Number'));
        $this->assertSame(['LHE→DOH', 'DOH→JED'], $builder->safePayloadSummary($payload)['segment_routes']);
    }

    public function test_payload_freeze_fingerprint_changes_after_root_version_correction(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $draft = $this->twoSegmentConnectingDraft();
        $corrected = $builder->buildPayload($draft, 'bfm_revalidate_v1');
        $legacy = $builder->buildPayload($draft, 'bfm_revalidate_v1');
        unset($legacy['OTA_AirLowFareSearchRQ']['Version']);

        $this->assertNotSame(
            $builder->revalidationPayloadFreezeFingerprint($corrected, $draft),
            $builder->revalidationPayloadFreezeFingerprint($legacy, $draft),
        );
    }

    public function test_safe_structural_output_excludes_raw_version_value(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $schema = $builder->evaluateRevalidationPayloadSchema(
            $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1'),
        );
        $encoded = json_encode($schema);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('"4"', $encoded);
        $this->assertStringNotContainsString('BFM_REVALIDATE_OTA_VERSION', $encoded);
    }

    public function test_invalid_send_attempt_is_blocked_before_supplier_call(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $tampered = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        unset($tampered['OTA_AirLowFareSearchRQ']['Version']);

        $schema = $builder->evaluateRevalidationPayloadSchema($tampered);
        $service = $this->app->make(SabreBookingService::class);
        $messageMethod = new \ReflectionMethod($service, 'revalidationPayloadSchemaBlockMessage');
        $messageMethod->setAccessible(true);
        $message = $messageMethod->invoke($service, $schema, 'bfm_revalidate_v1');

        $this->assertFalse($schema['revalidation_payload_schema_valid']);
        $this->assertSame(
            SabreRevalidationPayloadBuilder::REASON_MISSING_OR_INVALID_ROOT_VERSION,
            $schema['payload_schema_reason_code'],
        );
        $this->assertStringContainsString('OTA_AirLowFareSearchRQ.Version', $message);
    }

    public function test_structural_comparator_reports_compatible_bfm_with_root_version(): void
    {
        $comparator = $this->app->make(SabreRevalidationPayloadStructuralSchemaComparator::class);
        $bfm = $comparator->compareForDraft($this->twoSegmentConnectingDraft())['styles']['bfm_revalidate_v1'] ?? [];
        $this->assertSame('compatible', $bfm['schema_compatibility_verdict'] ?? null);
        $this->assertTrue($bfm['root_version_present'] ?? false);
        $this->assertTrue($bfm['root_version_type_valid'] ?? false);
        $this->assertContains('Version', $bfm['root_child_keys'] ?? []);
    }
}
