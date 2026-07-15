<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\PiaNdcOptionPnrService;
use App\Support\Bookings\PiaNdcFareFamilyPolicy;
use App\Support\Bookings\PiaNdcSelectedFareReadinessService;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PiaNdcSelectedFareReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_freedom_with_complete_provider_context_passes_readiness(): void
    {
        $offer = $this->groupedPiaOfferSnapshot();
        $freedom = collect(PiaNdcFareFamilyPolicy::collectProviderBackedBrandOptions($offer))
            ->firstWhere('name', 'FREEDOM');
        $this->assertNotNull($freedom);

        $service = app(PiaNdcSelectedFareReadinessService::class);
        Config::set('suppliers.pia_ndc.checkout_offer_price_enabled', false);

        $result = $service->evaluateForCheckout(
            $offer,
            'search-1',
            (string) ($offer['id'] ?? ''),
            (string) ($freedom['option_key'] ?? ''),
        );

        $this->assertTrue($result['ready']);
        $this->assertSame('ready', $result['readiness_status']);
        $this->assertSame('FREEDOM', $result['fare_type_code']);
        $this->assertSame('OfferItem-5', $result['offer_item_ref_id']);
    }

    public function test_missing_offer_item_ref_id_is_blocked_before_passenger_page(): void
    {
        $offer = $this->groupedPiaOfferSnapshot();
        $incomplete = [
            'name' => 'ECO LIGHT',
            'option_key' => 'eco-incomplete',
            'pia_ndc_provider_backed' => true,
            'provider_context' => [
                'shopping_response_ref_id' => 'shop-ref',
                'offer_ref_id' => 'eco-offer-ref',
                'fare_type_code' => 'ECO LIGHT',
                'pax_ref_id' => 'ADTPax-1',
                'payment_time_limit' => '2099-12-31T23:59:59',
                'pax_journey_ref_ids' => ['Journey-1'],
            ],
        ];

        $service = app(PiaNdcSelectedFareReadinessService::class);
        $result = $service->evaluateStructuralForOption($incomplete, $offer);
        $this->assertFalse($result['ready']);
        $this->assertSame('missing_offer_item_ref_id', $result['failed_reason_code']);
        $this->assertFalse($service->isOptionStructurallyReady($incomplete, $offer));
    }

    public function test_expired_payment_time_limit_is_blocked(): void
    {
        $offer = $this->groupedPiaOfferSnapshot();
        $eco = collect(PiaNdcFareFamilyPolicy::collectProviderBackedBrandOptions($offer))
            ->firstWhere('name', 'ECO LIGHT');
        $this->assertNotNull($eco);
        $eco['provider_context']['payment_time_limit'] = '2020-01-01T00:00:00';

        $result = app(PiaNdcSelectedFareReadinessService::class)->evaluateStructuralForOption($eco, $offer);
        $this->assertFalse($result['ready']);
        $this->assertSame('payment_time_limit_expired', $result['failed_reason_code']);
    }

    public function test_synthetic_branded_option_is_not_selectable_on_results(): void
    {
        $offer = $this->groupedPiaOfferSnapshot(includeSmartFreedom: false);
        $presentation = FlightOfferDisplayPresenter::buildBrandedFaresPresentationFields([
            [
                'name' => 'FREEDOM',
                'brand_name' => 'FREEDOM',
                'option_key' => 'synthetic-freedom',
                'price_total' => 28590,
                'is_synthetic_default' => true,
            ],
        ], $offer);

        $this->assertCount(1, $presentation['fare_family_options_display']);
        $this->assertSame('ECO LIGHT', $presentation['fare_family_options_display'][0]['name']);
        $this->assertFalse($presentation['branded_fares_selection_active']);
        $this->assertNotContains('FREEDOM', array_column($presentation['fare_family_options_display'], 'name'));
    }

    public function test_offer_price_unavailable_blocks_passenger_page(): void
    {
        $connection = $this->piaConnection();
        $offer = $this->groupedPiaOfferSnapshot();
        $offer['supplier_connection_id'] = $connection->id;
        $freedom = collect(PiaNdcFareFamilyPolicy::collectProviderBackedBrandOptions($offer))
            ->firstWhere('name', 'FREEDOM');
        $this->assertNotNull($freedom);

        Config::set('suppliers.pia_ndc.checkout_offer_price_enabled', true);

        Http::fake([
            'example.test/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCancelCommit_fault_500.xml')),
                500,
                ['Content-Type' => 'text/xml; charset=utf-8'],
            ),
        ]);

        $service = app(PiaNdcSelectedFareReadinessService::class);
        $result = $service->evaluateForCheckout(
            $offer,
            'search-offer-price-1',
            (string) $offer['id'],
            (string) $freedom['option_key'],
            connection: $connection,
        );

        $this->assertFalse($result['ready']);
        $this->assertTrue($result['live_offer_price_checked']);
    }

    public function test_order_create_failure_does_not_show_confirmation_success(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $connection = $this->piaConnection();
        $booking = $this->piaDraftBooking($connection);

        Http::fake([
            'example.test/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/pia-ndc/doOrderCancelCommit_fault_500.xml')),
                500,
                ['Content-Type' => 'text/xml; charset=utf-8'],
            ),
        ]);

        $result = app(PiaNdcOptionPnrService::class)->autoCreateOptionPnrForPublicBooking($booking);
        $this->assertFalse($result['success']);
        $this->assertNull($result['customer_notice']);

        $booking->refresh();
        $this->assertFalse(app(PiaNdcSelectedFareReadinessService::class)->bookingHasActiveOptionPnr($booking));
        $this->assertSame(BookingStatus::Draft, $booking->status);
    }

    /**
     * @return array<string, mixed>
     */
    private function groupedPiaOfferSnapshot(bool $includeSmartFreedom = true): array
    {
        $ecoCtx = [
            'provider' => SupplierProvider::PiaNdc->value,
            'shopping_response_ref_id' => 'b00fe7be-88f0-4de3-b455-28b5aa20f767',
            'offer_ref_id' => 'eco-offer-ref',
            'offer_item_ref_id' => 'OfferItem-1',
            'pax_ref_id' => 'ADTPax-1',
            'owner_code' => 'PK',
            'payment_time_limit' => '2099-12-31T23:59:59',
            'fare_type_code' => 'ECO LIGHT',
            'fare_basis' => 'VNBAG',
            'rbd' => 'V',
            'pax_journey_ref_ids' => ['Journey-1'],
        ];

        $fareFamilyOptions = [
            PiaNdcFareFamilyPolicy::buildProviderBackedFareFamilyOptionFromRow(
                ['supplier_provider' => SupplierProvider::PiaNdc->value, 'fare_breakdown' => ['supplier_total' => 24410]],
                ['name' => 'ECO LIGHT', 'price_total' => 24410, 'source_offer_id' => 'pia-eco'],
                $ecoCtx,
            ),
        ];

        if ($includeSmartFreedom) {
            $fareFamilyOptions[] = PiaNdcFareFamilyPolicy::buildProviderBackedFareFamilyOptionFromRow(
                ['supplier_provider' => SupplierProvider::PiaNdc->value, 'fare_breakdown' => ['supplier_total' => 28590]],
                ['name' => 'FREEDOM', 'price_total' => 28590, 'source_offer_id' => 'pia-freedom'],
                [
                    ...$ecoCtx,
                    'offer_ref_id' => 'freedom-offer-ref',
                    'offer_item_ref_id' => 'OfferItem-5',
                    'fare_type_code' => 'FREEDOM',
                    'fare_basis' => 'FNBAG',
                    'rbd' => 'F',
                ],
            );
        }

        $fareFamilyOptions = array_values(array_filter($fareFamilyOptions));

        return [
            'id' => 'pia-ndc-readiness-offer',
            'offer_id' => 'pia-ndc-readiness-offer',
            'supplier_provider' => SupplierProvider::PiaNdc->value,
            'fare_family' => 'ECO LIGHT',
            'provider_context' => $ecoCtx,
            'raw_payload' => ['provider_context' => $ecoCtx],
            'fare_breakdown' => ['supplier_total' => 24410],
            'segments' => [
                [
                    'airline_code' => 'PK',
                    'flight_number' => '301',
                    'origin' => 'KHI',
                    'destination' => 'ISB',
                    'departure_at' => '2026-07-23T10:00:00',
                    'arrival_at' => '2026-07-23T12:00:00',
                ],
            ],
            'fare_family_options' => array_values(array_filter($fareFamilyOptions)),
            'has_grouped_fare_options' => $includeSmartFreedom,
        ];
    }

    private function piaConnection(): SupplierConnection
    {
        return SupplierConnection::factory()->create([
            'provider' => SupplierProvider::PiaNdc,
            'base_url' => 'https://example.test/cranendc/v20.1/CraneNDCService',
            'credentials' => [
                'username' => 'test-user',
                'password' => 'test-pass',
                'agency_id' => 'SELENS',
                'agency_name' => 'NDC GATEWAY',
                'owner_code' => 'PK',
                'currency' => 'PKR',
                'language_code' => 'EN',
            ],
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metaOverrides
     */
    private function piaDraftBooking(SupplierConnection $connection, array $metaOverrides = []): Booking
    {
        $offer = array_merge($this->groupedPiaOfferSnapshot(), [
            'supplier_connection_id' => $connection->id,
        ]);
        $freedom = collect(PiaNdcFareFamilyPolicy::collectProviderBackedBrandOptions($offer))
            ->firstWhere('name', 'FREEDOM');
        $intent = FlightOfferDisplayPresenter::sanitizeSelectedFareFamilyIntent($freedom, $offer);
        $validated = PiaNdcFareFamilyPolicy::applySelectedBrandToValidatedSnapshot($offer, $intent);

        $booking = Booking::factory()->create([
            'booking_reference' => 'PIARDY01',
            'supplier' => SupplierProvider::PiaNdc->value,
            'payment_status' => 'unpaid',
            'status' => BookingStatus::Draft,
            'pnr' => null,
            'meta' => array_merge([
                'supplier_provider' => SupplierProvider::PiaNdc->value,
                'supplier_connection_id' => $connection->id,
                'validated_offer_snapshot' => $validated,
                'flight_offer_snapshot' => $offer,
                'selected_fare_family_option' => $intent,
                'search_criteria' => [
                    'origin' => 'KHI',
                    'destination' => 'ISB',
                    'depart_date' => '2026-07-23',
                ],
            ], $metaOverrides),
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'first_name' => 'JOHN',
            'last_name' => 'DOE',
            'date_of_birth' => '1990-01-01',
            'is_lead_passenger' => true,
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'john.doe@example.test',
            'phone' => '+923001234567',
        ]);

        return $booking->fresh(['passengers', 'contact']);
    }
}
