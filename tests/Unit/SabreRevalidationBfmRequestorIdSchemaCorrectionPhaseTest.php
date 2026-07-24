<?php

namespace Tests\Unit;

use App\Enums\SupplierProvider;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Services\Suppliers\Sabre\Diagnostics\SabreRevalidationPayloadStructuralSchemaComparator;
use App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder;
use Tests\TestCase;

/**
 * Phase SABRE-REVALIDATION-BFM-REQUESTOR-ID-SCHEMA-CORRECTION-1
 */
class SabreRevalidationBfmRequestorIdSchemaCorrectionPhaseTest extends TestCase
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

    public function test_bfm_revalidate_v1_includes_requestor_id_at_pos_source(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $payload = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');

        $requestorId = data_get($payload, 'OTA_AirLowFareSearchRQ.POS.Source.0.RequestorID');
        $this->assertIsArray($requestorId);
        $this->assertArrayHasKey('ID', $requestorId);
        $this->assertIsString($requestorId['ID']);
        $this->assertSame(SabreRevalidationPayloadBuilder::BFM_REVALIDATE_REQUESTOR_ID, $requestorId['ID']);
        $this->assertSame(SabreRevalidationPayloadBuilder::BFM_REVALIDATE_REQUESTOR_TYPE, $requestorId['Type']);
        $this->assertSame(
            SabreRevalidationPayloadBuilder::BFM_REVALIDATE_REQUESTOR_COMPANY_CODE,
            data_get($requestorId, 'CompanyName.Code'),
        );
        $this->assertSame(
            $builder->buildBfmRevalidatePosRequestorIdBlock(),
            $requestorId,
        );
    }

    public function test_schema_validator_rejects_missing_requestor_id_before_http(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $tampered = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        unset($tampered['OTA_AirLowFareSearchRQ']['POS']['Source'][0]['RequestorID']['ID']);

        $blocked = $builder->evaluateRevalidationPayloadSchema($tampered);
        $this->assertFalse($blocked['revalidation_payload_schema_valid']);
        $this->assertSame(
            SabreRevalidationPayloadBuilder::REASON_MISSING_OR_INVALID_REQUESTOR_ID,
            $blocked['payload_schema_reason_code'],
        );
        $this->assertFalse($blocked['requestor_id_present']);
        $this->assertFalse($blocked['requestor_id_type_valid']);
        $this->assertFalse($blocked['requestor_id_non_empty']);
        $this->assertContains(
            '$.OTA_AirLowFareSearchRQ.POS.Source[0].RequestorID.ID',
            $blocked['invalid_schema_paths'],
        );
    }

    public function test_schema_validator_rejects_null_and_empty_requestor_id_before_http(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);

        foreach ([null, ''] as $invalidId) {
            $tampered = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
            data_set($tampered, 'OTA_AirLowFareSearchRQ.POS.Source.0.RequestorID.ID', $invalidId);

            $blocked = $builder->evaluateRevalidationPayloadSchema($tampered);
            $this->assertFalse($blocked['revalidation_payload_schema_valid']);
            $this->assertSame(
                SabreRevalidationPayloadBuilder::REASON_MISSING_OR_INVALID_REQUESTOR_ID,
                $blocked['payload_schema_reason_code'],
            );
            $this->assertFalse($blocked['requestor_id_type_valid']);
            $this->assertFalse($blocked['requestor_id_non_empty']);
        }
    }

    public function test_schema_validator_rejects_object_array_and_boolean_requestor_id_before_http(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);

        foreach ([['id' => '1'], true, false] as $invalidId) {
            $tampered = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
            data_set($tampered, 'OTA_AirLowFareSearchRQ.POS.Source.0.RequestorID.ID', $invalidId);

            $blocked = $builder->evaluateRevalidationPayloadSchema($tampered);
            $this->assertFalse($blocked['revalidation_payload_schema_valid'], json_encode($invalidId));
            $this->assertSame(
                SabreRevalidationPayloadBuilder::REASON_MISSING_OR_INVALID_REQUESTOR_ID,
                $blocked['payload_schema_reason_code'],
            );
            $this->assertFalse($blocked['requestor_id_type_valid']);
        }
    }

    public function test_corrected_payload_reports_pos_source_and_requestor_structure(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $payload = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        $schema = $builder->evaluateRevalidationPayloadSchema($payload);

        $this->assertTrue($schema['revalidation_payload_schema_valid']);
        $this->assertTrue($schema['requestor_id_present']);
        $this->assertTrue($schema['requestor_id_type_valid']);
        $this->assertTrue($schema['requestor_id_non_empty']);
        $this->assertTrue($schema['requestor_identity_source_present']);
        $this->assertSame('sabre_bfm_shop_requestor_id_parity', $schema['requestor_identity_source_location']);
        $this->assertContains('POS', $schema['root_child_keys']);
        $this->assertContains('Source', $schema['pos_child_keys']);
        $this->assertContains('RequestorID', $schema['source_child_keys']);
        $this->assertContains('ID', $schema['requestor_id_child_keys']);
        $this->assertContains('Type', $schema['requestor_id_child_keys']);
        $this->assertContains('CompanyName', $schema['requestor_id_child_keys']);
        $this->assertSame('string', $schema['requestor_id_child_types']['ID'] ?? null);
        $this->assertTrue($schema['root_version_present']);
        $this->assertTrue($schema['booking_class_context_present']);
        $this->assertTrue($schema['pricing_context_present']);
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

    public function test_payload_freeze_fingerprint_changes_after_requestor_id_correction(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $draft = $this->twoSegmentConnectingDraft();
        $corrected = $builder->buildPayload($draft, 'bfm_revalidate_v1');
        $legacy = $builder->buildPayload($draft, 'bfm_revalidate_v1');
        unset($legacy['OTA_AirLowFareSearchRQ']['POS']['Source'][0]['RequestorID']['ID']);

        $this->assertNotSame(
            $builder->revalidationPayloadFreezeFingerprint($corrected, $draft),
            $builder->revalidationPayloadFreezeFingerprint($legacy, $draft),
        );
    }

    public function test_safe_structural_output_excludes_raw_requestor_id_value(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $schema = $builder->evaluateRevalidationPayloadSchema(
            $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1'),
        );
        $encoded = json_encode($schema);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('"ID":"1"', $encoded);
        $this->assertStringNotContainsString('BFM_REVALIDATE_REQUESTOR_ID', $encoded);
    }

    public function test_invalid_send_attempt_is_blocked_before_supplier_call(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $tampered = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        unset($tampered['OTA_AirLowFareSearchRQ']['POS']['Source'][0]['RequestorID']['ID']);

        $schema = $builder->evaluateRevalidationPayloadSchema($tampered);
        $service = $this->app->make(SabreBookingService::class);
        $messageMethod = new \ReflectionMethod($service, 'revalidationPayloadSchemaBlockMessage');
        $messageMethod->setAccessible(true);
        $message = $messageMethod->invoke($service, $schema, 'bfm_revalidate_v1');

        $this->assertFalse($schema['revalidation_payload_schema_valid']);
        $this->assertSame(
            SabreRevalidationPayloadBuilder::REASON_MISSING_OR_INVALID_REQUESTOR_ID,
            $schema['payload_schema_reason_code'],
        );
        $this->assertStringContainsString('RequestorID.ID', $message);
    }

    public function test_structural_comparator_reports_compatible_bfm_with_requestor_id(): void
    {
        $comparator = $this->app->make(SabreRevalidationPayloadStructuralSchemaComparator::class);
        $bfm = $comparator->compareForDraft($this->twoSegmentConnectingDraft())['styles']['bfm_revalidate_v1'] ?? [];
        $this->assertSame('compatible', $bfm['schema_compatibility_verdict'] ?? null);
        $this->assertTrue($bfm['requestor_id_present'] ?? false);
        $this->assertTrue($bfm['requestor_id_type_valid'] ?? false);
        $this->assertTrue($bfm['requestor_identity_source_present'] ?? false);
        $this->assertContains('ID', $bfm['requestor_id_child_keys'] ?? []);
    }
}
