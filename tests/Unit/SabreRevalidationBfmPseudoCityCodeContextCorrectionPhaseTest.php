<?php

namespace Tests\Unit;

use App\Enums\SupplierProvider;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Services\Suppliers\Sabre\Diagnostics\SabreRevalidationPayloadStructuralSchemaComparator;
use App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder;
use Tests\TestCase;

/**
 * Phase SABRE-REVALIDATION-BFM-PSEUDO-CITY-CODE-CONTEXT-CORRECTION-1
 */
class SabreRevalidationBfmPseudoCityCodeContextCorrectionPhaseTest extends TestCase
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
                'pseudo_city_code' => 'ABCD',
            ],
        ];
    }

    public function test_bfm_revalidate_v1_includes_pseudo_city_code_at_pos_source(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $draft = $this->twoSegmentConnectingDraft();
        $payload = $builder->buildPayload($draft, 'bfm_revalidate_v1');

        $this->assertArrayHasKey('PseudoCityCode', data_get($payload, 'OTA_AirLowFareSearchRQ.POS.Source.0'));
        $this->assertIsString(data_get($payload, 'OTA_AirLowFareSearchRQ.POS.Source.0.PseudoCityCode'));
        $this->assertSame(
            $builder->resolveBfmRevalidatePseudoCityCodeContext($draft)['pcc'],
            data_get($payload, 'OTA_AirLowFareSearchRQ.POS.Source.0.PseudoCityCode'),
        );
        $this->assertSame('shop_context_pseudo_city_code', $payload['_ota_revalidate_pcc_source_location'] ?? null);
        $this->assertArrayHasKey('ID', data_get($payload, 'OTA_AirLowFareSearchRQ.POS.Source.0.RequestorID'));
    }

    public function test_pcc_resolves_from_draft_explicit_key_with_precedence_over_shop_context(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $draft = $this->twoSegmentConnectingDraft();
        $draft['_sabre_pseudo_city_code'] = 'ZZ99';

        $context = $builder->resolveBfmRevalidatePseudoCityCodeContext($draft);
        $this->assertSame('ZZ99', $context['pcc']);
        $this->assertSame('draft_sabre_pseudo_city_code', $context['source_location']);
    }

    public function test_pcc_resolves_from_shop_context_pcc_key(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $draft = $this->twoSegmentConnectingDraft();
        unset($draft['_sabre_shop_identifiers']['pseudo_city_code']);
        $draft['_sabre_shop_identifiers']['pcc'] = 'EFGH';

        $context = $builder->resolveBfmRevalidatePseudoCityCodeContext($draft);
        $this->assertSame('EFGH', $context['pcc']);
        $this->assertSame('shop_context_pcc', $context['source_location']);
    }

    public function test_schema_validator_rejects_missing_pseudo_city_code_before_http(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $tampered = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        unset($tampered['OTA_AirLowFareSearchRQ']['POS']['Source'][0]['PseudoCityCode']);
        $tampered['_ota_revalidate_pcc_source_location'] = null;

        $blocked = $builder->evaluateRevalidationPayloadSchema($tampered);
        $this->assertFalse($blocked['revalidation_payload_schema_valid']);
        $this->assertSame(
            SabreRevalidationPayloadBuilder::REASON_MISSING_OR_INVALID_PSEUDO_CITY_CODE,
            $blocked['payload_schema_reason_code'],
        );
        $this->assertFalse($blocked['pseudo_city_code_present']);
        $this->assertFalse($blocked['pseudo_city_code_type_valid']);
        $this->assertFalse($blocked['pseudo_city_code_non_empty']);
        $this->assertContains(
            '$.OTA_AirLowFareSearchRQ.POS.Source[0].PseudoCityCode',
            $blocked['invalid_schema_paths'],
        );
    }

    public function test_schema_validator_rejects_null_and_empty_pseudo_city_code_before_http(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);

        foreach ([null, ''] as $invalidPcc) {
            $tampered = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
            data_set($tampered, 'OTA_AirLowFareSearchRQ.POS.Source.0.PseudoCityCode', $invalidPcc);

            $blocked = $builder->evaluateRevalidationPayloadSchema($tampered);
            $this->assertFalse($blocked['revalidation_payload_schema_valid']);
            $this->assertSame(
                SabreRevalidationPayloadBuilder::REASON_MISSING_OR_INVALID_PSEUDO_CITY_CODE,
                $blocked['payload_schema_reason_code'],
            );
            $this->assertFalse($blocked['pseudo_city_code_type_valid']);
            $this->assertFalse($blocked['pseudo_city_code_non_empty']);
        }
    }

    public function test_schema_validator_rejects_object_array_and_boolean_pseudo_city_code_before_http(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);

        foreach ([['code' => 'ABCD'], true, false] as $invalidPcc) {
            $tampered = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
            data_set($tampered, 'OTA_AirLowFareSearchRQ.POS.Source.0.PseudoCityCode', $invalidPcc);

            $blocked = $builder->evaluateRevalidationPayloadSchema($tampered);
            $this->assertFalse($blocked['revalidation_payload_schema_valid'], json_encode($invalidPcc));
            $this->assertSame(
                SabreRevalidationPayloadBuilder::REASON_MISSING_OR_INVALID_PSEUDO_CITY_CODE,
                $blocked['payload_schema_reason_code'],
            );
            $this->assertFalse($blocked['pseudo_city_code_type_valid']);
        }
    }

    public function test_schema_validator_blocks_when_no_authoritative_pcc_available(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $draft = $this->twoSegmentConnectingDraft();
        unset($draft['_sabre_shop_identifiers']);

        $payload = $builder->buildPayload($draft, 'bfm_revalidate_v1');
        $blocked = $builder->evaluateRevalidationPayloadSchema($payload);

        $this->assertFalse($blocked['revalidation_payload_schema_valid']);
        $this->assertSame(
            SabreRevalidationPayloadBuilder::REASON_MISSING_OR_INVALID_PSEUDO_CITY_CODE,
            $blocked['payload_schema_reason_code'],
        );
        $this->assertFalse($blocked['pseudo_city_code_source_present']);
    }

    public function test_corrected_payload_reports_pos_source_structure_and_preserves_context(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $payload = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        $schema = $builder->evaluateRevalidationPayloadSchema($payload);

        $this->assertTrue($schema['revalidation_payload_schema_valid']);
        $this->assertTrue($schema['pseudo_city_code_present']);
        $this->assertTrue($schema['pseudo_city_code_type_valid']);
        $this->assertTrue($schema['pseudo_city_code_non_empty']);
        $this->assertTrue($schema['pseudo_city_code_source_present']);
        $this->assertSame('shop_context_pseudo_city_code', $schema['pseudo_city_code_source_location']);
        $this->assertContains('PseudoCityCode', $schema['source_child_keys']);
        $this->assertContains('RequestorID', $schema['source_child_keys']);
        $this->assertTrue($schema['requestor_id_present']);
        $this->assertTrue($schema['root_version_present']);
        $this->assertTrue($schema['pricing_context_present']);
    }

    public function test_payload_freeze_fingerprint_changes_after_pseudo_city_code_correction(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $draft = $this->twoSegmentConnectingDraft();
        $corrected = $builder->buildPayload($draft, 'bfm_revalidate_v1');
        $legacy = $builder->buildPayload($draft, 'bfm_revalidate_v1');
        unset($legacy['OTA_AirLowFareSearchRQ']['POS']['Source'][0]['PseudoCityCode']);
        $legacy['_ota_revalidate_pcc_source_location'] = null;

        $this->assertNotSame(
            $builder->revalidationPayloadFreezeFingerprint($corrected, $draft),
            $builder->revalidationPayloadFreezeFingerprint($legacy, $draft),
        );
    }

    public function test_safe_structural_output_excludes_raw_pseudo_city_code_value(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $schema = $builder->evaluateRevalidationPayloadSchema(
            $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1'),
        );
        $encoded = json_encode($schema);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('"ABCD"', $encoded);
    }

    public function test_invalid_send_attempt_is_blocked_before_supplier_call(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $tampered = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        unset($tampered['OTA_AirLowFareSearchRQ']['POS']['Source'][0]['PseudoCityCode']);
        $tampered['_ota_revalidate_pcc_source_location'] = null;

        $schema = $builder->evaluateRevalidationPayloadSchema($tampered);
        $service = $this->app->make(SabreBookingService::class);
        $messageMethod = new \ReflectionMethod($service, 'revalidationPayloadSchemaBlockMessage');
        $messageMethod->setAccessible(true);
        $message = $messageMethod->invoke($service, $schema, 'bfm_revalidate_v1');

        $this->assertFalse($schema['revalidation_payload_schema_valid']);
        $this->assertSame(
            SabreRevalidationPayloadBuilder::REASON_MISSING_OR_INVALID_PSEUDO_CITY_CODE,
            $schema['payload_schema_reason_code'],
        );
        $this->assertStringContainsString('PseudoCityCode', $message);
    }

    public function test_structural_comparator_reports_compatible_bfm_with_pseudo_city_code(): void
    {
        $comparator = $this->app->make(SabreRevalidationPayloadStructuralSchemaComparator::class);
        $bfm = $comparator->compareForDraft($this->twoSegmentConnectingDraft())['styles']['bfm_revalidate_v1'] ?? [];
        $this->assertSame('compatible', $bfm['schema_compatibility_verdict'] ?? null);
        $this->assertTrue($bfm['pseudo_city_code_present'] ?? false);
        $this->assertTrue($bfm['pseudo_city_code_type_valid'] ?? false);
        $this->assertTrue($bfm['pseudo_city_code_source_present'] ?? false);
        $this->assertContains('PseudoCityCode', $bfm['source_child_keys'] ?? []);
    }
}
