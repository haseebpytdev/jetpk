<?php

namespace Tests\Unit;

use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategyDigest;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategyRegistry;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SabreGdsV25PnrPayloadMarkersTest extends TestCase
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
            '_sabre_pseudo_city_code' => 'AB12',
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
            'passengers' => [[
                'type' => 'ADT',
                'first_name' => 'Test',
                'last_name' => 'Traveler',
                'gender' => 'MALE',
                'date_of_birth' => '1990-01-15',
            ]],
            'contact' => [
                'email' => 'booker@example.com',
                'phone' => '3001234567',
            ],
            '_requires_passport_doc' => false,
            '_sabre_booking_context' => [
                'validating_carrier' => 'PK',
                'brand_code' => 'FL',
                'selected_brand_code' => 'FL',
                'fare_basis_codes_by_segment' => ['VOWFL/V'],
                'booking_classes_by_segment' => ['V'],
                'selected_price_total' => 88623,
            ],
            'fare' => ['amount' => 88623, 'currency' => 'PKR'],
        ];
    }

    public function test_v25_wire_maps_fare_basis_and_validating_carrier_from_context(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $raw = $builder->buildPassengerRecordsV25GdsWire($this->freedomPkDraft(), []);
        $wire = $builder->stripOtaInternalKeysFromBookingWire($raw);

        $this->assertSame(SabreBookingPayloadBuilder::PASSENGER_RECORDS_V2_5_GDS, $raw['_ota_payload_schema'] ?? null);
        $this->assertSame('2.5.0', $wire['CreatePassengerNameRecordRQ']['version'] ?? null);
        $this->assertSame('PK', data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.FlightQualifiers.VendorPrefs.Airline.Code'
        ));
        $this->assertSame('VOWFL/V', data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.CommandPricing.0.FareBasis.Code'
        ));
        $brandNode = data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.Brand'
        );
        $this->assertNull($brandNode);
        $this->assertNull(data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.ItineraryOptions'
        ));
        $digest = $builder->summarizeV25AirPricePricingQualifiersStructuralDigest($wire, [
            'brand_code' => 'FL',
            'selected_brand_code' => 'FL',
        ]);
        $this->assertFalse((bool) ($digest['brand_qualifier_present'] ?? true));
        $this->assertTrue((bool) ($digest['selected_brand_code_present'] ?? false));
    }

    public function test_v25_wire_includes_pnr_only_manual_ticketing_marker(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildPassengerRecordsV25GdsWire($this->freedomPkDraft(), [])
        );

        $this->assertSame(
            '7TAW',
            data_get($wire, 'CreatePassengerNameRecordRQ.TravelItineraryAddInfo.AgencyInfo.Ticketing.TicketType')
        );
    }

    public function test_v25_validation_passes_with_ticketing_enabled_false_and_no_airticket(): void
    {
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.ticketing_enabled', false);

        $builder = app(SabreBookingPayloadBuilder::class);
        $raw = $builder->buildPassengerRecordsV25GdsWire($this->freedomPkDraft(), []);
        $wire = $builder->stripOtaInternalKeysFromBookingWire($raw);
        $diag = $builder->summarizeTraditionalPnrWirePostBody(
            $wire,
            null,
            SabreBookingPayloadBuilder::PASSENGER_RECORDS_V2_5_GDS,
        );

        $this->assertTrue((bool) ($diag['wire_traditional_pnr_contract_valid'] ?? false));
        $this->assertTrue((bool) ($diag['manual_ticketing_marker_present'] ?? false));
        $this->assertTrue((bool) ($diag['fare_basis_present'] ?? false));
        $this->assertTrue((bool) ($diag['validating_carrier_present'] ?? false));
        $this->assertFalse((bool) ($diag['ticket_issuance_attempted'] ?? true));
        $this->assertFalse((bool) ($diag['airticket_attempted'] ?? true));
        $this->assertFalse((bool) ($diag['ticketing_enabled_required_for_pnr'] ?? true));
        $this->assertTrue((bool) ($diag['ticket_issuance_disabled_ok'] ?? false));
    }

    public function test_v25_validation_passes_when_ticketing_enabled_in_config_but_pnr_only(): void
    {
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.ticketing_enabled', true);

        $builder = app(SabreBookingPayloadBuilder::class);
        $raw = $builder->buildPassengerRecordsV25GdsWire($this->freedomPkDraft(), []);
        $wire = $builder->stripOtaInternalKeysFromBookingWire($raw);
        $diag = $builder->summarizeTraditionalPnrWirePostBody(
            $wire,
            null,
            SabreBookingPayloadBuilder::PASSENGER_RECORDS_V2_5_GDS,
        );

        $this->assertTrue((bool) ($diag['wire_traditional_pnr_contract_valid'] ?? false));
        $invalid = is_array($diag['wire_invalid_traditional_pnr_contract_keys'] ?? null)
            ? $diag['wire_invalid_traditional_pnr_contract_keys']
            : [];
        $this->assertNotContains('ticketing_enabled_in_config', $invalid);
        $customerMsg = $builder->buildTraditionalPnrPayloadValidationCustomerSafeMessage($invalid);
        $this->assertStringNotContainsString('ticketing_enabled_in_config', $customerMsg);
    }

    public function test_strategy_digest_reports_v25_context_markers_present(): void
    {
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.ticketing_enabled', false);

        $builder = app(SabreBookingPayloadBuilder::class);
        $raw = $builder->buildPassengerRecordsCpnrWireForStyle(
            $this->freedomPkDraft(),
            [],
            SabreBookingPayloadBuilder::PASSENGER_RECORDS_V2_5_GDS,
        );
        $wire = $builder->stripOtaInternalKeysFromBookingWire($raw);
        $envelopeDiag = $builder->summarizeEnvelopeForDiagnostics($raw);
        $tradDiag = $builder->summarizeTraditionalPnrWirePostBody(
            $wire,
            null,
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
        );

        $fareBasisPresent = (bool) ($envelopeDiag['has_fare_basis'] ?? false)
            || (bool) ($tradDiag['fare_basis_present'] ?? false)
            || (bool) ($tradDiag['wire_airprice_has_fare_basis'] ?? false);
        $validatingCarrierPresent = (bool) ($envelopeDiag['has_validating_carrier'] ?? false)
            || (bool) ($tradDiag['validating_carrier_present'] ?? false)
            || (bool) ($tradDiag['wire_airprice_has_validating_carrier'] ?? false);

        $this->assertTrue($fareBasisPresent);
        $this->assertTrue($validatingCarrierPresent);
        $this->assertTrue((bool) ($tradDiag['manual_ticketing_marker_present'] ?? false));
    }
}
