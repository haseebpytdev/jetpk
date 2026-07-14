<?php

namespace Tests\Unit;

use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Support\Sabre\SabreCpnrIatiWireSchemaValidator;
use Tests\TestCase;

class SabreCpnrIatiWireSchemaValidationTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function minimalDraft(string $vc = 'QR'): array
    {
        return [
            '_valid' => true,
            'supplier_connection_id' => 1,
            '_sabre_pseudo_city_code' => 'AB12',
            'validating_carrier' => $vc,
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DOH',
                    'carrier' => 'QR',
                    'flight_number' => '621',
                    'departure_at' => '2026-07-23T03:10:00',
                    'arrival_at' => '2026-07-23T06:40:00',
                    'booking_class' => 'O',
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
            '_sabre_booking_context' => ['brand_code' => 'ECONVENIEN'],
        ];
    }

    public function test_iati_wire_passes_local_cpnr_schema_validation(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($this->minimalDraft('QR'), [])
        );

        $summary = app(SabreCpnrIatiWireSchemaValidator::class)->validateCpnrEnvelope($wire);

        $this->assertSame('pass', $summary['cpnr_schema_validation_status']);
        $this->assertFalse($summary['cpnr_schema_validation_failed']);
        $this->assertSame('pre_http', $summary['cpnr_schema_validation_stage']);
    }

    public function test_validating_carrier_under_pricing_qualifiers_fails_schema_validation(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($this->minimalDraft('QR'), [])
        );
        data_set(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.ValidatingCarrier',
            ['Code' => 'QR'],
        );

        $summary = app(SabreCpnrIatiWireSchemaValidator::class)->validateCpnrEnvelope($wire);

        $this->assertSame('fail', $summary['cpnr_schema_validation_status']);
        $this->assertTrue($summary['cpnr_schema_validation_failed']);
        $this->assertStringContainsString('PricingQualifiers', (string) $summary['cpnr_schema_validation_pointer']);
        $this->assertContains('ValidatingCarrier', $summary['cpnr_schema_validation_rejected_keys_sample']);
    }

    public function test_schema_validation_failure_outputs_safe_pointer_and_message_only(): void
    {
        $validator = app(SabreCpnrIatiWireSchemaValidator::class);
        $cpnr = [
            'AirPrice' => [
                [
                    'PriceRequestInformation' => [
                        'OptionalQualifiers' => [
                            'PricingQualifiers' => [
                                'PassengerType' => [['Code' => 'ADT', 'Quantity' => '1']],
                                'ValidatingCarrier' => ['Code' => 'QR'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $summary = $validator->validateIatiLikeCpnrV24AirPrice($cpnr);

        $encoded = json_encode($summary);
        $this->assertStringNotContainsString('booker@', $encoded);
        $this->assertStringNotContainsString('Traveler', $encoded);
        $this->assertNotEmpty($summary['cpnr_schema_validation_message_summary']);
    }

    public function test_brand_and_passenger_type_remain_allowed_under_pricing_qualifiers(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($this->minimalDraft('QR'), [])
        );
        $cpnr = $wire['CreatePassengerNameRecordRQ'] ?? [];
        $pq = data_get($cpnr, 'AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers');

        $this->assertNotEmpty($pq['PassengerType'] ?? null);
        $this->assertNotEmpty($pq['Brand'] ?? null);
        $this->assertNull($pq['ValidatingCarrier'] ?? null);
    }

    public function test_outcome_classifier_detects_sabre_schema_validation_without_application_digest(): void
    {
        $validator = app(SabreCpnrIatiWireSchemaValidator::class);

        $this->assertTrue($validator->outcomeLooksLikeCpnrSchemaValidationFailure(
            'sabre_booking_validation_failed',
            'Sabre booking validation failed: pointer: /CreatePassengerNameRecordRQ/AirPrice/0/PriceRequestInformation/OptionalQualifiers/PricingQualifiers, message: object instance has properties which are',
            false,
        ));
        $this->assertFalse($validator->outcomeLooksLikeCpnrSchemaValidationFailure(
            'sabre_booking_application_error',
            'Unable to perform air booking step',
            true,
        ));
    }
}
