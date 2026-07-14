<?php

namespace Tests\Unit;

use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Support\Sabre\SabrePassengerRecordsPayloadDigest;
use Tests\TestCase;

class SabreAirPriceValidatingCarrierTest extends TestCase
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

    public function test_iati_like_builder_emits_airprice_validating_carrier_from_draft(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($this->minimalDraft('QR'), [])
        );

        $this->assertSame(
            'QR',
            data_get(
                $wire,
                'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.FlightQualifiers.VendorPrefs.Airline.Code'
            )
        );
        $this->assertNull(data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.ValidatingCarrier'
        ));
        $this->assertNotEmpty(data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.Brand'
        ));
        $this->assertNotEmpty(data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.PassengerType'
        ));
    }

    public function test_digest_detects_airprice_validating_carrier_present_and_matching(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($this->minimalDraft('QR'), [])
        );

        $digest = app(SabrePassengerRecordsPayloadDigest::class)->digest($wire, [
            'payload_style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            'validating_carrier' => 'QR',
            'brand_code' => 'ECONVENIEN',
            'selected_context_segments' => [
                [
                    'marketing_carrier' => 'QR',
                    'flight_number' => '621',
                    'origin' => 'LHE',
                    'destination' => 'DOH',
                    'booking_class' => 'O',
                    'departure_datetime' => '2026-07-23T03:10:00',
                ],
            ],
        ]);

        $this->assertTrue($digest['airprice_validating_carrier_present']);
        $this->assertSame('QR', $digest['airprice_validating_carrier']);
        $this->assertTrue($digest['context_comparison']['validating_carrier_match']);
        $this->assertFalse($digest['hard_no_fares_rbd_carrier_risk']);
        $this->assertSame('pass', $digest['cpnr_schema_validation_status']);
        $this->assertTrue($digest['post_f9i_payload_digest_clean']);
    }

    public function test_digest_flags_missing_airprice_validating_carrier_as_hard_risk(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $this->minimalDraft('QR');
        unset($draft['validating_carrier']);
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($draft, [])
        );

        $digest = app(SabrePassengerRecordsPayloadDigest::class)->digest($wire, [
            'payload_style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            'validating_carrier' => 'QR',
            'selected_context_segments' => [],
        ]);

        $this->assertFalse($digest['airprice_validating_carrier_present']);
        $this->assertContains('airprice_missing_validating_carrier', $digest['hard_no_fares_rbd_carrier_risk_reasons']);
    }
}
