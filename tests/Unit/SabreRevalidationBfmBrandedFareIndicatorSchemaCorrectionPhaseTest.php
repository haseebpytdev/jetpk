<?php

namespace Tests\Unit;

use App\Enums\SupplierProvider;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Services\Suppliers\Sabre\Diagnostics\SabreRevalidationPayloadStructuralSchemaComparator;
use App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder;
use Tests\TestCase;

/**
 * Phase SABRE-REVALIDATION-BFM-BRANDED-FARE-INDICATOR-SCHEMA-CORRECTION-1
 */
class SabreRevalidationBfmBrandedFareIndicatorSchemaCorrectionPhaseTest extends TestCase
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

    public function test_bfm_revalidate_v1_omits_branded_fare_indicators_on_price_request_information(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $payload = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');

        $this->assertNull(data_get(
            $payload,
            'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators',
        ));
        $this->assertNull(data_get(
            $payload,
            'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions',
        ));
        $this->assertSame('PKR', data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.CurrencyCode'));
    }

    public function test_schema_validator_rejects_lowercase_single_branded_fare_before_http(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $tampered = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        data_set(
            $tampered,
            'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators.singleBrandedFare',
            true,
        );

        $blocked = $builder->evaluateRevalidationPayloadSchema($tampered);
        $this->assertFalse($blocked['revalidation_payload_schema_valid']);
        $this->assertSame(
            SabreRevalidationPayloadBuilder::REASON_UNSUPPORTED_SINGLE_BRANDED_FARE,
            $blocked['payload_schema_reason_code'],
        );
        $this->assertTrue($blocked['contains_unsupported_single_branded_fare']);
        $this->assertSame(['singleBrandedFare'], $blocked['unsupported_branded_fare_indicator_keys']);
        $this->assertSame(['singleBrandedFare'], $blocked['branded_fare_indicator_child_keys']);
        $this->assertSame(['singleBrandedFare' => 'boolean'], $blocked['branded_fare_indicator_child_types']);
        $this->assertContains(
            '$.OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators.singleBrandedFare',
            $blocked['invalid_schema_paths'],
        );
    }

    public function test_invalid_send_attempt_is_blocked_before_supplier_call(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $tampered = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        data_set(
            $tampered,
            'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators.singleBrandedFare',
            true,
        );

        $schema = $builder->evaluateRevalidationPayloadSchema($tampered);
        $this->assertFalse($schema['revalidation_payload_schema_valid']);
        $this->assertSame(
            SabreRevalidationPayloadBuilder::REASON_UNSUPPORTED_SINGLE_BRANDED_FARE,
            $schema['payload_schema_reason_code'],
        );

        $service = $this->app->make(SabreBookingService::class);
        $messageMethod = new \ReflectionMethod($service, 'revalidationPayloadSchemaBlockMessage');
        $messageMethod->setAccessible(true);
        $message = $messageMethod->invoke($service, $schema, 'bfm_revalidate_v1');
        $this->assertStringContainsString('singleBrandedFare', $message);
        $this->assertStringContainsString('not accepted', $message);
    }

    public function test_corrected_payload_preserves_branded_fare_booking_class_and_pricing_context(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $payload = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        $schema = $builder->evaluateRevalidationPayloadSchema($payload);

        $this->assertTrue($schema['revalidation_payload_schema_valid']);
        $this->assertFalse($schema['contains_unsupported_single_branded_fare']);
        $this->assertSame([], $schema['unsupported_branded_fare_indicator_keys']);
        $this->assertSame([], $schema['branded_fare_indicator_child_keys']);
        $this->assertTrue($schema['branded_fare_context_present']);
        $this->assertStringContainsString('shop_context_brand_code', (string) $schema['branded_fare_context_location']);
        $this->assertStringContainsString('shop_context_selected_brand_code', (string) $schema['branded_fare_context_location']);
        $this->assertTrue($schema['booking_class_context_present']);
        $this->assertTrue($schema['cabin_context_present']);
        $this->assertTrue($schema['fare_basis_context_present']);
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

    public function test_payload_freeze_fingerprint_changes_after_branded_fare_indicator_removal(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $draft = $this->twoSegmentConnectingDraft();
        $corrected = $builder->buildPayload($draft, 'bfm_revalidate_v1');
        $legacy = $builder->buildPayload($draft, 'bfm_revalidate_v1');
        data_set(
            $legacy,
            'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators.singleBrandedFare',
            true,
        );

        $this->assertNotSame(
            $builder->revalidationPayloadFreezeFingerprint($corrected, $draft),
            $builder->revalidationPayloadFreezeFingerprint($legacy, $draft),
        );
    }

    public function test_safe_structural_output_excludes_raw_brand_values(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $schema = $builder->evaluateRevalidationPayloadSchema(
            $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1'),
        );
        $encoded = json_encode($schema);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('"ECON"', $encoded);
    }

    public function test_structural_comparator_reports_compatible_bfm_without_single_branded_fare(): void
    {
        $comparator = $this->app->make(SabreRevalidationPayloadStructuralSchemaComparator::class);
        $bfm = $comparator->compareForDraft($this->twoSegmentConnectingDraft())['styles']['bfm_revalidate_v1'] ?? [];
        $this->assertSame('compatible', $bfm['schema_compatibility_verdict'] ?? null);
        $this->assertFalse($bfm['contains_unsupported_single_branded_fare'] ?? true);
        $this->assertTrue($bfm['branded_fare_context_present'] ?? false);
        $this->assertTrue($bfm['booking_class_context_present'] ?? false);
        $this->assertTrue($bfm['fare_basis_context_present'] ?? false);
        $this->assertTrue($bfm['pricing_context_present'] ?? false);
    }

    public function test_booking_service_schema_block_contract_for_single_branded_fare(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $tampered = $builder->buildPayload($this->twoSegmentConnectingDraft(), 'bfm_revalidate_v1');
        data_set(
            $tampered,
            'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators.singleBrandedFare',
            true,
        );

        $schema = $builder->evaluateRevalidationPayloadSchema($tampered);
        $service = $this->app->make(SabreBookingService::class);
        $messageMethod = new \ReflectionMethod($service, 'revalidationPayloadSchemaBlockMessage');
        $messageMethod->setAccessible(true);
        $message = $messageMethod->invoke($service, $schema, 'bfm_revalidate_v1');

        $this->assertFalse($schema['revalidation_payload_schema_valid']);
        $this->assertSame(
            SabreRevalidationPayloadBuilder::REASON_UNSUPPORTED_SINGLE_BRANDED_FARE,
            $schema['payload_schema_reason_code'],
        );
        $this->assertStringContainsString('singleBrandedFare', $message);
    }
}
