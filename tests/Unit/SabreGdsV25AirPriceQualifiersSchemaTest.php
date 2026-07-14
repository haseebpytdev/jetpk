<?php

namespace Tests\Unit;

use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Support\Sabre\SabrePassengerRecordsHttpValidationExcerptBuilder;
use App\Support\Sabre\SabrePassengerRecordsV25WireSchemaValidator;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SabreGdsV25AirPriceQualifiersSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function freedomPkDraft(): array
    {
        return [
            '_valid' => true,
            'supplier_connection_id' => 2,
            'validating_carrier' => 'PK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'carrier' => 'PK',
                'flight_number' => '233',
                'departure_at' => '2026-08-15T08:00:00',
                'arrival_at' => '2026-08-15T11:00:00',
                'booking_class' => 'V',
                'fare_basis_code' => 'VOWFL/V',
            ]],
            'passengers' => [['type' => 'ADT', 'first_name' => 'Test', 'last_name' => 'Traveler']],
            'contact' => ['email' => 'booker@example.com', 'phone' => '3001234567'],
            '_sabre_booking_context' => [
                'validating_carrier' => 'PK',
                'brand_code' => 'FL',
                'selected_brand_code' => 'FL',
                'fare_basis_codes_by_segment' => ['VOWFL/V'],
                'booking_classes_by_segment' => ['V'],
            ],
        ];
    }

    public function test_v25_command_pricing_is_always_array_with_fare_basis_object(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildPassengerRecordsV25GdsWire($this->freedomPkDraft(), [])
        );
        $commandPricing = data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.CommandPricing'
        );
        $this->assertIsArray($commandPricing);
        $this->assertTrue(array_is_list($commandPricing));
        $this->assertSame('VOWFL/V', data_get($commandPricing, '0.FareBasis.Code'));
    }

    public function test_v25_payload_omits_brand_qualifier_by_default(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildPassengerRecordsV25GdsWire($this->freedomPkDraft(), [])
        );
        $brand = data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.Brand'
        );
        $this->assertNull($brand);
    }

    public function test_v25_payload_omits_segment_select_by_default(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildPassengerRecordsV25GdsWire($this->freedomPkDraft(), [])
        );
        $this->assertNull(data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.ItineraryOptions'
        ));
        $this->assertNull(data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.ItineraryOptions.SegmentSelect'
        ));
    }

    public function test_v25_payload_contains_only_allowed_pricing_qualifier_keys(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildPassengerRecordsV25GdsWire($this->freedomPkDraft(), [])
        );
        $pq = data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers'
        );
        $this->assertIsArray($pq);
        foreach (array_keys($pq) as $key) {
            $this->assertContains(
                $key,
                SabreBookingPayloadBuilder::V25_GDS_ALLOWED_PRICING_QUALIFIER_KEYS,
                'Unexpected PricingQualifiers key on v2.5 wire: '.$key
            );
        }
        $this->assertNotContains('Brand', array_keys($pq));
        $this->assertNotContains('ItineraryOptions', array_keys($pq));
    }

    public function test_v25_selected_brand_remains_in_context_when_wire_brand_omitted(): void
    {
        $draft = $this->freedomPkDraft();
        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildPassengerRecordsV25GdsWire($draft, [])
        );
        $ctx = $draft['_sabre_booking_context'];
        $digest = $builder->summarizeV25AirPricePricingQualifiersStructuralDigest($wire, [
            'brand_code' => $ctx['brand_code'],
            'selected_brand_code' => $ctx['selected_brand_code'],
        ]);

        $this->assertFalse((bool) ($digest['brand_qualifier_present'] ?? true));
        $this->assertSame('missing', $digest['brand_qualifier_shape'] ?? null);
        $this->assertTrue((bool) ($digest['selected_brand_code_present'] ?? false));
        $this->assertTrue((bool) ($digest['selected_brand_code_context_only'] ?? false));
        $this->assertSame(
            SabreBookingPayloadBuilder::V25_GDS_BRAND_QUALIFIER_OMITTED_REASON,
            $digest['brand_qualifier_omitted_reason'] ?? null
        );
        $this->assertFalse((bool) ($digest['segment_select_present'] ?? true));
        $this->assertSame(
            SabreBookingPayloadBuilder::V25_GDS_SEGMENT_SELECT_OMITTED_REASON,
            $digest['segment_select_omitted_reason'] ?? null
        );
    }

    public function test_v25_local_schema_validator_passes_for_certified_wire(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildPassengerRecordsV25GdsWire($this->freedomPkDraft(), [])
        );
        $summary = app(SabrePassengerRecordsV25WireSchemaValidator::class)->validateCpnrEnvelope($wire);
        $this->assertSame('pass', $summary['cpnr_schema_validation_status'] ?? null);
        $this->assertFalse((bool) ($summary['cpnr_schema_validation_failed'] ?? true));
    }

    public function test_v25_local_schema_validator_rejects_itinerary_options_before_http(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildPassengerRecordsV25GdsWire($this->freedomPkDraft(), [])
        );
        data_set(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.ItineraryOptions',
            ['SegmentSelect' => ['RPH' => '1', 'Number' => '1']]
        );
        $summary = app(SabrePassengerRecordsV25WireSchemaValidator::class)->validateCpnrEnvelope($wire);
        $this->assertSame('fail', $summary['cpnr_schema_validation_status'] ?? null);
        $this->assertStringContainsString('ItineraryOptions', (string) ($summary['cpnr_schema_validation_pointer'] ?? ''));
        $this->assertSame(
            SabreBookingPayloadBuilder::V25_AIRPRICE_OPTIONAL_QUALIFIER_SCHEMA_ERROR,
            $summary['safe_reason_code'] ?? null
        );
    }

    public function test_v25_structural_digest_reports_expected_shapes(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $this->freedomPkDraft();
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildPassengerRecordsV25GdsWire($draft, [])
        );
        $ctx = $draft['_sabre_booking_context'];
        $digest = $builder->summarizeV25AirPricePricingQualifiersStructuralDigest($wire, [
            'brand_code' => $ctx['brand_code'],
            'selected_brand_code' => $ctx['selected_brand_code'],
        ]);
        $this->assertTrue((bool) ($digest['pricing_qualifiers_present'] ?? false));
        $this->assertContains('CommandPricing', $digest['pricing_qualifier_keys'] ?? []);
        $this->assertTrue((bool) ($digest['command_pricing_present'] ?? false));
        $this->assertSame('array', $digest['command_pricing_shape'] ?? null);
        $this->assertSame('missing', $digest['brand_qualifier_shape'] ?? null);
        $this->assertFalse((bool) ($digest['brand_qualifier_present'] ?? true));
        $this->assertFalse((bool) ($digest['itinerary_options_present'] ?? true));
        $this->assertSame('missing', $digest['itinerary_options_shape'] ?? null);
        $this->assertFalse((bool) ($digest['segment_select_present'] ?? true));
        $this->assertSame('missing', $digest['segment_select_shape'] ?? null);
        $this->assertSame('object', $digest['fare_basis_shape'] ?? null);
        $this->assertTrue((bool) ($digest['fare_basis_present'] ?? false));
        $this->assertSame('string', $digest['validating_carrier_shape'] ?? null);
        $this->assertTrue((bool) ($digest['validating_carrier_present'] ?? false));
        $this->assertTrue((bool) ($digest['manual_ticketing_marker_present'] ?? false));
        $this->assertFalse((bool) ($digest['ticket_issuance_attempted'] ?? true));
        $this->assertFalse((bool) ($digest['airticket_attempted'] ?? true));
    }

    public function test_structured_http_400_excerpts_redact_to_safe_pointer_and_message(): void
    {
        $builder = app(SabrePassengerRecordsHttpValidationExcerptBuilder::class);
        $structured = $builder->buildStructuredExcerpts([
            'errors' => [[
                'source' => ['pointer' => '/CreatePassengerNameRecordRQ/AirPrice/0/PriceRequestInformation/OptionalQualifiers/PricingQualifiers/CommandPricing'],
                'detail' => 'instance type (object) is not allowed',
                'code' => 'schema_validation_failed',
            ]],
        ]);
        $this->assertCount(1, $structured);
        $this->assertStringContainsString('CommandPricing', (string) ($structured[0]['pointer'] ?? ''));
        $this->assertStringContainsString('instance type', (string) ($structured[0]['message_excerpt'] ?? ''));
        $encoded = json_encode($structured);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('passport', strtolower($encoded));
        $this->assertStringNotContainsString('booker@', strtolower($encoded));
    }

    public function test_segment_select_pointer_http_400_populates_structured_safe_fields(): void
    {
        $builder = app(SabrePassengerRecordsHttpValidationExcerptBuilder::class);
        $structured = $builder->buildStructuredExcerpts([
            'errors' => [[
                'source' => ['pointer' => SabreBookingPayloadBuilder::AIRPRICE_SEGMENT_SELECT_REJECTED_POINTER],
                'detail' => 'instance type (object) does not match schema',
                'code' => 'schema_validation_failed',
            ]],
        ]);
        $summary = $builder->extractCpnrSchemaValidationSummary($structured);
        $this->assertStringContainsString('SegmentSelect', (string) ($summary['cpnr_schema_validation_pointer'] ?? ''));
        $this->assertStringContainsString('instance type', (string) ($summary['cpnr_schema_validation_message_summary'] ?? ''));
        $this->assertSame('post_http', $summary['cpnr_schema_validation_stage'] ?? null);
        $this->assertSame(
            SabreBookingPayloadBuilder::V25_AIRPRICE_OPTIONAL_QUALIFIER_SCHEMA_ERROR,
            $builder->classifyV25AirPriceOptionalQualifierSchemaReason($structured)
        );
    }

    public function test_brand_pointer_http_400_classifies_brand_specific_reason(): void
    {
        $builder = app(SabrePassengerRecordsHttpValidationExcerptBuilder::class);
        $structured = $builder->buildStructuredExcerpts([
            'errors' => [[
                'source' => ['pointer' => SabreBookingPayloadBuilder::AIRPRICE_BRAND_REJECTED_POINTER],
                'detail' => 'instance type (string) does not match schema',
                'code' => 'schema_validation_failed',
            ]],
        ]);
        $this->assertSame(
            SabreBookingPayloadBuilder::V25_AIRPRICE_OPTIONAL_QUALIFIER_SCHEMA_ERROR,
            $builder->classifyV25AirPriceOptionalQualifierSchemaReason($structured)
        );
        $this->assertSame(
            SabreBookingPayloadBuilder::V25_BRAND_QUALIFIER_REQUIRED_OR_SHAPE_UNKNOWN,
            $builder->classifyV25BrandQualifierHostReason($structured)
        );
    }
}
