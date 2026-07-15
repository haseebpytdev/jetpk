<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPassenger;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Support\Bookings\SabreBrandedFarePublicAutoPnrEligibility;
use App\Support\Bookings\SabreSafeRefreshContext;
use App\Support\PublicBooking;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SabreBrandedFarePublicAutoPnrCheckoutWiringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->configureCheckoutSubmit();
        Http::fake(function (Request $request) {
            if (str_contains(strtolower($request->url()), 'token') || $request->isForm()) {
                return Http::response(['access_token' => 'tok-test', 'expires_in' => 3600], 200);
            }

            return Http::response([
                'errors' => [
                    [
                        'title' => 'Schema validation failed',
                        'detail' => 'object instance has properties',
                        'source' => ['pointer' => '/CreatePassengerNameRecordRQ/AirPrice/0/Brand/0'],
                    ],
                ],
            ], 422);
        });
    }

    public function test_review_submit_persists_eligibility_meta_without_pnr_when_flags_off(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->createBrandedPkDraftBooking();

        $response = $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later_booking_request']);

        $response->assertRedirect(route('booking.confirmation'));
        $response->assertSessionDoesntHaveErrors(['booking']);

        $booking->refresh();
        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertNull($booking->pnr);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $stored = is_array($meta[SabreBrandedFarePublicAutoPnrEligibility::META_KEY] ?? null)
            ? $meta[SabreBrandedFarePublicAutoPnrEligibility::META_KEY]
            : null;
        $this->assertNotNull($stored);
        $this->assertFalse($stored['eligible']);
        $this->assertSame('auto_pnr_flag_disabled', $stored['reason_code']);
        $this->assertSame('FL', $stored['selected_brand_code']);
        $this->assertSame('object_content', $stored['brand_shape']);
        $this->assertSame('PK', $stored['carrier_chain']);
        $this->assertSame(['auto_pnr_flag_enabled', 'public_flag_enabled'], $stored['failed_conditions']);
        $this->assertFalse($stored['live_supplier_call_attempted']);
        $this->assertArrayNotHasKey('condition_results', $stored);

        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', 'create_pnr')
            ->orderByDesc('id')
            ->first();
        if ($attempt !== null) {
            $this->assertNotSame('success', $attempt->status);
        }
    }

    public function test_confirmation_page_does_not_expose_eligibility_diagnostics(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->createBrandedPkDraftBooking();

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later_booking_request'])
            ->assertRedirect(route('booking.confirmation'));

        $confirmation = $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->get(route('booking.confirmation'));

        $confirmation->assertOk();
        $html = $confirmation->getContent();
        $this->assertStringNotContainsString('auto_pnr_flag_enabled', $html);
        $this->assertStringNotContainsString('failed_conditions', $html);
        $this->assertStringNotContainsString('brand_shape', $html);
        $this->assertStringNotContainsString('eligible_pending_public_pnr_enablement', $html);
    }

    protected function configureCheckoutSubmit(): void
    {
        Config::set([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => false,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.allow_createbooking_without_revalidation' => false,
            'suppliers.sabre.refresh_offer_before_public_pnr' => false,
            'suppliers.sabre.certified_route_selector_public_checkout_enabled' => false,
            'suppliers.sabre.passenger_records_allow_verified_multi_segment' => false,
            'suppliers.sabre.passenger_records_fresh_shop_guard_before_live' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
            'suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled' => false,
            'suppliers.sabre.createbooking_payload_style' => 'trip_orders_create_booking_v1_current',
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => null,
        ]);
    }

    protected function createBrandedPkDraftBooking(): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-pk-fl-offer',
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'PK',
            'airline_name' => 'PIA',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T10:00:00Z',
            'total' => 90062,
            'currency' => 'PKR',
            'validating_carrier' => 'PK',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'KHI',
                    'departure_at' => $depart.'T08:00:00Z',
                    'arrival_at' => $depart.'T10:00:00Z',
                    'carrier' => 'PK',
                    'flight_number' => '301',
                    'booking_class' => 'V',
                    'fare_basis_code' => 'VOWFL',
                ],
            ],
            'fare_breakdown' => [
                'supplier_total' => 90062,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'raw_payload' => [
                'distribution_channel' => 'GDS',
                'sabre_shop_context' => [
                    'pricing_information_ref' => 'pi-3',
                    'offer_ref' => 'offer-51',
                    'itinerary_ref' => 'itin-1',
                    'validating_carrier' => 'PK',
                    'fare_basis_codes' => ['VOWFL'],
                ],
                'sabre_booking_context' => [
                    'itinerary_reference' => '1',
                    'pricing_information_index' => 0,
                    'booking_classes_by_segment' => ['V'],
                    'fare_basis_codes_by_segment' => ['VOWFL'],
                    'segment_slice_count' => 1,
                    'brand_code' => 'FL',
                    'selected_brand_code' => 'FL',
                ],
            ],
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $sabreConn->id,
                'requires_price_change_confirmation' => false,
                'protection_mode' => 'hold_price_guaranteed',
                'fare_option_key' => 'fl-pi3',
                'selected_fare_family_option' => [
                    'brand_code' => 'FL',
                    'brand_name' => 'FREEDOM',
                    'fare_option_key' => 'fl-pi3',
                    'baggage' => '30 KG',
                    'cabin' => 'Economy',
                    'booking_class' => 'V',
                    'fare_basis' => 'VOWFL',
                ],
                'flight_offer_snapshot' => $offer,
                'normalized_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'KHI',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta[SabreSafeRefreshContext::META_KEY] = app(SabreSafeRefreshContext::class)->buildFromCheckout($offer, [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'KHI',
            'depart_date' => $depart,
            'adults' => 1,
        ], [
            'checkout_search_id' => 'bf7i-pk-search',
            'checkout_offer_id' => 'bf7i-pk-offer',
            'supplier_total' => 90062.0,
            'supplier_currency' => 'PKR',
        ]);
        $booking->forceFill(['meta' => $meta])->save();

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_index' => 0,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
            'first_name' => 'Test',
            'last_name' => 'Passenger',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'gender' => 'male',
            'passport_number' => 'AB1234567',
            'passport_issuing_country' => 'PK',
            'passport_expiry_date' => '2035-12-31',
            'nationality' => 'PK',
            'document_type' => 'passport',
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'bf7i@example.test',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 5000,
            'fees' => 0,
            'markup' => 5062,
            'discount' => 0,
            'total' => 90062,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        return $booking->fresh(['passengers', 'contact', 'fareBreakdown']);
    }
}
