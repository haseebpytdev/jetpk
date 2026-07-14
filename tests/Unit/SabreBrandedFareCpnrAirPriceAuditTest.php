<?php

namespace Tests\Unit;

use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SabreBrandedFareCpnrAirPriceAuditTest extends TestCase
{
    protected function freedomMinimalDraft(): array
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
                    'booking_class' => 'V',
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
            '_sabre_booking_context' => [
                'brand_code' => 'FL',
            ],
        ];
    }

    public function test_freedom_fl_produces_array_of_content_objects_on_iati_like_wire(): void
    {
        Config::set('suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled', false);

        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $this->freedomMinimalDraft();
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($draft, [])
        );

        $brand = data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.Brand.0.content'
        );
        $this->assertSame('FL', $brand);

        $summary = $builder->summarizeAirPriceBrandQualifierForInspect(
            $draft,
            $wire,
            SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            [
                'selected_fare_family_option' => [
                    'brand_name' => 'FREEDOM',
                    'brand_code' => 'FL',
                    'fare_option_key' => 'fl-pi3',
                ],
            ],
        );

        $this->assertSame('FL', $summary['selected_fare_family_brand_code']);
        $this->assertSame('FL', $summary['resolved_brand_code_for_wire']);
        $this->assertSame('array_of_content_objects', $summary['current_brand_node_shape']);
        $this->assertSame('array_of_content_objects', $summary['default_brand_node_shape']);
        $this->assertFalse($summary['rejected_shape_is_array_of_code_objects']);
        $this->assertSame(
            SabreBookingPayloadBuilder::AIRPRICE_BRAND_REJECTED_POINTER,
            $summary['rejected_pointer_expected']
        );
        $this->assertSame([['content' => '[scalar]']], $summary['current_brand_node_json_preview']);
        $this->assertFalse($summary['compare_gate_enabled']);
        $this->assertSame('object_content', $summary['active_brand_shape_selector']);
        $this->assertSame(SabreBookingPayloadBuilder::DEFAULT_AIRPRICE_BRAND_SHAPE_SELECTOR, $summary['default_brand_shape_selector']);
    }

    public function test_default_gate_off_keeps_array_of_content_objects_on_wire(): void
    {
        Config::set('suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled', false);

        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $this->freedomMinimalDraft();
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($draft, [])
        );

        $brandNode = data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.Brand'
        );
        $this->assertSame([['content' => 'FL']], $brandNode);
        $this->assertSame('array_of_content_objects', $builder->classifyAirPriceBrandNodeShape($brandNode));
    }

    public function test_gate_off_default_does_not_require_compare_gate(): void
    {
        Config::set('suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled', false);

        $builder = app(SabreBookingPayloadBuilder::class);
        $this->assertSame('object_content', $builder->resolveActiveAirPriceBrandShapeSelector());
        $this->assertSame(SabreBookingPayloadBuilder::DEFAULT_AIRPRICE_BRAND_SHAPE_SELECTOR, SabreBookingPayloadBuilder::DEFAULT_AIRPRICE_BRAND_SHAPE_SELECTOR);

        $draft = $this->freedomMinimalDraft();
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($draft, [])
        );

        $this->assertSame([['content' => 'FL']], data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.Brand'
        ));
    }

    public function test_compare_gate_on_string_array_produces_array_of_strings_on_wire(): void
    {
        Config::set('suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled', true);
        Config::set('suppliers.sabre.branded_fares_airprice_brand_shape_compare_variant', 'string_array');

        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $this->freedomMinimalDraft();
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($draft, [])
        );

        $brandNode = data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.Brand'
        );
        $this->assertSame(['FL'], $brandNode);
        $this->assertNull(data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.Brand.0.Code'
        ));
        $this->assertSame('array_of_strings', $builder->classifyAirPriceBrandNodeShape($brandNode));
    }

    public function test_selected_fare_family_brand_code_merged_into_sabre_booking_context(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);

        $sanitized = $builder->sanitizeSelectedFareFamilyForSabreContext([
            'brand_name' => 'FREEDOM',
            'brand_code' => 'FL',
            'option_key' => 'fl-pi3',
            'baggage_summary' => '30 KG',
            'booking_class' => 'V',
            'fare_basis' => 'VOWFL/V',
            'price_display' => 'Approx. PKR 90,062',
        ], 'fl-pi3');

        $this->assertSame('FL', $sanitized['brand_code']);
        $this->assertSame('fl-pi3', $sanitized['fare_option_key']);
        $this->assertSame('30 KG', $sanitized['baggage']);

        $merged = $builder->mergeSelectedFareFamilyIntoSabreBookingContext([], $sanitized);
        $this->assertSame('FL', $merged['brand_code']);
        $this->assertSame('FL', $merged['selected_brand_code']);
        $this->assertSame('FL', $merged['selected_fare_family_option']['brand_code']);

        $draft = $this->freedomMinimalDraft();
        unset($draft['_sabre_booking_context']);
        $draft['_sabre_booking_context'] = $merged;

        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($draft, [])
        );

        $this->assertSame('FL', data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.Brand.0.content'
        ));

        $summary = $builder->summarizeAirPriceBrandQualifierForInspect(
            $draft,
            $wire,
            SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            [
                'selected_fare_family_option' => $sanitized,
            ],
        );

        $this->assertSame('FL', $summary['resolved_brand_code_for_wire']);
        $this->assertTrue($summary['selected_fare_family_option_merged']);
        $this->assertSame('FL', $summary['merged_context_brand_code']);
    }

    public function test_inspect_diagnostics_classify_current_and_candidate_shapes(): void
    {
        Config::set('suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled', true);
        Config::set('suppliers.sabre.branded_fares_airprice_brand_shape_compare_variant', 'string_array');

        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $this->freedomMinimalDraft();
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($draft, [])
        );

        $summary = $builder->summarizeAirPriceBrandQualifierForInspect(
            $draft,
            $wire,
            SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            null,
        );

        $this->assertTrue($summary['compare_gate_enabled']);
        $this->assertSame('string_array', $summary['compare_variant']);
        $this->assertSame('string_array', $summary['active_brand_shape_selector']);
        $this->assertSame('array_of_strings', $summary['current_brand_node_shape']);
        $this->assertSame('array_of_strings', $summary['candidate_brand_node_shape']);
        $this->assertFalse($summary['live_call_attempted']);
        $this->assertArrayHasKey('candidate_shapes', $summary);
        $this->assertSame(['FL'], $summary['candidate_shapes']['string_array']);

        Config::set('suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled', false);
        $wireDefault = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($draft, [])
        );
        $summaryDefault = $builder->summarizeAirPriceBrandQualifierForInspect(
            $draft,
            $wireDefault,
            SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            null,
        );

        $this->assertSame('object_content', $summaryDefault['active_brand_shape_selector']);
        $this->assertSame('array_of_content_objects', $summaryDefault['current_brand_node_shape']);
        $this->assertSame('array_of_content_objects', $summaryDefault['default_brand_node_shape']);
        $this->assertArrayNotHasKey('candidate_brand_node_shape', $summaryDefault);
    }

    public function test_traditional_v25_wire_has_no_brand_qualifier(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $this->freedomMinimalDraft();
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($draft, [])
        );

        $this->assertNull(data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.Brand'
        ));

        $summary = $builder->summarizeAirPriceBrandQualifierForInspect(
            $draft,
            $wire,
            SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
            null,
        );

        $this->assertSame('absent', $summary['current_brand_node_shape']);
        $this->assertFalse($summary['brand_present_on_wire']);
        $this->assertFalse($summary['rejected_shape_is_array_of_code_objects']);
    }

    public function test_candidate_shapes_emitted_when_compare_gate_enabled(): void
    {
        Config::set('suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled', true);

        $builder = app(SabreBookingPayloadBuilder::class);
        $candidates = $builder->candidateAirPriceBrandShapesForCompare('FL');

        $this->assertSame(['FL'], $candidates['string_array']);
        $this->assertSame([['Code' => 'FL']], $candidates['current_object_code']);
        $this->assertSame([['value' => 'FL']], $candidates['object_value']);
        $this->assertNull($candidates['omit_brand']);

        $draft = $this->freedomMinimalDraft();
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($draft, [])
        );
        $summary = $builder->summarizeAirPriceBrandQualifierForInspect(
            $draft,
            $wire,
            SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            null,
        );

        $this->assertArrayHasKey('candidate_shapes', $summary);
        $this->assertSame(['FL'], $summary['candidate_shapes']['string_array']);
    }

    public function test_candidate_shapes_omitted_when_compare_gate_disabled(): void
    {
        Config::set('suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled', false);

        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $this->freedomMinimalDraft();
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($draft, [])
        );
        $summary = $builder->summarizeAirPriceBrandQualifierForInspect($draft, $wire, null, null);

        $this->assertArrayNotHasKey('candidate_shapes', $summary);
        $this->assertSame(
            SabreBookingPayloadBuilder::AIRPRICE_BRAND_SHAPE_COMPARE_VARIANTS,
            $summary['candidate_shape_keys']
        );
    }

    /**
     * @return array<string, array{0: string, 1: mixed, 2: string}>
     */
    public static function bf7dBrandShapeVariantsProvider(): array
    {
        return [
            'current_object_code' => ['current_object_code', [['Code' => 'FL']], 'array_of_code_objects'],
            'string_array' => ['string_array', ['FL'], 'array_of_strings'],
            'empty_object_array' => ['empty_object_array', [[]], 'array_of_empty_objects'],
            'object_value' => ['object_value', [['value' => 'FL']], 'array_of_value_objects'],
            'object_content' => ['object_content', [['content' => 'FL']], 'array_of_content_objects'],
            'object_text' => ['object_text', [['text' => 'FL']], 'array_of_text_objects'],
            'single_object_code' => ['single_object_code', ['Code' => 'FL'], 'single_code_object'],
            'single_object_value' => ['single_object_value', ['value' => 'FL'], 'single_value_object'],
            'single_object_content' => ['single_object_content', ['content' => 'FL'], 'single_content_object'],
            'omit_brand' => ['omit_brand', null, 'absent'],
        ];
    }

    #[DataProvider('bf7dBrandShapeVariantsProvider')]
    public function test_bf7d_compare_gate_variant_produces_expected_brand_node(
        string $variant,
        mixed $expectedBrandNode,
        string $expectedShape,
    ): void {
        Config::set('suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled', true);
        Config::set('suppliers.sabre.branded_fares_airprice_brand_shape_compare_variant', $variant);

        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $this->freedomMinimalDraft();
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($draft, [])
        );

        $brandNode = data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.Brand'
        );

        if ($expectedBrandNode === null) {
            $this->assertNull($brandNode);
        } else {
            $this->assertSame($expectedBrandNode, $brandNode);
        }
        $this->assertSame($expectedShape, $builder->classifyAirPriceBrandNodeShape($brandNode));
        $this->assertSame($variant, $builder->resolveActiveAirPriceBrandShapeSelector());
    }

    public function test_gate_off_ignores_variant_env_and_keeps_object_content_array(): void
    {
        Config::set('suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled', false);
        Config::set('suppliers.sabre.branded_fares_airprice_brand_shape_compare_variant', 'object_value');

        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $this->freedomMinimalDraft();
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($draft, [])
        );

        $brandNode = data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.Brand'
        );
        $this->assertSame([['content' => 'FL']], $brandNode);
        $this->assertSame('object_content', $builder->resolveActiveAirPriceBrandShapeSelector());
    }

    public function test_unsupported_variant_not_in_registry(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);

        $this->assertFalse($builder->isSupportedAirPriceBrandShapeCompareVariant('not_a_real_variant'));
        $this->assertTrue($builder->isSupportedAirPriceBrandShapeCompareVariant('object_value'));
        $this->assertCount(10, $builder->supportedAirPriceBrandShapeCompareVariants());
    }

    public function test_classify_brand_node_shapes(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);

        $this->assertSame('absent', $builder->classifyAirPriceBrandNodeShape(null));
        $this->assertSame('array_of_content_objects', $builder->classifyAirPriceBrandNodeShape([['content' => 'FL']]));
        $this->assertSame('array_of_code_objects', $builder->classifyAirPriceBrandNodeShape([['Code' => 'FL']]));
        $this->assertSame('array_of_strings', $builder->classifyAirPriceBrandNodeShape(['FL']));
        $this->assertSame('array_of_empty_objects', $builder->classifyAirPriceBrandNodeShape([[]]));
        $this->assertSame('array_of_value_objects', $builder->classifyAirPriceBrandNodeShape([['value' => 'FL']]));
        $this->assertSame('scalar_string', $builder->classifyAirPriceBrandNodeShape('FL'));
        $this->assertSame('single_code_object', $builder->classifyAirPriceBrandNodeShape(['Code' => 'FL']));
        $this->assertSame('single_value_object', $builder->classifyAirPriceBrandNodeShape(['value' => 'FL']));
    }

    public function test_summarize_is_pure_no_network_side_effects(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $this->freedomMinimalDraft();
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($draft, [])
        );

        $summary = $builder->summarizeAirPriceBrandQualifierForInspect($draft, $wire, null, null);
        $this->assertSame('array_of_content_objects', $summary['current_brand_node_shape']);
        $this->assertFalse($summary['live_call_attempted']);
        $this->assertArrayNotHasKey('http_status', $summary);
    }
}
