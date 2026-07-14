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
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\Sabre\SabreBookingPayloadBuilder;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreRevalidationPayloadBuilder;
use App\Support\PublicBooking;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SabreBookingReviewSubmitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        config([
            'suppliers.sabre.refresh_offer_before_public_pnr' => false,
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.allow_createbooking_without_revalidation' => false,
            'suppliers.sabre.createbooking_payload_style' => 'trip_orders_create_booking_v1_current',
            'suppliers.sabre.passenger_records_allow_verified_multi_segment' => false,
            /** B77 live PR tests opt-in per test via true when exercising fresh-shop guard. */
            'suppliers.sabre.passenger_records_fresh_shop_guard_before_live' => false,
            /** R6: this suite exercises Trip Orders / multi-segment paths; certified selector is covered separately. */
            'suppliers.sabre.certified_route_selector_public_checkout_enabled' => false,
        ]);
    }

    private function sabreHttpUrlLooksLikeToken(string $url): bool
    {
        $haystack = strtolower($url);

        return str_contains($haystack, strtolower((string) config('suppliers.sabre.token_path', '/v2/auth/token')));
    }

    /**
     * @param  callable(Request): mixed  $afterTokenResponder
     */
    private function assertNoPassengerRecordsHttpPost(): void
    {
        $recorded = Http::recorded();
        $pairs = $recorded instanceof Collection ? $recorded->all() : (array) $recorded;
        foreach ($pairs as $pair) {
            $request = is_array($pair) ? ($pair[0] ?? null) : $pair;
            if ($request instanceof Request && str_contains((string) $request->url(), '/passenger/records')) {
                $this->fail('Unexpected Passenger Records HTTP POST during test.');
            }
        }
    }

    protected function activateSabreConnectionForHttp(SupplierConnection $sabreConn): void
    {
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);
    }

    private function sabreStubOAuthAndHttp(callable $afterTokenResponder): void
    {
        Http::fake(function (Request $request, array $options) use ($afterTokenResponder) {
            $payload = $options['laravel_data'] ?? [];
            // OAuth token POST uses asForm(); Content-Type may include charset so Request::isForm()
            // can be false — still treat as OAuth when body carries grant_type or URL matches token path.
            $isOAuthRequest = $this->sabreHttpUrlLooksLikeToken($request->url())
                || (is_array($payload) && array_key_exists('grant_type', $payload));

            if ($isOAuthRequest) {
                return Http::response([
                    'access_token' => 'tok-test-stub',
                    'expires_in' => 3600,
                ], 200);
            }

            return $afterTokenResponder($request);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function sabreIntlPassengerPassportFields(): array
    {
        return [
            'passport_number' => 'AB9999999',
            'passport_issuing_country' => 'PK',
            'passport_expiry_date' => '2035-12-31',
            'nationality' => 'PK',
            'document_type' => 'passport',
        ];
    }

    public function test_sabre_final_review_submit_blocked_when_booking_disabled(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();
        config([
            'suppliers.sabre.booking_enabled' => false,
            'suppliers.sabre.booking_live_call_enabled' => false,
        ]);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-offer-1',
            'supplier_provider' => 'sabre',
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T14:00:00Z',
            'total' => 100000,
            'currency' => 'PKR',
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'requires_price_change_confirmation' => false,
                'protection_mode' => 'hold_price_guaranteed',
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'sabre-review-test@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), [
                'booking_method' => 'pay_later',
            ])
            ->assertRedirect(route('booking.review'))
            ->assertSessionHasErrors(['booking' => 'Sabre booking is not enabled yet.']);

        Http::assertNothingSent();
    }

    public function test_sabre_final_review_submit_succeeds_when_booking_enabled_without_live_calls(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => false,
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => null,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-offer-1',
            'supplier_provider' => 'sabre',
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T14:00:00Z',
            'total' => 100000,
            'currency' => 'PKR',
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'requires_price_change_confirmation' => false,
                'protection_mode' => 'hold_price_guaranteed',
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'sabre-review-dry-run@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), [
                'booking_method' => 'pay_later',
            ])
            ->assertRedirect(route('booking.confirmation'))
            ->assertSessionHas('sabre_checkout_notice');

        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertSame('dry_run', data_get($meta, 'sabre_checkout_outcome.status'));

        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $this->assertNotNull($attempt);
        $this->assertSame('dry_run', $attempt->status);
        $summary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertArrayHasKey('has_booking_class', $summary);
        $this->assertArrayHasKey('payload_schema', $summary);
        $this->assertSame('trip_orders_create_booking_v1', $summary['payload_schema'] ?? null);
        $this->assertArrayHasKey('booking_schema', $summary);
        $this->assertSame('trip_orders_create_booking', $summary['booking_schema'] ?? null);
    }

    public function test_sabre_final_review_live_submits_when_both_flags_true_and_stores_pnr(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->sabreStubOAuthAndHttp(function (Request $request) {
            if (str_contains($request->url(), 'trip/orders/getBooking')) {
                return Http::response([
                    'bookingId' => 'pnrzz9-booking-id',
                    'isCancelable' => true,
                    'isTicketed' => false,
                    'flights' => [[
                        'fromAirportCode' => 'LHE',
                        'toAirportCode' => 'DXB',
                        'departureDate' => '2026-06-06',
                        'departureTime' => '11:00',
                        'arrivalDate' => '2026-06-06',
                        'arrivalTime' => '14:00',
                        'airlineCode' => 'EK',
                        'flightNumber' => '601',
                        'bookingClass' => 'K',
                        'flightStatusCode' => 'HK',
                    ]],
                ], 200);
            }

            return Http::response(['recordLocator' => 'PNRZZ9'], 201);
        });
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => null,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-offer-2',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T14:00:00Z',
            'total' => 100000,
            'currency' => 'PKR',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'carrier' => 'EK',
                'flight_number' => '601',
                'departure_at' => $depart.'T08:00:00Z',
                'arrival_at' => $depart.'T14:00:00Z',
                'booking_class' => 'K',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100000,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
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
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create(array_merge([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ], $this->sabreIntlPassengerPassportFields()));

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'sabre-review-pending@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), [
                'booking_method' => 'pay_later',
            ])
            ->assertRedirect(route('booking.confirmation'))
            ->assertSessionHas('sabre_checkout_notice');

        $booking->refresh();
        $this->assertSame('pending_payment_or_ticketing', data_get($booking->meta, 'sabre_checkout_outcome.status'));
        $this->assertSame('PNRZZ9', $booking->pnr);
        $this->assertSame('synced', data_get($booking->meta, 'pnr_itinerary_sync.status'));

        Http::assertSent(function ($request): bool {
            return $request instanceof Request
                && str_contains($request->url(), 'trip/orders/createBooking');
        });
    }

    public function test_duffel_final_review_submit_not_blocked_when_sabre_booking_disabled(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'duffel-offer-1',
            'supplier_provider' => 'duffel',
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T14:00:00Z',
            'total' => 100000,
            'currency' => 'PKR',
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Duffel->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Duffel->value,
                'requires_price_change_confirmation' => false,
                'protection_mode' => 'hold_price_guaranteed',
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'duffel-review-test@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), [
                'booking_method' => 'pay_later',
            ])
            ->assertRedirect(route('booking.confirmation'));

        Http::assertNothingSent();
    }

    public function test_public_review_page_includes_submit_double_click_guard(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => false,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-review-js-guard',
            'supplier_provider' => 'sabre',
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T14:00:00Z',
            'total' => 100000,
            'currency' => 'PKR',
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'requires_price_change_confirmation' => false,
                'protection_mode' => 'hold_price_guaranteed',
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'sabre-review-js@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->get(route('booking.review'))
            ->assertOk()
            ->assertSee('data-ota-submitting', false)
            ->assertSee('Please wait', false);

        Http::assertNothingSent();
    }

    public function test_public_review_blocked_when_recent_public_create_pnr_attempt_was_http_429(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-dup-429',
            'supplier_provider' => 'sabre',
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T14:00:00Z',
            'total' => 100000,
            'currency' => 'PKR',
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'requires_price_change_confirmation' => false,
                'protection_mode' => 'hold_price_guaranteed',
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'sabre-dup-429@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => null,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'failed',
            'error_code' => 'sabre_booking_http_failed',
            'error_message' => 'Too Many Requests',
            'safe_summary' => [
                'source' => 'sabre_public_checkout',
                'live_call_attempted' => true,
                'http_status' => 429,
            ],
            'attempted_by' => null,
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), [
                'booking_method' => 'pay_later',
            ])
            ->assertRedirect(route('booking.review'))
            ->assertSessionHasErrors(['booking']);

        Http::assertNothingSent();
    }

    public function test_public_review_allows_submit_when_prior_attempt_had_no_live_call(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => false,
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => null,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-dup-no-live',
            'supplier_provider' => 'sabre',
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T14:00:00Z',
            'total' => 100000,
            'currency' => 'PKR',
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'requires_price_change_confirmation' => false,
                'protection_mode' => 'hold_price_guaranteed',
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'sabre-dup-no-live@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => null,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'failed',
            'error_code' => 'sabre_invalid_itinerary_timing',
            'error_message' => 'Timing invalid',
            'safe_summary' => [
                'source' => 'sabre_public_checkout',
                'live_call_attempted' => false,
            ],
            'attempted_by' => null,
            'attempted_at' => now()->subMinute(),
            'completed_at' => now()->subMinute(),
        ]);

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), [
                'booking_method' => 'pay_later',
            ])
            ->assertRedirect(route('booking.confirmation'));

        Http::assertNothingSent();
    }

    public function test_public_review_blocked_when_booking_has_pnr_already(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-dup-pnr',
            'supplier_provider' => 'sabre',
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T14:00:00Z',
            'total' => 100000,
            'currency' => 'PKR',
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => 'ABCDEF',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'requires_price_change_confirmation' => false,
                'protection_mode' => 'hold_price_guaranteed',
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'sabre-dup-pnr@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), [
                'booking_method' => 'pay_later',
            ])
            ->assertRedirect(route('booking.review'))
            ->assertSessionHasErrors(['booking']);

        Http::assertNothingSent();
    }

    public function test_public_review_blocked_when_supplier_booking_attempt_is_processing(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-dup-processing',
            'supplier_provider' => 'sabre',
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T14:00:00Z',
            'total' => 100000,
            'currency' => 'PKR',
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'requires_price_change_confirmation' => false,
                'protection_mode' => 'hold_price_guaranteed',
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'sabre-dup-proc@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => null,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'processing',
            'safe_summary' => [
                'source' => 'sabre_public_checkout',
                'live_call_attempted' => true,
            ],
            'attempted_by' => null,
            'attempted_at' => now(),
            'completed_at' => null,
        ]);

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), [
                'booking_method' => 'pay_later',
            ])
            ->assertRedirect(route('booking.review'))
            ->assertSessionHasErrors(['booking']);

        Http::assertNothingSent();
    }

    public function test_sabre_final_review_submit_blocked_when_snapshot_timeline_mismatch(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => false,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $depart = '2026-05-30';
        $offer = [
            'offer_id' => 'sabre-snap-timeline-bad',
            'id' => 'sabre-snap-timeline-bad',
            'supplier_provider' => 'sabre',
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'departure_at' => '2026-05-30T05:00:00',
            'arrival_at' => '2026-06-01T07:10:00',
            'duration_minutes' => 845,
            'stops' => 1,
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'KHI',
                    'departure_at' => '2026-05-30T05:00:00',
                    'arrival_at' => '2026-05-30T06:45:00',
                    'duration_minutes' => 105,
                ],
                [
                    'origin' => 'KHI',
                    'destination' => 'DXB',
                    'departure_at' => '2026-06-01T05:00:00',
                    'arrival_at' => '2026-06-01T07:10:00',
                    'duration_minutes' => 130,
                ],
            ],
            'total' => 100000,
            'currency' => 'PKR',
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'requires_price_change_confirmation' => false,
                'protection_mode' => 'hold_price_guaranteed',
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'sabre-timeline-invalid@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), [
                'booking_method' => 'pay_later',
            ])
            ->assertRedirect(route('booking.review'))
            ->assertSessionHasErrors(['booking']);

        Http::assertNothingSent();
    }

    public function test_sabre_final_review_live_403_stores_forbidden_error_code(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->sabreStubOAuthAndHttp(fn () => Http::response(['message' => 'Forbidden'], 403));
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => null,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-offer-403',
            'supplier_provider' => 'sabre',
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T14:00:00Z',
            'total' => 100000,
            'currency' => 'PKR',
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
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create(array_merge([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ], $this->sabreIntlPassengerPassportFields()));

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'sabre-403@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), [
                'booking_method' => 'pay_later',
            ])
            ->assertRedirect(route('booking.review'))
            ->assertSessionHasErrors([
                'booking' => 'Sabre booking endpoint is forbidden for this credential/path. Try configured booking path or contact Sabre/provider.',
            ]);

        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $this->assertNotNull($attempt);
        $this->assertSame('sabre_booking_forbidden', $attempt->error_code);
        $summary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertArrayHasKey('endpoint_path', $summary);
    }

    public function test_sabre_final_review_live_422_stores_validation_error_without_raw_body_in_attempt(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->sabreStubOAuthAndHttp(fn () => Http::response([
            'errors' => [
                [
                    'title' => 'Schema validation failed',
                    'detail' => 'object instance has properties',
                    'source' => ['pointer' => '/CreatePassengerNameRecordRQ/AirPrice/0/message'],
                ],
            ],
        ], 422));
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => null,
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => false,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $booking = $this->createSabreReviewSubmitDraftBookingFor422Test();

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), [
                'booking_method' => 'pay_later',
            ])
            ->assertRedirect(route('booking.confirmation'))
            ->assertSessionDoesntHaveErrors(['booking']);

        $booking->refresh();
        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertNull($booking->pnr);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertTrue((bool) ($meta['defer_supplier_booking_to_manual_review'] ?? false));

        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $this->assertNotNull($attempt);
        $this->assertSame('sabre_booking_validation_failed', $attempt->error_code);
        $summary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertIsArray($summary['safe_validation_excerpts'] ?? null);
    }

    public function test_sabre_final_review_live_422_blocks_when_public_auto_pnr_enabled(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->sabreStubOAuthAndHttp(fn () => Http::response([
            'errors' => [
                [
                    'title' => 'Schema validation failed',
                    'detail' => 'object instance has properties',
                    'source' => ['pointer' => '/CreatePassengerNameRecordRQ/AirPrice/0/message'],
                ],
            ],
        ], 422));
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => null,
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $booking = $this->createSabreReviewSubmitDraftBookingFor422Test();

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), [
                'booking_method' => 'pay_later',
            ])
            ->assertRedirect(route('booking.review'))
            ->assertSessionHasErrors(['booking']);

        $errors = session('errors');
        $bookingError = $errors?->get('booking')[0] ?? '';
        $this->assertStringNotContainsString('CreatePassengerNameRecordRQ', (string) $bookingError);
        $this->assertStringNotContainsString('/AirPrice/0/message', (string) $bookingError);

        $booking->refresh();
        $this->assertSame(BookingStatus::Draft, $booking->status);
    }

    private function createSabreReviewSubmitDraftBookingFor422Test(): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $this->activateSabreConnectionForHttp($sabreConn);

        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-offer-422',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T14:00:00Z',
            'total' => 100000,
            'currency' => 'PKR',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'departure_at' => $depart.'T08:00:00Z',
                    'arrival_at' => $depart.'T14:00:00Z',
                    'carrier' => 'EK',
                    'flight_number' => '601',
                    'booking_class' => 'Y',
                    'fare_basis_code' => 'YOWEK',
                ],
            ],
            'fare_breakdown' => [
                'supplier_total' => 100000,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
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
                'flight_offer_snapshot' => $offer,
                'normalized_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create(array_merge([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ], $this->sabreIntlPassengerPassportFields()));

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'sabre-422@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        return $booking;
    }

    public function test_sabre_inspect_booking_payload_command_outputs_sanitized_flags(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        config([
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => null,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();

        $depart = now()->addDays(12)->toDateString();
        $snapshot = [
            'offer_id' => 'inspect-offer-1',
            'supplier_offer_id' => 'inspect-offer-1',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'PK',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'KHI',
                    'departure_at' => $depart.'T05:00:00',
                    'arrival_at' => $depart.'T06:45:00',
                    'carrier' => 'PK',
                    'flight_number' => '303',
                    'booking_class' => 'Y',
                    'fare_basis_code' => 'YOWPK',
                ],
                [
                    'origin' => 'KHI',
                    'destination' => 'DXB',
                    'departure_at' => $depart.'T22:00:00',
                    'arrival_at' => $depart.'T23:30:00',
                    'carrier' => 'EK',
                    'flight_number' => '601',
                    'booking_class' => 'K',
                    'fare_basis_code' => 'KLITE1',
                ],
            ],
            'fare_breakdown' => [
                'supplier_total' => 150000,
                'currency' => 'PKR',
                'base_fare' => 120000,
                'taxes' => 30000,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'baggage' => ['summary' => '1PC'],
            'raw_payload' => [
                'sabre_shop_identifiers' => [
                    'itinerary_id' => 'itin-x',
                    'pricing_0_offerItemId' => 'offer-ref-99',
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
                'normalized_offer_snapshot' => $snapshot,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'inspect-payload@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 120000,
            'taxes' => 30000,
            'fees' => 0,
            'markup' => 0,
            'discount' => 0,
            'total' => 150000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        Artisan::call('sabre:inspect-booking-payload', ['--booking' => (string) $booking->id]);
        $out = Artisan::output();
        $this->assertStringContainsString('booking_id='.$booking->id, $out);
        $this->assertStringContainsString('segment_1_booking_class_present=yes', $out);
        $this->assertStringContainsString('segment_2_fare_basis_present=yes', $out);
        $this->assertStringContainsString('has_booking_class=true', $out);
        $this->assertStringContainsString('has_fare_basis=true', $out);
        $this->assertStringContainsString('payload_schema=trip_orders_create_booking_v1', $out);
        $this->assertStringContainsString('has_commit_or_end_transaction=true', $out);
        $this->assertStringContainsString('has_trip_orders_schema=true', $out);
        $this->assertStringContainsString('has_offer_reference=true', $out);
        $this->assertStringContainsString('ticketing_enabled=false', $out);
        $this->assertStringContainsString('booking_schema=trip_orders_create_booking', $out);
    }

    public function test_sabre_inspect_booking_config_b36_boolean_flags_and_no_value_leaks(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        config([
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => 'trip_orders_create_booking',
            'suppliers.sabre.agency_phone' => '+155501112233',
            'suppliers.sabre.agency_country' => 'PK',
            'suppliers.sabre.createbooking_payload_style' => 'trip_orders_flight_details_sabre_pos_source_phone_v1',
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->credentials = ['pcc' => 'SECRETPSEUDO'];
        $sabreConn->save();

        $depart = now()->addDays(11)->toDateString();
        $snapshot = [
            'offer_id' => 'cfg-b36',
            'supplier_offer_id' => 'cfg-b36',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'EK',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => $depart.'T08:00:00Z',
                'arrival_at' => $depart.'T14:00:00Z',
                'carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLITE1',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100000,
                'currency' => 'PKR',
                'base_fare' => 80000,
                'taxes' => 20000,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'baggage' => ['summary' => '1PC'],
            'raw_payload' => [
                'sabre_shop_identifiers' => [
                    'itinerary_id' => 'itin-cfg-b36',
                    'pricing_0_offerItemId' => 'offer-cfg-b36',
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
                'normalized_offer_snapshot' => $snapshot,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create(array_merge([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ], $this->sabreIntlPassengerPassportFields()));

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'cfg-b36@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 20000,
            'fees' => 0,
            'markup' => 0,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        Artisan::call('sabre:inspect-booking-config', ['--booking' => (string) $booking->id]);
        $out = Artisan::output();
        $this->assertStringContainsString('active_createbooking_payload_style=trip_orders_flight_details_sabre_pos_source_phone_v1', $out);
        $this->assertStringContainsString('booking_path=/v1/trip/orders/createBooking', $out);
        $this->assertStringContainsString('agency_phone_config_present=true', $out);
        $this->assertStringContainsString('agency_country_config_present=true', $out);
        $this->assertStringContainsString('pcc_present=true', $out);
        foreach (['SECRETPSEUDO', '+155501112233', 'cfg-b36@'] as $leak) {
            $this->assertStringNotContainsString($leak, $out, 'inspect-booking-config must not echo secrets or PII: '.$leak);
        }
    }

    public function test_sabre_inspect_booking_payload_warns_when_fare_basis_missing(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        config([
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => null,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();

        $depart = now()->addDays(12)->toDateString();
        $snapshot = [
            'offer_id' => 'inspect-offer-nofb',
            'supplier_offer_id' => 'inspect-offer-nofb',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'PK',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'departure_at' => $depart.'T05:00:00',
                    'arrival_at' => $depart.'T08:00:00',
                    'carrier' => 'PK',
                    'flight_number' => '303',
                    'booking_class' => 'Y',
                ],
            ],
            'fare_breakdown' => [
                'supplier_total' => 150000,
                'currency' => 'PKR',
                'base_fare' => 120000,
                'taxes' => 30000,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'baggage' => ['summary' => '1PC'],
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $sabreConn->id,
                'normalized_offer_snapshot' => $snapshot,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'inspect-nofb@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 120000,
            'taxes' => 30000,
            'fees' => 0,
            'markup' => 0,
            'discount' => 0,
            'total' => 150000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        Artisan::call('sabre:inspect-booking-payload', ['--booking' => (string) $booking->id]);
        $out = Artisan::output();
        $this->assertStringContainsString('has_fare_basis=false', $out);
        $this->assertStringContainsString('inspect_warning_fare_basis=', $out);
        $this->assertStringContainsString('Trip Orders booking:', $out);
        $this->assertStringNotContainsString('Authorization', $out);
    }

    public function test_sabre_effective_schema_defaults_to_trip_orders_for_create_booking_path(): void
    {
        config([
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => null,
        ]);
        $svc = $this->app->make(SabreBookingService::class);
        $this->assertSame('trip_orders_create_booking', $svc->effectiveSabreBookingSchema());
    }

    public function test_sabre_final_review_live_200_order_id_success_stores_api_id_and_payload_schema(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->sabreStubOAuthAndHttp(fn () => Http::response(['bookingId' => 'sabre-order-xyz'], 200));
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => null,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-offer-noloc',
            'supplier_provider' => 'sabre',
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T14:00:00Z',
            'total' => 100000,
            'currency' => 'PKR',
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
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create(array_merge([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ], $this->sabreIntlPassengerPassportFields()));

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'sabre-noloc@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), [
                'booking_method' => 'pay_later',
            ])
            ->assertRedirect(route('booking.confirmation'));

        $booking->refresh();
        $this->assertSame('needs_review', data_get($booking->meta, 'sabre_checkout_outcome.status'));
        $this->assertSame('sabre-order-xyz', $booking->supplier_api_booking_id);
        $this->assertNull($booking->pnr);
        $this->assertSame('manual_review', $booking->supplier_booking_status);

        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $this->assertNotNull($attempt);
        $this->assertSame('needs_review', $attempt->status);
        $summary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertSame('trip_orders_create_booking_v1', $summary['payload_schema'] ?? null);
    }

    public function test_sabre_final_review_live_200_record_locator_sets_pnr_and_success_attempt(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->sabreStubOAuthAndHttp(fn () => Http::response(['recordLocator' => 'abc1de'], 200));
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => null,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-offer-pnr',
            'supplier_provider' => 'sabre',
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T14:00:00Z',
            'total' => 100000,
            'currency' => 'PKR',
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
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create(array_merge([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ], $this->sabreIntlPassengerPassportFields()));

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'sabre-pnr@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), [
                'booking_method' => 'pay_later',
            ])
            ->assertRedirect(route('booking.confirmation'));

        $booking->refresh();
        $this->assertSame('ABC1DE', $booking->pnr);
        $this->assertSame('pending_payment_or_ticketing', $booking->supplier_booking_status);

        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $this->assertNotNull($attempt);
        $this->assertSame('success', $attempt->status);
        $summary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertSame('trip_orders_create_booking_v1', $summary['payload_schema'] ?? null);
    }

    public function test_sabre_final_review_live_200_errors_without_locator_sets_application_error_and_notice(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->sabreStubOAuthAndHttp(fn () => Http::response([
            'errors' => [
                [
                    'code' => 'RULE_FAIL',
                    'title' => 'Invalid',
                    'detail' => 'Fare not held',
                    'field' => 'itinerary.segments[0].class',
                    'missingFields' => ['fareBasis', 'priceQuoteId'],
                ],
            ],
            'timestamp' => '2026-05-12T10:00:00Z',
            'traceId' => 'trace-abc-123',
            'request' => ['id' => 'corr-req-1', 'correlationId' => 'corr-xyz-9'],
        ], 200));
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => null,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-offer-err',
            'supplier_provider' => 'sabre',
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T14:00:00Z',
            'total' => 100000,
            'currency' => 'PKR',
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
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create(array_merge([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ], $this->sabreIntlPassengerPassportFields()));

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'sabre-err@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), [
                'booking_method' => 'pay_later',
            ])
            ->assertRedirect(route('booking.confirmation'))
            ->assertSessionHas('sabre_checkout_notice');

        $booking->refresh();
        $this->assertSame('manual_review', $booking->supplier_booking_status);
        $this->assertNull($booking->pnr);
        $this->assertSame('needs_review', data_get($booking->meta, 'sabre_checkout_outcome.status'));
        $this->assertSame('sabre_booking_application_error', data_get($booking->meta, 'sabre_checkout_outcome.error_code'));

        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $this->assertNotNull($attempt);
        $this->assertSame('needs_review', $attempt->status);
        $this->assertSame('sabre_booking_application_error', $attempt->error_code);
        $summary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertSame('RULE_FAIL', ($summary['response_error_codes'][0] ?? null));
        $this->assertArrayNotHasKey('raw', $summary);
        $this->assertArrayNotHasKey('response_body', $summary);
        $this->assertSame('trip_orders_create_booking_v1', $summary['payload_schema'] ?? null);
        $this->assertSame('corr-req-1', $summary['request_id'] ?? null);
        $this->assertSame('corr-xyz-9', $summary['request_correlation_id'] ?? null);
        $this->assertSame('trace-abc-123', $summary['trace_id'] ?? null);
        $mf = $summary['response_missing_fields'] ?? [];
        $this->assertIsArray($mf);
        $this->assertContains('fareBasis', $mf);
        $this->assertContains('priceQuoteId', $mf);

        Artisan::call('sabre:inspect-booking-attempt', ['--attempt' => (string) $attempt->id]);
        $inspectOut = Artisan::output();
        $this->assertStringContainsString('attempt_id='.$attempt->id, $inspectOut);
        $this->assertStringContainsString('error_code=sabre_booking_application_error', $inspectOut);
        $this->assertStringContainsString('response_error_codes=RULE_FAIL', $inspectOut);
        $this->assertStringContainsString('request_id=corr-req-1', $inspectOut);
        $this->assertStringContainsString('request_correlation_id=corr-xyz-9', $inspectOut);
        $this->assertStringContainsString('trace_id=trace-abc-123', $inspectOut);
        $this->assertStringContainsString('fareBasis', $inspectOut);
        $this->assertStringContainsString('priceQuoteId', $inspectOut);
    }

    public function test_sabre_final_review_live_200_mandatory_data_missing_sets_outcome_meta_and_notice(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->sabreStubOAuthAndHttp(fn () => Http::response([
            'errors' => [
                ['code' => 'MANDATORY_DATA_MISSING', 'title' => 'Incomplete', 'detail' => 'Required fields absent'],
            ],
            'timestamp' => '2026-05-12T11:00:00Z',
            'traceId' => 'trace-md-1',
            'request' => ['id' => 'req-md-1', 'correlationId' => 'corr-md-1'],
        ], 200));
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => null,
            'suppliers.sabre.createbooking_payload_style' => 'trip_orders_flight_offer_v1',
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-offer-md',
            'supplier_provider' => 'sabre',
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T14:00:00Z',
            'total' => 100000,
            'currency' => 'PKR',
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
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create(array_merge([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ], $this->sabreIntlPassengerPassportFields()));

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'sabre-md@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        $resp = $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), [
                'booking_method' => 'pay_later',
            ]);
        $resp->assertRedirect(route('booking.confirmation'))
            ->assertSessionHas('sabre_checkout_notice', fn ($v) => is_string($v) && str_contains($v, 'mandatory booking data was missing'));

        $booking->refresh();
        $this->assertTrue((bool) data_get($booking->meta, 'sabre_checkout_outcome.mandatory_data_missing'));
        $this->assertSame('MANDATORY_DATA_MISSING', (string) (data_get($booking->meta, 'sabre_checkout_outcome.response_error_codes.0')));
        $this->assertArrayHasKey('wire_root_keys', $booking->meta['sabre_checkout_outcome'] ?? []);
        $this->assertFalse((bool) data_get($booking->meta, 'sabre_checkout_outcome.wire_has_required_product_at_root'));
        $this->assertIsArray(data_get($booking->meta, 'sabre_checkout_outcome.response_error_messages'));
    }

    public function test_sabre_revalidate_payload_summary_uses_preserved_shop_context(): void
    {
        $builder = app(SabreRevalidationPayloadBuilder::class);
        $draft = $this->sabreRevalidationDraft([
            '_sabre_shop_context' => [
                'itinerary_ref' => 'itin-ctx-1',
                'pricing_information_ref' => 'offer-ref-ctx-1',
                'leg_refs' => [1, 2],
                'schedule_refs' => [11, 12],
                'fare_component_refs' => [21],
                'fare_basis_codes' => ['YCTX1'],
            ],
        ]);

        $payload = $builder->buildPayload($draft);
        $summary = $builder->safePayloadSummary($payload);

        $this->assertTrue($summary['has_shop_context']);
        $this->assertTrue($summary['has_leg_refs']);
        $this->assertTrue($summary['has_schedule_refs']);
        $this->assertTrue($summary['has_pricing_information_ref']);
        $this->assertTrue($summary['has_fare_component_refs']);
        $this->assertTrue($summary['has_fare_basis']);
        $this->assertTrue($summary['has_class_of_service']);
        $this->assertTrue($summary['has_segment_numbers']);
        $this->assertSame(1, data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.FlightSegment.Number'));
        $this->assertSame('Y', data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.FlightSegment.ClassOfService'));
    }

    public function test_sabre_revalidate_400_safe_parser_extracts_error_details(): void
    {
        $digest = app(SabreRevalidationPayloadBuilder::class)->extractSafeErrorDigest([
            'errors' => [[
                'code' => 'ERR.MISSING',
                'detail' => 'Missing fare component reference',
                'missingFields' => ['fareComponents[0].ref'],
                'source' => ['pointer' => '/OTA_AirLowFareSearchRQ/OriginDestinationInformation/0'],
            ]],
            'request' => ['correlationId' => 'corr-safe-1'],
        ]);

        $this->assertSame(['ERR.MISSING'], $digest['response_error_codes'] ?? null);
        $this->assertSame(['Missing fare component reference'], $digest['response_error_messages'] ?? null);
        $this->assertSame(['fareComponents[0].ref'], $digest['response_missing_fields'] ?? null);
        $this->assertSame(['/OTA_AirLowFareSearchRQ/OriginDestinationInformation/0'], $digest['response_validation_paths'] ?? null);
        $this->assertSame('corr-safe-1', $digest['request_id'] ?? null);
    }

    public function test_sabre_successful_revalidate_response_populates_fare_linkage_fields(): void
    {
        $builder = app(SabreRevalidationPayloadBuilder::class);
        $linkage = $builder->extractFareLinkage([
            'pricingInformation' => [[
                'offerItemId' => 'offer-ok-1',
                'fareReference' => 'fare-ref-ok-1',
                'priceQuoteReference' => 'pq-ok-1',
                'fare' => [
                    'validatingCarrierCode' => 'PK',
                    'totalFare' => ['totalPrice' => 12345.67, 'currency' => 'PKR'],
                    'passengerInfoList' => [[
                        'passengerInfo' => [
                            'fareComponents' => [[
                                'fareBasisCode' => 'YOK1',
                                'segments' => [[
                                    'segment' => ['bookingCode' => 'Y'],
                                ]],
                            ]],
                        ],
                    ]],
                ],
            ]],
            'revalidationReference' => 'rev-ok-1',
        ]);
        $digest = $builder->linkageDigest($linkage);

        $this->assertTrue($digest['has_fare_basis']);
        $this->assertTrue($digest['has_fare_reference']);
        $this->assertTrue($digest['has_price_quote_reference']);
        $this->assertTrue($digest['has_offer_reference']);
        $this->assertTrue($digest['has_revalidation_reference']);
        $this->assertTrue($digest['has_revalidated_fare']);
        $this->assertTrue($digest['has_revalidated_currency']);
    }

    public function test_sabre_revalidation_failure_skips_create_booking_when_enabled(): void
    {
        $sabreBase = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        Http::fake([
            $sabreBase.$tokenPath => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            $sabreBase.'/v4/shop/flights/revalidate' => Http::response([
                'errors' => [[
                    'code' => 'ERR.MISSING',
                    'message' => 'Missing shop context',
                    'missingFields' => ['pricingInformationRef'],
                ]],
            ], 400),
            $sabreBase.'/v1/trip/orders/createBooking' => Http::response(['recordLocator' => 'SHOULDNOT'], 201),
        ]);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.revalidate_before_booking' => true,
            'suppliers.sabre.revalidate_path' => '/v4/shop/flights/revalidate',
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => null,
            'suppliers.sabre.allow_createbooking_without_revalidation' => false,
        ]);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
            'base_url' => $sabreBase,
        ]);

        $result = app(SabreBookingService::class)->createBooking(
            $this->sabreOfferForRevalidation($connection->id),
            $this->sabrePassengerDataForRevalidation(),
        );

        $this->assertFalse($result['success']);
        $this->assertSame('sabre_revalidation_failed', $result['error_code']);
        $this->assertSame(400, $result['revalidation_http_status']);
        $this->assertSame(['ERR.MISSING'], data_get($result, 'revalidation_error_digest.response_error_codes'));
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/v1/trip/orders/createBooking'));
    }

    public function test_sabre_ticketing_stays_disabled_in_default_config(): void
    {
        config(['suppliers.sabre.ticketing_enabled' => false]);
        $this->assertFalse((bool) config('suppliers.sabre.ticketing_enabled'));
    }

    public function test_b74_pnr_only_single_segment_skips_prebooking_revalidation_and_creates_pnr(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $recordsPath = '/v2.5.0/passenger/records?mode=create';

        $this->sabreStubOAuthAndHttp(function (Request $request) use ($recordsPath) {
            if (str_contains($request->url(), '/revalidate')) {
                return Http::response(['errors' => [['code' => '27131', 'message' => 'fail']]], 400);
            }
            if (str_contains($request->url(), $recordsPath)) {
                return Http::response([
                    'CreatePassengerNameRecordRS' => [
                        'ApplicationResults' => ['status' => 'Complete'],
                        'ItineraryRef' => ['ID' => 'B74PNR1'],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_mode' => 'pnr_only',
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => $recordsPath,
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
            'suppliers.sabre.revalidate_before_booking' => true,
            'suppliers.sabre.revalidate_path' => '/v4/shop/flights/revalidate',
            'suppliers.sabre.allow_createbooking_without_revalidation' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => true,
            'suppliers.sabre.passenger_records_allow_verified_multi_segment' => false,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-b74-offer-1',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'carrier' => 'EK',
                'flight_number' => '625',
                'departure_at' => $depart.'T08:00:00Z',
                'arrival_at' => $depart.'T14:00:00Z',
                'booking_class' => 'Y',
                'fare_basis_code' => 'YLOW',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100000,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
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
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'adults' => 1,
                ],
            ],
        ]);

        BookingPassenger::factory()->create(array_merge([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ], $this->sabreIntlPassengerPassportFields()));

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'sabre-b74@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'))
            ->assertSessionHas('sabre_checkout_notice', fn ($v) => is_string($v) && str_contains($v, 'subject to confirmation'));

        $booking->refresh();
        $this->assertSame('B74PNR1', $booking->pnr);
        $this->assertSame('pnr_only_ticketing_disabled', data_get($booking->meta, 'sabre_checkout_outcome.prebooking_revalidation_skipped_reason'));
        $this->assertTrue((bool) data_get($booking->meta, 'sabre_checkout_outcome.revalidation_skipped_by_config'));
        $this->assertFalse((bool) data_get($booking->meta, 'sabre_checkout_outcome.revalidation_attempted'));
        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $this->assertNotNull($attempt);
        $this->assertSame('success', $attempt->status);
        $summary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertTrue($summary['ticketing_disabled'] ?? false);
        $this->assertTrue($summary['ticketing_pending'] ?? false);

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/revalidate'));
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/passenger/records'));
    }

    /**
     * B81: Double-submit / back-button POST after successful PNR must not call Passenger Records again.
     */
    public function test_b81_second_post_after_successful_pnr_does_not_call_passenger_records_again(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $recordsPath = '/v2.5.0/passenger/records?mode=create';

        $this->sabreStubOAuthAndHttp(function (Request $request) use ($recordsPath) {
            if (str_contains($request->url(), '/revalidate')) {
                return Http::response(['errors' => [['code' => '27131', 'message' => 'fail']]], 400);
            }
            if (str_contains($request->url(), $recordsPath)) {
                return Http::response([
                    'CreatePassengerNameRecordRS' => [
                        'ApplicationResults' => ['status' => 'Complete'],
                        'ItineraryRef' => ['ID' => 'B81PNR'],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_mode' => 'pnr_only',
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => $recordsPath,
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
            'suppliers.sabre.revalidate_before_booking' => true,
            'suppliers.sabre.revalidate_path' => '/v4/shop/flights/revalidate',
            'suppliers.sabre.allow_createbooking_without_revalidation' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => true,
            'suppliers.sabre.passenger_records_allow_verified_multi_segment' => false,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-b81-offer',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'carrier' => 'EK',
                'flight_number' => '625',
                'departure_at' => $depart.'T08:00:00Z',
                'arrival_at' => $depart.'T14:00:00Z',
                'booking_class' => 'Y',
                'fare_basis_code' => 'YLOW',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100000,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
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
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'adults' => 1,
                ],
            ],
        ]);

        BookingPassenger::factory()->create(array_merge([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ], $this->sabreIntlPassengerPassportFields()));

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'sabre-b81@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'));

        $this->assertSame(
            1,
            collect(Http::recorded())
                ->filter(fn (array $p): bool => str_contains((string) $p[0]->url(), '/passenger/records'))
                ->count()
        );

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'));

        $this->assertSame(
            1,
            collect(Http::recorded())
                ->filter(fn (array $p): bool => str_contains((string) $p[0]->url(), '/passenger/records'))
                ->count()
        );
    }

    /**
     * B81: Recent public-checkout attempt with HTTP 429 blocks an immediate duplicate POST (no second live call).
     */
    public function test_b81_recent_429_create_pnr_attempt_blocks_immediate_duplicate_review_post(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v2.5.0/passenger/records?mode=create',
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $booking = $this->b63SabreBookingWithOffer(
            $agency->id,
            $sabreConn->id,
            $this->b63SabreTwoSegmentOffer($sabreConn->id, $depart),
            $depart,
            'sabre-b81-429@example.com',
        );

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $sabreConn->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'failed',
            'error_code' => 'sabre_booking_http_failed',
            'error_message' => 'HTTP 429.',
            'safe_summary' => [
                'source' => 'sabre_public_checkout',
                'live_call_attempted' => true,
                'http_status' => 429,
            ],
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.review'))
            ->assertSessionHasErrors(['booking']);

        Http::assertNothingSent();
    }

    /**
     * B81: Stale shop/segment attempt blocks repeat submit with search-again messaging (no Passenger Records POST).
     */
    public function test_b81_stale_shop_segment_attempt_blocks_duplicate_review_post(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v2.5.0/passenger/records?mode=create',
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $booking = $this->b63SabreBookingWithOffer(
            $agency->id,
            $sabreConn->id,
            $this->b63SabreTwoSegmentOffer($sabreConn->id, $depart),
            $depart,
            'sabre-b81-stale@example.com',
        );

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $sabreConn->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'error_code' => 'sabre_passenger_records_stale_shop_segment',
            'error_message' => 'Stale Sabre shop segment.',
            'safe_summary' => [
                'source' => 'sabre_public_checkout',
                'live_call_attempted' => false,
            ],
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        $response = $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later']);

        $response->assertRedirect(route('booking.review'));
        $response->assertSessionHasErrors(['booking']);
        $err = session('errors')->get('booking');
        $this->assertIsArray($err);
        $this->assertStringContainsString('new search', strtolower((string) ($err[0] ?? '')));

        $this->assertNoPassengerRecordsHttpPost();
    }

    /**
     * B81: Application-error attempt blocks immediate duplicate submit (draft recovery) without a second live call.
     */
    public function test_b81_application_error_attempt_blocks_immediate_duplicate_review_post(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v2.5.0/passenger/records?mode=create',
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $booking = $this->b63SabreBookingWithOffer(
            $agency->id,
            $sabreConn->id,
            $this->b63SabreTwoSegmentOffer($sabreConn->id, $depart),
            $depart,
            'sabre-b81-app@example.com',
        );

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $sabreConn->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'error_message' => 'Application error.',
            'safe_summary' => [
                'source' => 'sabre_public_checkout',
                'live_call_attempted' => true,
                'http_status' => 200,
            ],
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        $response = $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later']);

        $response->assertRedirect(route('booking.review'));
        $response->assertSessionHasErrors(['booking']);
        $err = session('errors')->get('booking');
        $this->assertIsArray($err);
        $this->assertStringContainsString('review', strtolower((string) ($err[0] ?? '')));

        $this->assertNoPassengerRecordsHttpPost();
    }

    /**
     * B81: Failure before any live Sabre call (booking disabled) does not trap the customer — second submit works once enabled.
     */
    public function test_b81_preflight_validation_failure_does_not_block_corrected_resubmit(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => false,
            'suppliers.sabre.booking_live_call_enabled' => false,
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => null,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-b81-offer-dry',
            'supplier_provider' => 'sabre',
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T14:00:00Z',
            'total' => 100000,
            'currency' => 'PKR',
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'requires_price_change_confirmation' => false,
                'protection_mode' => 'hold_price_guaranteed',
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'sabre-b81-preflight@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.review'))
            ->assertSessionHasErrors(['booking']);

        Http::assertNothingSent();

        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => false,
        ]);

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'));

        $booking->refresh();
        $this->assertNotNull($booking->submitted_at);
        $this->assertFalse((bool) config('suppliers.sabre.ticketing_enabled'));
    }

    public function test_b74_pnr_only_multi_segment_creates_pnr_with_itinerary_advisory(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $recordsPath = '/v2.5.0/passenger/records?mode=create';

        $this->sabreStubOAuthAndHttp(function (Request $request) use ($recordsPath) {
            if (str_contains($request->url(), '/revalidate')) {
                return Http::response(['errors' => [['code' => '27131', 'message' => 'fail']]], 400);
            }
            if (str_contains($request->url(), $recordsPath)) {
                return Http::response([
                    'CreatePassengerNameRecordRS' => [
                        'ApplicationResults' => ['status' => 'Complete'],
                        'ItineraryRef' => ['ID' => 'B74MULTI'],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_mode' => 'pnr_only',
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => $recordsPath,
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
            'suppliers.sabre.revalidate_before_booking' => true,
            'suppliers.sabre.revalidate_path' => '/v4/shop/flights/revalidate',
            'suppliers.sabre.allow_createbooking_without_revalidation' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => true,
            'suppliers.sabre.passenger_records_allow_verified_multi_segment' => false,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()->where('agency_id', $agency->id)->where('provider', 'sabre')->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $booking = $this->b63SabreBookingWithOffer(
            $agency->id,
            $sabreConn->id,
            $this->b63SabreTwoSegmentOffer($sabreConn->id, $depart),
            $depart,
            'sabre-b74-multi@example.com',
        );

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'));

        $booking->refresh();
        $this->assertSame('B74MULTI', $booking->pnr);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertTrue((bool) data_get($meta, 'sabre_checkout_outcome.passenger_records_itinerary_advisory'));
        $this->assertSame('multi_segment', data_get($meta, 'sabre_checkout_outcome.guard_trigger'));
        $this->assertFalse((bool) data_get($meta, 'sabre_checkout_outcome.revalidation_attempted'));
        $this->assertSame(1, SupplierBooking::query()->where('booking_id', $booking->id)->count());

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/revalidate'));
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/passenger/records'));
    }

    public function test_b74_pnr_only_segment_order_corrected_creates_pnr(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $recordsPath = '/v2.5.0/passenger/records?mode=create';

        $this->sabreStubOAuthAndHttp(fn () => Http::response([
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => ['status' => 'Complete'],
                'ItineraryRef' => ['ID' => 'B74ORD'],
            ],
        ], 200));
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_mode' => 'pnr_only',
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => $recordsPath,
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => true,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()->where('agency_id', $agency->id)->where('provider', 'sabre')->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-b74-offer-order-corrected',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'PK',
            'depart_at' => $depart.'T05:00:00Z',
            'arrive_at' => $depart.'T08:00:00Z',
            'total' => 100000,
            'currency' => 'PKR',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'carrier' => 'PK',
                'flight_number' => '301',
                'departure_at' => $depart.'T05:00:00Z',
                'arrival_at' => $depart.'T08:00:00Z',
                'booking_class' => 'Y',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100000,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'raw_payload' => [
                'sabre_segment_order' => [
                    'segment_order_corrected' => true,
                    'original_segment_routes_sample' => ['DXB→LHE'],
                    'corrected_segment_routes_sample' => ['LHE→DXB'],
                ],
            ],
        ];
        $booking = $this->b63SabreBookingWithOffer($agency->id, $sabreConn->id, $offer, $depart, 'sabre-b74-order@example.com');

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'));

        $booking->refresh();
        $this->assertSame('B74ORD', $booking->pnr);
        $this->assertSame('segment_order_corrected', data_get($booking->meta, 'sabre_checkout_outcome.guard_trigger'));
        $this->assertTrue((bool) data_get($booking->meta, 'sabre_checkout_outcome.passenger_records_itinerary_advisory'));
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/passenger/records'));
    }

    public function test_b74_pnr_only_multi_segment_host_incomplete_needs_review_without_pnr(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $recordsPath = '/v2.5.0/passenger/records?mode=create';

        $this->sabreStubOAuthAndHttp(fn () => Http::response([
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => ['status' => 'Incomplete'],
            ],
        ], 200));
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_mode' => 'pnr_only',
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => $recordsPath,
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => true,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()->where('agency_id', $agency->id)->where('provider', 'sabre')->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $booking = $this->b63SabreBookingWithOffer(
            $agency->id,
            $sabreConn->id,
            $this->b63SabreTwoSegmentOffer($sabreConn->id, $depart),
            $depart,
            'sabre-b74-incomplete@example.com',
        );

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'));

        $booking->refresh();
        $this->assertNull($booking->pnr);
        $this->assertSame('needs_review', data_get($booking->meta, 'sabre_checkout_outcome.status'));
        $this->assertTrue((bool) data_get($booking->meta, 'sabre_checkout_outcome.live_call_attempted'));
        $this->assertTrue((bool) data_get($booking->meta, 'sabre_checkout_outcome.application_results_incomplete'));
        $this->assertSame(0, SupplierBooking::query()->where('booking_id', $booking->id)->count());
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/passenger/records'));
    }

    public function test_sabre_revalidate_payload_merges_identifiers_into_partial_shop_context(): void
    {
        $builder = app(SabreRevalidationPayloadBuilder::class);
        $draft = $this->sabreRevalidationDraft([
            '_sabre_shop_context' => [
                'itinerary_ref' => 'itin-merge-b15',
                'leg_refs' => [1, 2],
            ],
            '_sabre_shop_identifiers' => [
                'pricing_0_ref' => 'pi-b15-ref',
                'pricing_0_id' => 'pi-b15-id',
                'pricing_0_offerItemId' => 'oi-b15-1',
            ],
        ]);
        $payload = $builder->buildPayload($draft);
        $this->assertSame('pi-b15-ref', data_get($payload, 'pricingInformation.0.ref'));
        $this->assertSame('pi-b15-id', data_get($payload, 'pricingInformation.0.id'));
        $this->assertSame('itin-merge-b15', data_get($payload, 'itinerary.id'));
        $summary = $builder->safePayloadSummary($payload);
        $this->assertTrue($summary['has_pricing_information_ref']);
        $this->assertTrue($summary['has_itinerary_reference']);
        $this->assertTrue($summary['has_offer_reference']);
    }

    public function test_sabre_revalidate_error_digest_includes_heuristic_hint_for_numeric_27131(): void
    {
        $digest = app(SabreRevalidationPayloadBuilder::class)->extractSafeErrorDigest([
            'errors' => [[
                'code' => 'GCB15-ISELL-TN-00-2026-04-00-SX5S',
                'message' => '27131',
            ]],
        ]);
        $this->assertContains('27131', $digest['response_error_messages'] ?? []);
        $this->assertNotEmpty($digest['response_error_hints'] ?? []);
        $this->assertStringContainsString('Heuristic', (string) ($digest['response_error_hints'][0] ?? ''));
    }

    public function test_sabre_booking_payload_merge_keeps_shop_context_and_fills_missing_pricing_from_identifiers(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $offer = [
            'offer_id' => 'merge-offer-1',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => 1,
            'airline_code' => 'PK',
            'flight_number' => '303',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'KHI',
                'departure_at' => '2026-08-01T10:00:00',
                'arrival_at' => '2026-08-01T11:30:00',
                'carrier' => 'PK',
                'flight_number' => '303',
                'booking_class' => 'Y',
                'fare_basis_code' => 'YOW',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100,
                'currency' => 'USD',
                'base_fare' => 80,
                'taxes' => 20,
            ],
            'baggage' => [],
            'raw_payload' => [
                'sabre_shop_context' => [
                    'itinerary_ref' => 'itin-merge-c15',
                    'leg_refs' => [1],
                ],
                'sabre_shop_identifiers' => [
                    'pricing_0_ref' => 'pmerge-1',
                ],
            ],
        ];
        $draft = $builder->buildInternalDraft($offer, [
            'passengers' => [['type' => 'ADT', 'first_name' => 'T', 'last_name' => 'P']],
            'contact' => ['email' => '', 'phone' => ''],
        ]);
        $this->assertSame('itin-merge-c15', $draft['_sabre_shop_context']['itinerary_ref'] ?? null);
        $this->assertSame('pmerge-1', $draft['_sabre_shop_context']['pricing_0_ref'] ?? null);
        $this->assertSame(['1'], $draft['_sabre_shop_context']['leg_refs'] ?? null);
    }

    public function test_b62_passenger_records_live_application_complete_persists_pnr_and_supplier_booking(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $recordsPath = '/v2.5.0/passenger/records?mode=create';

        $this->sabreStubOAuthAndHttp(fn () => Http::response([
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => ['status' => 'Complete'],
                'ItineraryRef' => ['ID' => 'ABCDEF'],
            ],
        ], 200));
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => $recordsPath,
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.allow_createbooking_without_revalidation' => false,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-b62-offer-1',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T14:00:00Z',
            'total' => 100000,
            'currency' => 'PKR',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'carrier' => 'EK',
                'flight_number' => '615',
                'departure_at' => $depart.'T08:00:00Z',
                'arrival_at' => $depart.'T14:00:00Z',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLITE',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100000,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
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
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create(array_merge([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ], $this->sabreIntlPassengerPassportFields()));

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'sabre-b62@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), [
                'booking_method' => 'pay_later',
            ])
            ->assertRedirect(route('booking.confirmation'));

        $booking->refresh();
        $this->assertSame('ABCDEF', $booking->pnr);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertSame('pending_payment_or_ticketing', data_get($meta, 'sabre_checkout_outcome.status'));
        $this->assertNull(data_get($meta, 'sabre_checkout_outcome.sabre_host_classification'));
        $sb = SupplierBooking::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $this->assertNotNull($sb);
        $this->assertSame('ABCDEF', $sb->pnr);
        $this->assertSame('pending_ticketing', $sb->status);
        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $this->assertNotNull($attempt);
        $this->assertSame('success', $attempt->status);
        $attemptSummary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertTrue($attemptSummary['ticketing_disabled'] ?? false);
        $this->assertTrue($attemptSummary['ticketing_pending'] ?? false);

        Http::assertSent(function ($request): bool {
            return $request instanceof Request
                && str_contains((string) $request->url(), '/v2.5.0/passenger/records')
                && $request->hasHeader('Conversation-ID');
        });
    }

    public function test_b62_passenger_records_incomplete_http200_does_not_persist_pnr(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $recordsPath = '/v2.5.0/passenger/records?mode=create';

        $this->sabreStubOAuthAndHttp(fn () => Http::response([
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => ['status' => 'Incomplete'],
                'dummy' => 'ok',
            ],
        ], 200));
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => $recordsPath,
            'suppliers.sabre.booking_schema' => 'passenger_records_create_pnr',
            'suppliers.sabre.revalidate_before_booking' => false,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-b62-offer-2',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'carrier' => 'EK',
                'flight_number' => '616',
                'departure_at' => $depart.'T09:00:00Z',
                'arrival_at' => $depart.'T15:00:00Z',
                'booking_class' => 'K',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 95000,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
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
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create(array_merge([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ], $this->sabreIntlPassengerPassportFields()));

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'sabre-b62-inc@example.com',
            'phone' => '+923001234569',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 75000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 95000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'));

        $booking->refresh();
        $this->assertNull($booking->pnr);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertSame('needs_review', data_get($meta, 'sabre_checkout_outcome.status'));
        $co = data_get($meta, 'sabre_checkout_outcome');
        $co = is_array($co) ? $co : [];
        $this->assertTrue(($co['application_results_incomplete'] ?? false) === true);
        $this->assertSame(0, SupplierBooking::query()->where('booking_id', $booking->id)->count());
        $attemptInc = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $this->assertNotNull($attemptInc);
        $this->assertSame('needs_review', $attemptInc->status);
    }

    public function test_b62_passenger_records_http400_does_not_persist_pnr(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $recordsPath = '/v2.5.0/passenger/records?mode=create';

        $this->sabreStubOAuthAndHttp(fn () => Http::response(['errorCode' => 'VALIDATION_FAILED', 'message' => 'schema'], 400));
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => $recordsPath,
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
            'suppliers.sabre.revalidate_before_booking' => false,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-b62-offer-3',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'carrier' => 'EK',
                'flight_number' => '617',
                'departure_at' => $depart.'T10:00:00Z',
                'arrival_at' => $depart.'T16:00:00Z',
                'booking_class' => 'K',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 90000,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
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
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create(array_merge([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ], $this->sabreIntlPassengerPassportFields()));

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'sabre-b62-400@example.com',
            'phone' => '+923001234570',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 70000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 90000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.review'))
            ->assertSessionHasErrors(['booking']);

        $booking->refresh();
        $this->assertNull($booking->pnr);
        $this->assertSame(0, SupplierBooking::query()->where('booking_id', $booking->id)->count());
        $attempt400 = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $this->assertNotNull($attempt400);
        $this->assertSame('failed', $attempt400->status);
        $this->assertSame('sabre_booking_validation_failed', $attempt400->error_code);
    }

    public function test_b63_passenger_records_guard_blocks_two_segments_without_live_post(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $recordsPath = '/v2.5.0/passenger/records?mode=create';

        $this->sabreStubOAuthAndHttp(fn () => Http::response([
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => ['status' => 'Complete'],
                'ItineraryRef' => ['ID' => 'GUARD01'],
            ],
        ], 200));
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_mode' => 'certified',
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => $recordsPath,
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => true,
            'suppliers.sabre.passenger_records_allow_verified_multi_segment' => false,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $offer = $this->b63SabreTwoSegmentOffer($sabreConn->id, $depart);
        $booking = $this->b63SabreBookingWithOffer($agency->id, $sabreConn->id, $offer, $depart, 'sabre-b63-guard-2seg@example.com');

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'));

        $booking->refresh();
        $this->assertNull($booking->pnr);
        $this->assertSame('manual_review', $booking->supplier_booking_status);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertSame('needs_review', data_get($meta, 'sabre_checkout_outcome.status'));
        $this->assertSame('sabre_passenger_records_itinerary_guard', data_get($meta, 'sabre_checkout_outcome.error_code'));
        $this->assertFalse((bool) data_get($meta, 'sabre_checkout_outcome.live_call_attempted'));
        $this->assertNull(data_get($meta, 'sabre_checkout_outcome.sabre_host_classification'));
        $this->assertSame('multi_segment', data_get($meta, 'sabre_checkout_outcome.guard_trigger'));
        $this->assertSame(0, SupplierBooking::query()->where('booking_id', $booking->id)->count());
        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $this->assertNotNull($attempt);
        $this->assertSame('needs_review', $attempt->status);
        $this->assertSame('sabre_passenger_records_itinerary_guard', $attempt->error_code);
        $summary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertTrue($summary['ticketing_disabled'] ?? false);
        $this->assertFalse($summary['live_call_attempted'] ?? true);

        Http::assertNotSent(function ($request): bool {
            return $request instanceof Request
                && str_contains((string) $request->url(), '/passenger/records');
        });
    }

    public function test_d2f_c2_passenger_records_uc_application_error_persists_host_classification_in_meta_only(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $recordsPath = '/v2.5.0/passenger/records?mode=create';

        $this->sabreStubOAuthAndHttp(fn () => Http::response([
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => [
                    'status' => 'Incomplete',
                    'Error' => [
                        [
                            'SystemSpecificResults' => [
                                [
                                    'Message' => [
                                        ['content' => 'WARN.SP.HALT_ON_STATUS_RECEIVED'],
                                        ['content' => 'Flight SV739 returned status code UC'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200));
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => $recordsPath,
            'suppliers.sabre.booking_schema' => 'passenger_records_create_pnr',
            'suppliers.sabre.revalidate_before_booking' => false,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-d2f-c2-uc-offer',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'SV',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'JED',
                'carrier' => 'SV',
                'flight_number' => '739',
                'departure_at' => $depart.'T09:00:00Z',
                'arrival_at' => $depart.'T12:00:00Z',
                'booking_class' => 'K',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 95000,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
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
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'JED',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create(array_merge([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ], $this->sabreIntlPassengerPassportFields()));

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'sabre-d2f-c2-uc@example.com',
            'phone' => '+923001234570',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 75000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 95000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        $svc = app(SabreBookingService::class);
        $result = $svc->runPublicReviewDryRun($booking->fresh(['passengers', 'contact', 'fareBreakdown']));

        $this->assertArrayNotHasKey('sabre_host_classification', $result);
        $this->assertSame('needs_review', $result['status'] ?? null);
        $this->assertSame('sabre_booking_application_error', $result['error_code'] ?? null);
        $this->assertTrue((bool) ($result['live_call_attempted'] ?? false));

        $booking->refresh();
        $this->assertNull($booking->pnr);
        $classification = data_get($booking->meta, 'sabre_checkout_outcome.sabre_host_classification');
        $this->assertIsArray($classification);
        $this->assertSame('host_sell_rejected_uc', $classification['safe_reason_code'] ?? null);
        $this->assertSame('UC_SEGMENT_STATUS', $classification['host_error_family'] ?? null);
        $this->assertSame('sabre_host_classifier_v1', $classification['classifier_version'] ?? null);
        $this->assertNotEmpty($classification['admin_summary'] ?? null);
        $this->assertNotEmpty($classification['recorded_at'] ?? null);
        $this->assertSame('no_retry_same_offer', $classification['retry_policy'] ?? null);
        $this->assertTrue($classification['manual_review_required'] ?? false);
        $this->assertSame('airbook_sell', $classification['source_layer'] ?? null);
        $signals = is_array($classification['matched_signals'] ?? null) ? $classification['matched_signals'] : [];
        $this->assertNotEmpty(array_filter($signals, fn (mixed $signal): bool => is_string($signal) && str_contains(strtoupper($signal), 'UC')));

        $encoded = json_encode($classification, JSON_THROW_ON_ERROR);
        foreach ([
            'CreatePassengerNameRecordRQ',
            'PassengerName',
            'FormOfPayment',
            'Telephone',
            'targetCity',
            'raw',
            'response_body',
            'sabre-d2f-c2-uc@example.com',
            '+923001234570',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $encoded, 'Classification must not echo forbidden key/PII: '.$forbidden);
        }
    }

    public function test_b63_passenger_records_guard_blocks_segment_order_corrected_without_live_post(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $recordsPath = '/v2.5.0/passenger/records?mode=create';

        $this->sabreStubOAuthAndHttp(fn () => Http::response([
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => ['status' => 'Complete'],
                'ItineraryRef' => ['ID' => 'GUARD02'],
            ],
        ], 200));
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_mode' => 'certified',
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => $recordsPath,
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => true,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-b63-offer-order-corrected',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'PK',
            'depart_at' => $depart.'T05:00:00Z',
            'arrive_at' => $depart.'T08:00:00Z',
            'total' => 100000,
            'currency' => 'PKR',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'carrier' => 'PK',
                'flight_number' => '301',
                'departure_at' => $depart.'T05:00:00Z',
                'arrival_at' => $depart.'T08:00:00Z',
                'booking_class' => 'Y',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 100000,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'raw_payload' => [
                'sabre_segment_order' => [
                    'segment_order_corrected' => true,
                    'original_segment_routes_sample' => ['DXB→LHE'],
                    'corrected_segment_routes_sample' => ['LHE→DXB'],
                ],
            ],
        ];
        $booking = $this->b63SabreBookingWithOffer($agency->id, $sabreConn->id, $offer, $depart, 'sabre-b63-guard-order@example.com');

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'));

        $booking->refresh();
        $this->assertNull($booking->pnr);
        $this->assertSame('manual_review', $booking->supplier_booking_status);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertSame('segment_order_corrected', data_get($meta, 'sabre_checkout_outcome.guard_trigger'));
        $this->assertFalse((bool) data_get($meta, 'sabre_checkout_outcome.live_call_attempted'));
        $this->assertSame(0, SupplierBooking::query()->where('booking_id', $booking->id)->count());

        Http::assertNotSent(function ($request): bool {
            return $request instanceof Request
                && str_contains((string) $request->url(), '/passenger/records');
        });
    }

    public function test_b63_trip_orders_two_segments_not_blocked_by_passenger_records_guard(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->sabreStubOAuthAndHttp(fn () => Http::response(['order' => ['id' => 'ORD-B63']], 200));
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => 'trip_orders_create_booking',
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => true,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $offer = $this->b63SabreTwoSegmentOffer($sabreConn->id, $depart);
        $booking = $this->b63SabreBookingWithOffer($agency->id, $sabreConn->id, $offer, $depart, 'sabre-b63-trip-2seg@example.com');

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later']);

        Http::assertSent(function ($request): bool {
            return $request instanceof Request
                && str_contains((string) $request->url(), '/v1/trip/orders/createBooking');
        });
    }

    public function test_b64_admin_booking_show_displays_passenger_records_itinerary_guard_notice(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
            'supplier' => SupplierProvider::Sabre->value,
            'supplier_booking_status' => 'manual_review',
            'pnr' => null,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'sabre_checkout_outcome' => [
                    'status' => 'needs_review',
                    'error_code' => 'sabre_passenger_records_itinerary_guard',
                    'live_call_attempted' => false,
                    'guard_trigger' => 'multi_segment',
                    'segment_count' => 2,
                    'segment_order_corrected' => true,
                ],
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Manual Review Required', false)
            ->assertSee('Passenger Records risky itinerary guard', false)
            ->assertSee('multi_segment', false)
            ->assertSee('Segment count', false)
            ->assertSee('Live Sabre call attempted', false)
            ->assertSee('Not created', false)
            ->assertSee('Disabled / pending manual', false)
            ->assertSee('Create/check booking manually in Sabre or use alternate supplier flow.', false);
    }

    public function test_b65_allow_verified_valid_multi_segment_creates_pnr(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $recordsPath = '/v2.5.0/passenger/records?mode=create';
        $sabreBase = rtrim((string) config('suppliers.sabre.default_base_url'), '/');

        $this->sabreStubOAuthAndHttp(function (Request $request) use ($recordsPath) {
            if (str_contains($request->url(), '/revalidate')) {
                return Http::response($this->b67SabreTwoSegmentRevalidateJson(), 200);
            }
            if (str_contains($request->url(), $recordsPath)) {
                return Http::response([
                    'CreatePassengerNameRecordRS' => [
                        'ApplicationResults' => ['status' => 'Complete'],
                        'ItineraryRef' => ['ID' => 'MULTI1'],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_mode' => 'certified',
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => $recordsPath,
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
            'suppliers.sabre.revalidate_before_booking' => true,
            'suppliers.sabre.revalidate_path' => '/v4/shop/flights/revalidate',
            'suppliers.sabre.allow_createbooking_without_revalidation' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => true,
            'suppliers.sabre.passenger_records_allow_verified_multi_segment' => true,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $offer = $this->b65SabreTwoSegmentOffer($sabreConn->id, $depart);
        $booking = $this->b63SabreBookingWithOffer($agency->id, $sabreConn->id, $offer, $depart, 'sabre-b65-ok@example.com');

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'));

        $booking->refresh();
        $this->assertSame('MULTI1', $booking->pnr);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertTrue((bool) data_get($meta, 'sabre_checkout_outcome.passenger_records_multi_segment_eligible'));
        $this->assertTrue((bool) data_get($meta, 'sabre_checkout_outcome.passenger_records_multi_segment_revalidation_ok'));
        $this->assertTrue((bool) data_get($meta, 'sabre_checkout_outcome.passenger_records_multi_segment_revalidation_applied'));
        Http::assertSent(fn ($request): bool => $request instanceof Request
            && str_contains((string) $request->url(), '/passenger/records'));
    }

    public function test_b67_multi_segment_revalidation_disabled_guarded_no_live_post(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->sabreStubOAuthAndHttp(fn () => Http::response([
            'CreatePassengerNameRecordRS' => ['ApplicationResults' => ['status' => 'Complete']],
        ], 200));
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_mode' => 'certified',
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v2.5.0/passenger/records?mode=create',
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => true,
            'suppliers.sabre.passenger_records_allow_verified_multi_segment' => true,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()->where('agency_id', $agency->id)->where('provider', 'sabre')->firstOrFail();
        $depart = now()->addDays(10)->toDateString();
        $booking = $this->b63SabreBookingWithOffer($agency->id, $sabreConn->id, $this->b65SabreTwoSegmentOffer($sabreConn->id, $depart), $depart, 'sabre-b67-rev-off@example.com');

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'));

        $booking->refresh();
        $this->assertNull($booking->pnr);
        $this->assertSame('multi_segment_revalidation_required', data_get($booking->meta, 'sabre_checkout_outcome.guard_trigger'));
        $this->assertNoPassengerRecordsHttpPost();
    }

    public function test_b67_multi_segment_revalidation_failed_guarded_no_live_post(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->sabreStubOAuthAndHttp(function (Request $request) {
            if (str_contains($request->url(), '/revalidate')) {
                return Http::response(['errors' => [['code' => 'ERR', 'message' => 'fail']]], 400);
            }

            return Http::response(['CreatePassengerNameRecordRS' => ['ApplicationResults' => ['status' => 'Complete']]], 200);
        });
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_mode' => 'certified',
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v2.5.0/passenger/records?mode=create',
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
            'suppliers.sabre.revalidate_before_booking' => true,
            'suppliers.sabre.revalidate_path' => '/v4/shop/flights/revalidate',
            'suppliers.sabre.allow_createbooking_without_revalidation' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => true,
            'suppliers.sabre.passenger_records_allow_verified_multi_segment' => true,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()->where('agency_id', $agency->id)->where('provider', 'sabre')->firstOrFail();
        $depart = now()->addDays(10)->toDateString();
        $booking = $this->b63SabreBookingWithOffer($agency->id, $sabreConn->id, $this->b65SabreTwoSegmentOffer($sabreConn->id, $depart), $depart, 'sabre-b67-rev-fail@example.com');

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.review'))
            ->assertSessionHasErrors('booking');

        $booking->refresh();
        $this->assertNull($booking->pnr);
        $this->assertSame('sabre_revalidation_failed', data_get($booking->meta, 'sabre_checkout_outcome.error_code'));
        $this->assertNoPassengerRecordsHttpPost();
    }

    public function test_b67_merge_revalidated_class_of_service_into_segments(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $segments = [
            ['origin' => 'LHE', 'destination' => 'KHI', 'booking_class' => 'X'],
            ['origin' => 'KHI', 'destination' => 'JED', 'booking_class' => 'X'],
        ];
        $linkage = [
            'per_segment' => [
                ['origin' => 'LHE', 'destination' => 'KHI', 'class_of_service' => 'Y'],
                ['origin' => 'KHI', 'destination' => 'JED', 'class_of_service' => 'M'],
            ],
        ];
        $merged = $builder->mergeRevalidatedClassOfServiceIntoSegments($segments, $linkage);
        $this->assertSame('Y', $merged[0]['booking_class']);
        $this->assertSame('M', $merged[1]['booking_class']);
        $this->assertTrue($builder->linkageCoversSegmentsWithClassOfService($merged, $linkage));

        $draft = [
            '_valid' => true,
            'segments' => $merged,
            'passengers' => [['first_name' => 'A', 'last_name' => 'B', 'type' => 'ADT']],
            'fare' => ['amount' => 100, 'currency' => 'PKR'],
            'contact' => ['email' => 'a@example.com', 'phone' => '+923001234567'],
            'supplier_connection_id' => 1,
        ];
        $wire = $builder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($draft);
        $segs = data_get($wire, 'CreatePassengerNameRecordRQ.AirBook.OriginDestinationInformation.FlightSegment');
        $this->assertIsArray($segs);
        $list = array_is_list($segs) ? $segs : [$segs];
        $this->assertSame('Y', $list[0]['ResBookDesigCode'] ?? null);
        $this->assertSame('M', $list[1]['ResBookDesigCode'] ?? null);
    }

    public function test_b67_direct_create_booking_broken_continuity_guarded(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->sabreStubOAuthAndHttp(function (Request $request) {
            if (str_contains($request->url(), '/revalidate')) {
                return Http::response($this->b67SabreTwoSegmentRevalidateJson(), 200);
            }

            return Http::response(['CreatePassengerNameRecordRS' => ['ApplicationResults' => ['status' => 'Complete']]], 200);
        });
        config([
            'suppliers.sabre.booking_mode' => 'certified',
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v2.5.0/passenger/records?mode=create',
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
            'suppliers.sabre.revalidate_before_booking' => true,
            'suppliers.sabre.revalidate_path' => '/v4/shop/flights/revalidate',
            'suppliers.sabre.allow_createbooking_without_revalidation' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => true,
            'suppliers.sabre.passenger_records_allow_verified_multi_segment' => true,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()->where('agency_id', $agency->id)->where('provider', 'sabre')->firstOrFail();
        $this->activateSabreConnectionForHttp($sabreConn);
        $depart = now()->addDays(10)->toDateString();
        $offer = $this->b65SabreTwoSegmentOffer($sabreConn->id, $depart);
        $offer['segments'] = [
            [
                'origin' => 'LHE',
                'destination' => 'KHI',
                'carrier' => 'PK',
                'flight_number' => '301',
                'departure_at' => $depart.'T05:00:00Z',
                'arrival_at' => $depart.'T06:45:00Z',
                'booking_class' => 'Y',
            ],
            [
                'origin' => 'KHI',
                'destination' => 'DXB',
                'carrier' => 'PK',
                'flight_number' => '741',
                'departure_at' => $depart.'T08:30:00Z',
                'arrival_at' => $depart.'T12:00:00Z',
                'booking_class' => 'Y',
            ],
        ];

        $result = app(SabreBookingService::class)->createBooking(
            $offer,
            $this->sabrePassengerDataForRevalidation(),
        );

        $this->assertSame('sabre_passenger_records_itinerary_guard', $result['error_code'] ?? null);
        $this->assertSame('multi_segment_validation_failed', $result['guard_trigger'] ?? null);
        $this->assertFalse((bool) ($result['live_call_attempted'] ?? true));
    }

    public function test_b65_allow_verified_broken_continuity_stays_guarded(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->sabreStubOAuthAndHttp(function (Request $request) {
            if (str_contains($request->url(), '/revalidate')) {
                return Http::response($this->b67SabreTwoSegmentRevalidateJson(), 200);
            }

            return Http::response(['CreatePassengerNameRecordRS' => ['ApplicationResults' => ['status' => 'Complete']]], 200);
        });
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_mode' => 'certified',
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v2.5.0/passenger/records?mode=create',
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
            'suppliers.sabre.revalidate_before_booking' => true,
            'suppliers.sabre.revalidate_path' => '/v4/shop/flights/revalidate',
            'suppliers.sabre.allow_createbooking_without_revalidation' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => true,
            'suppliers.sabre.passenger_records_allow_verified_multi_segment' => true,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()->where('agency_id', $agency->id)->where('provider', 'sabre')->firstOrFail();
        $this->activateSabreConnectionForHttp($sabreConn);
        $depart = now()->addDays(10)->toDateString();
        $offer = $this->b65SabreTwoSegmentOffer($sabreConn->id, $depart);
        $offer['segments'] = [
            [
                'origin' => 'LHE',
                'destination' => 'KHI',
                'carrier' => 'PK',
                'flight_number' => '301',
                'departure_at' => $depart.'T05:00:00Z',
                'arrival_at' => $depart.'T06:45:00Z',
                'booking_class' => 'Y',
            ],
            [
                'origin' => 'KHI',
                'destination' => 'DXB',
                'carrier' => 'PK',
                'flight_number' => '741',
                'departure_at' => $depart.'T08:30:00Z',
                'arrival_at' => $depart.'T12:00:00Z',
                'booking_class' => 'Y',
            ],
        ];
        $booking = $this->b63SabreBookingWithOffer($agency->id, $sabreConn->id, $offer, $depart, 'sabre-b65-bad-route@example.com');

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'));

        $booking->refresh();
        $this->assertNull($booking->pnr);
        $this->assertSame('sabre_passenger_records_itinerary_guard', data_get($booking->meta, 'sabre_checkout_outcome.error_code'));
        $this->assertSame('multi_segment_validation_failed', data_get($booking->meta, 'sabre_checkout_outcome.guard_trigger'));
        $this->assertNoPassengerRecordsHttpPost();
    }

    public function test_b65_allow_verified_missing_booking_class_stays_guarded(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->sabreStubOAuthAndHttp(function (Request $request) {
            if (str_contains($request->url(), '/revalidate')) {
                return Http::response($this->b67SabreTwoSegmentRevalidateJson(), 200);
            }

            return Http::response(['CreatePassengerNameRecordRS' => ['ApplicationResults' => ['status' => 'Complete']]], 200);
        });
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_mode' => 'certified',
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v2.5.0/passenger/records?mode=create',
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
            'suppliers.sabre.revalidate_before_booking' => true,
            'suppliers.sabre.revalidate_path' => '/v4/shop/flights/revalidate',
            'suppliers.sabre.allow_createbooking_without_revalidation' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => true,
            'suppliers.sabre.passenger_records_allow_verified_multi_segment' => true,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()->where('agency_id', $agency->id)->where('provider', 'sabre')->firstOrFail();
        $this->activateSabreConnectionForHttp($sabreConn);
        $depart = now()->addDays(10)->toDateString();
        $offer = $this->b65SabreTwoSegmentOffer($sabreConn->id, $depart);
        unset($offer['segments'][1]['booking_class']);
        $booking = $this->b63SabreBookingWithOffer($agency->id, $sabreConn->id, $offer, $depart, 'sabre-b65-no-rbd@example.com');

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'));

        $booking->refresh();
        $this->assertNull($booking->pnr);
        $this->assertFalse((bool) data_get($booking->meta, 'sabre_checkout_outcome.all_segments_have_booking_class'));
        $this->assertNoPassengerRecordsHttpPost();
    }

    public function test_b65_allow_verified_segment_order_corrected_wrong_order_stays_guarded(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->sabreStubOAuthAndHttp(function (Request $request) {
            if (str_contains($request->url(), '/revalidate')) {
                return Http::response($this->b67SabreTwoSegmentRevalidateJson(), 200);
            }

            return Http::response(['CreatePassengerNameRecordRS' => ['ApplicationResults' => ['status' => 'Complete']]], 200);
        });
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_mode' => 'certified',
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v2.5.0/passenger/records?mode=create',
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
            'suppliers.sabre.revalidate_before_booking' => true,
            'suppliers.sabre.revalidate_path' => '/v4/shop/flights/revalidate',
            'suppliers.sabre.allow_createbooking_without_revalidation' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => true,
            'suppliers.sabre.passenger_records_allow_verified_multi_segment' => true,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()->where('agency_id', $agency->id)->where('provider', 'sabre')->firstOrFail();
        $this->activateSabreConnectionForHttp($sabreConn);
        $depart = now()->addDays(10)->toDateString();
        $offer = $this->b65SabreTwoSegmentOffer($sabreConn->id, $depart);
        $offer['origin'] = 'ISB';
        $offer['raw_payload']['sabre_segment_order']['segment_order_corrected'] = true;
        $booking = $this->b63SabreBookingWithOffer($agency->id, $sabreConn->id, $offer, $depart, 'sabre-b65-order@example.com');

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'));

        $booking->refresh();
        $this->assertNull($booking->pnr);
        $this->assertTrue((bool) data_get($booking->meta, 'sabre_checkout_outcome.segment_order_corrected'));
        $this->assertNoPassengerRecordsHttpPost();
    }

    /**
     * @return array<string, mixed>
     */
    protected function b63SabreTwoSegmentOffer(int $connectionId, string $depart): array
    {
        $offer = $this->b65SabreTwoSegmentOffer($connectionId, $depart);
        $offer['id'] = 'sabre-b63-offer-2seg';

        return $offer;
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    protected function b67SabreTwoSegmentRevalidateJson(): array
    {
        return [
            'pricingInformation' => [[
                'fare' => [
                    'validatingCarrierCode' => 'PK',
                    'totalFare' => ['totalPrice' => 120000, 'currency' => 'PKR'],
                    'passengerInfoList' => [[
                        'passengerInfo' => [
                            'fareComponents' => [[
                                'fareBasisCode' => 'YLOW',
                                'segments' => [
                                    ['segment' => [
                                        'bookingCode' => 'Y',
                                        'departure' => ['locationCode' => 'LHE'],
                                        'arrival' => ['locationCode' => 'KHI'],
                                    ]],
                                    ['segment' => [
                                        'bookingCode' => 'Y',
                                        'departure' => ['locationCode' => 'KHI'],
                                        'arrival' => ['locationCode' => 'JED'],
                                    ]],
                                ],
                            ]],
                        ],
                    ]],
                ],
            ]],
        ];
    }

    protected function b65SabreTwoSegmentOffer(int $connectionId, string $depart): array
    {
        return [
            'id' => 'sabre-b65-offer-2seg',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $connectionId,
            'origin' => 'LHE',
            'destination' => 'JED',
            'airline_code' => 'PK',
            'depart_at' => $depart.'T05:00:00Z',
            'arrive_at' => $depart.'T12:00:00Z',
            'total' => 120000,
            'currency' => 'PKR',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'KHI',
                    'carrier' => 'PK',
                    'flight_number' => '301',
                    'departure_at' => $depart.'T05:00:00Z',
                    'arrival_at' => $depart.'T06:45:00Z',
                    'booking_class' => 'Y',
                ],
                [
                    'origin' => 'KHI',
                    'destination' => 'JED',
                    'carrier' => 'PK',
                    'flight_number' => '741',
                    'departure_at' => $depart.'T08:30:00Z',
                    'arrival_at' => $depart.'T12:00:00Z',
                    'booking_class' => 'Y',
                ],
            ],
            'fare_breakdown' => [
                'supplier_total' => 120000,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'raw_payload' => [
                'sabre_segment_order' => [
                    'segment_order_corrected' => true,
                    'original_segment_routes_sample' => ['KHI→JED', 'LHE→KHI'],
                    'corrected_segment_routes_sample' => ['LHE→KHI', 'KHI→JED'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function b63SabreBookingWithOffer(int $agencyId, int $connectionId, array $offer, string $depart, string $email): Booking
    {
        $booking = Booking::factory()->create([
            'agency_id' => $agencyId,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $connectionId,
                'requires_price_change_confirmation' => false,
                'protection_mode' => 'hold_price_guaranteed',
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'JED',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create(array_merge([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ], $this->sabreIntlPassengerPassportFields()));

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => $email,
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 90000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => (float) ($offer['total'] ?? 120000),
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        return $booking;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function sabreRevalidationDraft(array $extra = []): array
    {
        return array_merge([
            'selected_offer_id' => 'offer-rv-1',
            'supplier_offer_id' => 'offer-rv-1',
            'supplier_connection_id' => 1,
            'validating_carrier' => 'PK',
            'fare' => ['amount' => 150000, 'currency' => 'PKR', 'base_fare' => 120000, 'taxes' => 30000],
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-06-15T05:00:00',
                'arrival_at' => '2026-06-15T08:00:00',
                'carrier' => 'PK',
                'operating_airline_code' => 'PK',
                'flight_number' => '303',
                'booking_class' => 'Y',
                'fare_basis_code' => 'YCTX1',
            ]],
            'passengers' => [['type' => 'ADT', 'first_name' => 'Test', 'last_name' => 'Passenger']],
        ], $extra);
    }

    protected function sabreOfferForRevalidation(int $connectionId): array
    {
        return [
            'offer_id' => 'offer-rv-fail',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $connectionId,
            'airline_code' => 'PK',
            'validating_carrier' => 'PK',
            'segments' => $this->sabreRevalidationDraft()['segments'],
            'fare_breakdown' => [
                'supplier_total' => 150000,
                'currency' => 'PKR',
                'base_fare' => 120000,
                'taxes' => 30000,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'baggage' => ['summary' => '1PC'],
            'raw_payload' => [
                'sabre_shop_context' => [
                    'itinerary_ref' => 'itin-rv-fail',
                    'pricing_information_ref' => 'offer-rv-fail',
                    'leg_refs' => [1],
                    'schedule_refs' => [1],
                    'fare_basis_codes' => ['YCTX1'],
                ],
            ],
        ];
    }

    protected function sabrePassengerDataForRevalidation(): array
    {
        return [
            'passengers' => [[
                'passenger_type' => 'adult',
                'first_name' => 'Test',
                'last_name' => 'Passenger',
                'gender' => 'M',
                'date_of_birth' => '1990-01-01',
                'passport_number' => 'P123456',
                'passport_issuing_country' => 'PK',
                'passport_expiry_date' => '2030-01-01',
                'nationality' => 'PK',
            ]],
            'contact' => ['email' => 'safe@example.test', 'phone' => '+923001234567'],
        ];
    }

    public function test_b77_fresh_shop_guard_pass_allows_passenger_records_when_segment_in_shop(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $recordsPath = '/v2.5.0/passenger/records?mode=create';
        $fixture = json_decode((string) file_get_contents(base_path('tests/Fixtures/sabre_search_response.json')), true);
        $itinRef = &$fixture['groupedItineraryResponse']['itineraryGroups'][0]['itineraries'][0];
        $itinRef['pricingInformation'][0]['fare']['passengerInfoList'][0]['passengerInfo']['fareComponents'] = [
            [
                'fareBasisCode' => 'YLITE',
                'segments' => [
                    [
                        'segment' => [
                            'departure' => ['airport' => 'LHE'],
                            'arrival' => ['airport' => 'DXB'],
                            'marketingAirline' => ['code' => 'PK', 'flightNumber' => '203'],
                            'bookingCode' => 'Y',
                        ],
                    ],
                ],
            ],
        ];
        unset($itinRef);
        $this->sabreStubOAuthAndHttp(function (Request $request) use ($fixture) {
            $url = (string) $request->url();
            if (str_contains($url, 'offers/shop')) {
                return Http::response($fixture, 200);
            }
            if (str_contains($url, 'passenger/records')) {
                return Http::response([
                    'CreatePassengerNameRecordRS' => [
                        'ApplicationResults' => ['status' => 'Complete'],
                        'ItineraryRef' => ['ID' => 'B77GUARD'],
                    ],
                ], 200);
            }

            return Http::response(['error' => 'unexpected_test_url'], 500);
        });
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => $recordsPath,
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
            'suppliers.sabre.booking_mode' => 'pnr_only',
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.allow_createbooking_without_revalidation' => false,
            'suppliers.sabre.passenger_records_fresh_shop_guard_before_live' => true,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = '2026-06-10';
        $offer = [
            'id' => 'sabre-b77-offer-ok',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'PK',
            'airline_name' => 'Pakistan International',
            'depart_at' => $depart.'T08:30:00',
            'arrive_at' => $depart.'T11:15:00',
            'total' => 12500,
            'currency' => 'PKR',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'carrier' => 'PK',
                'flight_number' => '203',
                'departure_at' => $depart.'T08:30:00',
                'arrival_at' => $depart.'T11:15:00',
                'booking_class' => 'Y',
                'fare_basis_code' => 'YLITE',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 12500,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
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
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create(array_merge([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ], $this->sabreIntlPassengerPassportFields()));

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'b77-guard@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 8000,
            'taxes' => 2500,
            'fees' => 0,
            'markup' => 2000,
            'discount' => 0,
            'total' => 12500,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        Cache::flush();
        $svc = app(SabreBookingService::class);
        $outcome = $svc->runPublicReviewDryRun($booking->fresh(['passengers', 'contact', 'fareBreakdown']));
        $this->assertTrue((bool) ($outcome['success'] ?? false), json_encode($outcome, JSON_PRETTY_PRINT));
        $this->assertSame('B77GUARD', (string) ($outcome['pnr'] ?? ''));
        $booking->refresh();
        $this->assertSame('B77GUARD', $booking->pnr);

        Http::assertSent(fn (Request $request): bool => str_contains((string) $request->url(), 'offers/shop'));
        Http::assertSent(fn (Request $request): bool => str_contains((string) $request->url(), 'passenger/records'));
    }

    public function test_b77_fresh_shop_guard_blocks_passenger_records_and_stores_stale_meta(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $recordsPath = '/v2.5.0/passenger/records?mode=create';
        $emptyShop = ['groupedItineraryResponse' => ['itineraryGroups' => []]];

        $this->sabreStubOAuthAndHttp(function (Request $request) use ($emptyShop) {
            $url = (string) $request->url();
            if (str_contains($url, 'offers/shop')) {
                return Http::response($emptyShop, 200);
            }
            if (str_contains($url, 'passenger/records')) {
                return Http::response(['should_not_reach' => true], 200);
            }

            return Http::response(['error' => 'unexpected_test_url'], 500);
        });
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => $recordsPath,
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
            'suppliers.sabre.booking_mode' => 'pnr_only',
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.allow_createbooking_without_revalidation' => false,
            'suppliers.sabre.passenger_records_fresh_shop_guard_before_live' => true,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = '2026-06-10';
        $offer = [
            'id' => 'sabre-b77-offer-stale',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'PK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'carrier' => 'PK',
                'flight_number' => '203',
                'departure_at' => $depart.'T08:30:00',
                'arrival_at' => $depart.'T11:15:00',
                'booking_class' => 'V',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 12500,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
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
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create(array_merge([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ], $this->sabreIntlPassengerPassportFields()));

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'b77-stale@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 8000,
            'taxes' => 2500,
            'fees' => 0,
            'markup' => 2000,
            'discount' => 0,
            'total' => 12500,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        Cache::flush();
        $svc = app(SabreBookingService::class);
        $outcome = $svc->runPublicReviewDryRun($booking->fresh(['passengers', 'contact', 'fareBreakdown']));
        $this->assertSame('sabre_passenger_records_stale_shop_segment', $outcome['error_code'] ?? null);
        $this->assertFalse((bool) ($outcome['live_call_attempted'] ?? true));

        $booking->refresh();
        $this->assertNull($booking->pnr);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertSame('sabre_passenger_records_stale_shop_segment', data_get($meta, 'sabre_checkout_outcome.error_code'));
        $this->assertSame(0, data_get($meta, 'sabre_checkout_outcome.stale_segment_index'));
        $this->assertSame('LHE-DXB', data_get($meta, 'sabre_checkout_outcome.stale_segment_route'));
        $this->assertSame('PK203', data_get($meta, 'sabre_checkout_outcome.stale_segment_flight'));

        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $this->assertNotNull($attempt);
        $this->assertSame('sabre_passenger_records_stale_shop_segment', $attempt->error_code);
        $summary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertTrue($summary['ticketing_disabled'] ?? false);
        $this->assertContains((string) ($summary['probable_issue'] ?? ''), ['no_normalized_offers', 'shop_request_failed', 'shop_http_error', 'invalid_shop_json']);

        Http::assertNotSent(fn (Request $request): bool => str_contains((string) $request->url(), 'passenger/records'));
    }
}
