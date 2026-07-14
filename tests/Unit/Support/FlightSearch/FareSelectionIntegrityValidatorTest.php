<?php

namespace Tests\Unit\Support\FlightSearch;

use App\Data\SelectedFareContext;
use App\Data\SelectedFareOption;
use App\Enums\SupplierProvider;
use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Support\Bookings\BookingSupplierConfirmationNoticeResolver;
use App\Support\FlightSearch\FareSelectionIntegrityValidator;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use Tests\TestCase;

class FareSelectionIntegrityValidatorTest extends TestCase
{
    public function test_selected_fl_brand_does_not_match_lt_fare_basis_in_handoff(): void
    {
        $meta = [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => 2,
            'distribution_channel' => 'gds',
            'fare_option_key' => 'freedom-key',
            'selected_fare_family_option' => [
                'option_key' => 'freedom-key',
                'brand_code' => 'FL',
                'brand_name' => 'FREEDOM',
                'fare_basis' => 'VOWFL',
                'fare_basis_codes_by_segment' => ['VOWFL'],
                'booking_classes_by_segment' => ['V'],
                'baggage_summary' => '30kg',
                'displayed_price' => 88602,
            ],
            'sabre_booking_context' => [
                'selected_brand_code' => 'FL',
                'brand_code' => 'FL',
                'fare_basis_codes_by_segment' => ['VOWNBAG'],
                'booking_classes_by_segment' => ['V'],
            ],
        ];

        $result = (new FareSelectionIntegrityValidator)->validate($meta);

        $this->assertFalse($result['consistent']);
        $this->assertSame('branded_fare_context_mismatch', $result['reason_code']);
        $this->assertContains('fare_basis', $result['mismatch_fields']);
        $this->assertTrue($result['blocks_pnr_creation']);
    }

    public function test_reconcile_sabre_branded_fare_meta_propagates_segment_fare_basis(): void
    {
        $meta = [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'distribution_channel' => 'gds',
            'fare_option_key' => 'freedom-key',
            'selected_fare_family_option' => [
                'option_key' => 'freedom-key',
                'brand_code' => 'FL',
                'name' => 'FREEDOM',
                'fare_basis' => 'VOWFL',
                'fare_basis_codes_by_segment' => ['VOWFL'],
                'booking_classes_by_segment' => ['V'],
                'baggage_summary' => '30kg',
            ],
            'sabre_booking_context' => [
                'brand_code' => 'LT',
                'fare_basis_codes_by_segment' => ['VOWNBAG'],
            ],
        ];

        $reconciled = BookingSupplierConfirmationNoticeResolver::reconcileSabreBrandedFareMeta($meta);
        $handoff = $reconciled['sabre_booking_context'];

        $this->assertSame('FL', $handoff['selected_brand_code']);
        $this->assertSame(['VOWFL'], $handoff['fare_basis_codes_by_segment']);
        $this->assertSame(['V'], $handoff['booking_classes_by_segment']);
        $this->assertSame('30kg', $handoff['baggage']);

        $integrity = (new FareSelectionIntegrityValidator)->validate($reconciled);
        $this->assertTrue($integrity['consistent']);
    }

    public function test_sanitize_selected_fare_family_intent_includes_segment_arrays(): void
    {
        $offer = ['supplier_provider' => 'sabre', 'final_customer_price' => 80000];
        $resolved = [
            'option_key' => 'opt-1',
            'name' => 'FREEDOM',
            'brand_code' => 'FL',
            'fare_basis_codes_by_segment' => ['VOWFL'],
            'booking_classes_by_segment' => ['V'],
            'baggage_summary' => '30kg',
            'price_total' => 500,
            'currency' => 'USD',
        ];

        $intent = FlightOfferDisplayPresenter::sanitizeSelectedFareFamilyIntent($resolved, $offer);

        $this->assertSame(['VOWFL'], $intent['fare_basis_codes_by_segment']);
        $this->assertSame(['V'], $intent['booking_classes_by_segment']);
    }

    public function test_synthetic_default_fare_marks_branded_fare_unsupported(): void
    {
        $offer = [
            'offer_id' => 'test-offer',
            'supplier_provider' => 'duffel',
            'final_customer_price' => 50000,
            'cabin' => 'economy',
        ];

        $default = FlightOfferDisplayPresenter::buildSyntheticDefaultFareChoiceOption($offer);
        $this->assertIsArray($default);
        $intent = FlightOfferDisplayPresenter::sanitizeSelectedFareFamilyIntent($default, $offer);
        $option = SelectedFareOption::fromIntentArray($intent, $offer);

        $this->assertFalse($option->brandedFareSupported);
        $this->assertNotEmpty($option->fareOptionKey);
    }

    public function test_connection_sticky_when_meta_connection_matches(): void
    {
        $meta = [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => 2,
            'selected_fare_family_option' => ['option_key' => 'k1', 'brand_code' => 'FL'],
            'sabre_booking_context' => ['brand_code' => 'FL', 'fare_basis_codes_by_segment' => ['VOWFL']],
        ];
        $offer = ['supplier_connection_id' => 2, 'supplier_provider' => 'sabre'];

        $result = (new FareSelectionIntegrityValidator)->validate($meta, $offer);

        $this->assertTrue($result['consistent']);
        $this->assertTrue($result['admin_summary']['connection_sticky'] ?? false);
    }

    public function test_gir_archive_blank_segment_rows_are_stripped(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $archive = [
            'segment_sell_rows' => [
                ['origin' => '', 'destination' => '', 'carrier' => '', 'flight_number' => ''],
                ['origin' => 'LHE', 'destination' => 'DXB', 'carrier' => 'PK', 'flight_number' => '203', 'booking_class' => 'V'],
            ],
        ];

        $sanitized = $builder->sanitizeGirArchiveSegmentSellRows($archive);

        $this->assertCount(1, $sanitized['segment_sell_rows']);
        $this->assertSame('LHE', $sanitized['segment_sell_rows'][0]['origin']);
    }

    public function test_nn_omitted_from_halt_on_status_by_default(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $codes = $builder->resolveTraditionalCpnrHaltOnStatusCodes(iatiLike: false, omitNnWn: true);

        $this->assertNotContains('NN', $codes);
        $this->assertNotContains('WN', $codes);
    }

    public function test_selected_fare_context_from_meta(): void
    {
        $meta = [
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => 2,
            'fare_option_key' => 'k1',
            'selected_fare_family_option' => [
                'option_key' => 'k1',
                'brand_code' => 'FL',
                'fare_basis_codes_by_segment' => ['VOWFL'],
            ],
        ];

        $ctx = SelectedFareContext::fromBookingMeta($meta);

        $this->assertSame(2, $ctx->supplierConnectionId);
        $this->assertSame('FL', $ctx->selectedFare?->brandCode);
        $this->assertSame(['VOWFL'], $ctx->selectedFare?->fareBasisCodesBySegment);
    }
}
