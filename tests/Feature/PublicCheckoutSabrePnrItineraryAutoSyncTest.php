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
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Support\PublicBooking;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicCheckoutSabrePnrItineraryAutoSyncTest extends TestCase
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
            'suppliers.sabre.passenger_records_fresh_shop_guard_before_live' => false,
            'suppliers.sabre.certified_route_selector_public_checkout_enabled' => false,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => null,
        ]);
    }

    public function test_public_pnr_success_triggers_pnr_itinerary_sync_once(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Cache::flush();

        $getBookingCalls = 0;
        $this->stubSabreCheckoutHttp(
            createBookingResponse: ['recordLocator' => 'PNRAUTO'],
            getBookingResponder: function () use (&$getBookingCalls) {
                $getBookingCalls++;

                return Http::response($this->cleanFlightsJson(), 200);
            },
        );

        $booking = $this->draftSabreBookingReadyForReviewSubmit();
        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'))
            ->assertSessionHas('sabre_checkout_notice');

        $booking->refresh();
        $this->assertSame('PNRAUTO', $booking->pnr);
        $this->assertSame('pending_payment_or_ticketing', $booking->supplier_booking_status);
        $this->assertSame('synced', data_get($booking->meta, 'pnr_itinerary_sync.status'));
        $this->assertIsArray(data_get($booking->meta, 'pnr_itinerary_snapshot'));
        $this->assertSame(1, $getBookingCalls);
        $this->assertSame(1, SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', 'pnr_retrieve')
            ->count());
    }

    public function test_partial_resource_unavailable_auto_sync_preserves_pnr_and_pending_ticketing(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Cache::flush();

        $partialJson = array_merge($this->cleanFlightsJson(), [
            'errors' => [['code' => 'RESOURCE_UNAVAILABLE']],
            'flights' => [[
                'fromAirportCode' => 'LHE',
                'toAirportCode' => 'KHI',
                'departureDate' => '2026-06-06',
                'departureTime' => '11:00',
                'arrivalDate' => '2026-06-06',
                'arrivalTime' => '12:45',
                'airlineCode' => 'PK',
                'flightNumber' => '303',
                'bookingClass' => 'V',
                'flightStatusCode' => 'HK',
                'confirmationId' => 'RQATZN',
            ]],
        ]);

        $this->stubSabreCheckoutHttp(
            createBookingResponse: ['recordLocator' => 'PPNYYM'],
            getBookingResponder: fn () => Http::response($partialJson, 200),
        );

        $booking = $this->draftSabreBookingReadyForReviewSubmit();
        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'))
            ->assertSessionHas('sabre_checkout_notice');

        $booking->refresh();
        $this->assertSame('PPNYYM', $booking->pnr);
        $this->assertSame('pending_payment_or_ticketing', $booking->supplier_booking_status);
        $this->assertNull(data_get($booking->meta, 'pnr_itinerary_snapshot'));
        $this->assertSame('partial_resource_unavailable', data_get($booking->meta, 'pnr_itinerary_sync.status'));
        $this->assertSame('RQATZN', data_get($booking->meta, 'pnr_itinerary_sync.airline_locator_value'));
        $this->assertSame('flights.0.confirmationId', data_get($booking->meta, 'pnr_itinerary_sync.airline_locator_path'));
        $this->assertFalse((bool) data_get($booking->meta, 'pnr_itinerary_sync.is_ticketed'));
        $this->assertSame(1, SupplierBooking::query()->where('booking_id', $booking->id)->count());
    }

    public function test_sync_failure_does_not_block_confirmation_or_remove_pnr(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Cache::flush();

        $this->stubSabreCheckoutHttp(
            createBookingResponse: ['recordLocator' => 'PNRFAIL'],
            getBookingResponder: fn () => Http::response([], 200),
        );

        $booking = $this->draftSabreBookingReadyForReviewSubmit();
        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'))
            ->assertSessionHas('sabre_checkout_notice');

        $booking->refresh();
        $this->assertSame('PNRFAIL', $booking->pnr);
        $this->assertSame('pending_payment_or_ticketing', $booking->supplier_booking_status);
        $this->assertSame('retrieve_failed', data_get($booking->meta, 'pnr_itinerary_sync.status'));
        $this->assertSame('get_booking_empty', data_get($booking->meta, 'pnr_itinerary_sync.reason_code'));
        $this->assertNull(data_get($booking->meta, 'pnr_itinerary_snapshot'));
    }

    public function test_needs_review_without_pnr_does_not_run_auto_sync(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Cache::flush();

        Http::fake(function (Request $request, array $options) {
            $payload = $options['laravel_data'] ?? [];
            $isOAuthRequest = $this->sabreHttpUrlLooksLikeToken($request->url())
                || (is_array($payload) && array_key_exists('grant_type', $payload));

            if ($isOAuthRequest) {
                return Http::response(['access_token' => 'tok-test-stub', 'expires_in' => 3600], 200);
            }
            if (str_contains($request->url(), 'trip/orders/createBooking')) {
                return Http::response([
                    'errors' => [
                        ['code' => 'MANDATORY_DATA_MISSING', 'message' => 'Missing data'],
                    ],
                ], 200);
            }
            if (str_contains($request->url(), 'trip/orders/getBooking')) {
                $this->fail('getBooking should not run when public checkout did not persist a PNR.');
            }

            return Http::response([], 404);
        });

        $booking = $this->draftSabreBookingReadyForReviewSubmit();
        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'))
            ->assertSessionHas('sabre_checkout_notice');

        $booking->refresh();
        $this->assertNull($booking->pnr);
        $this->assertNull(data_get($booking->meta, 'pnr_itinerary_sync'));
        $this->assertSame('needs_review', data_get($booking->meta, 'sabre_checkout_outcome.status'));
        $this->assertSame(0, SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', 'pnr_retrieve')
            ->count());
    }

    public function test_auto_sync_skips_when_pnr_itinerary_sync_sidecar_already_present(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Cache::flush();

        $booking = $this->draftSabreBookingReadyForReviewSubmit();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['pnr_itinerary_sync'] = [
            'status' => 'synced',
            'synced_at' => now()->toIso8601String(),
        ];
        $booking->forceFill(['meta' => $meta, 'pnr' => 'EXIST1'])->save();

        $getBookingCalls = 0;
        Http::fake(function (Request $request, array $options) use (&$getBookingCalls) {
            $payload = $options['laravel_data'] ?? [];
            $isOAuthRequest = $this->sabreHttpUrlLooksLikeToken($request->url())
                || (is_array($payload) && array_key_exists('grant_type', $payload));

            if ($isOAuthRequest) {
                return Http::response(['access_token' => 'tok-test-stub', 'expires_in' => 3600], 200);
            }
            if (str_contains($request->url(), 'trip/orders/getBooking')) {
                $getBookingCalls++;

                return Http::response($this->cleanFlightsJson(), 200);
            }

            return Http::response([], 404);
        });

        app(SabreBookingService::class)->maybeAutoSyncPnrItineraryAfterPublicCheckout($booking->fresh());

        $this->assertSame(0, $getBookingCalls);
        $this->assertSame('synced', data_get($booking->refresh()->meta, 'pnr_itinerary_sync.status'));
    }

    /**
     * @param  array<string, mixed>  $createBookingResponse
     * @param  callable(): Response  $getBookingResponder
     */
    /**
     * @param  array<string, mixed>  $createBookingResponse
     * @param  callable(Request): Response  $getBookingResponder
     */
    private function stubSabreCheckoutHttp(array $createBookingResponse, callable $getBookingResponder): void
    {
        Http::fake(function (Request $request, array $options) use ($createBookingResponse, $getBookingResponder) {
            $payload = $options['laravel_data'] ?? [];
            $isOAuthRequest = $this->sabreHttpUrlLooksLikeToken($request->url())
                || (is_array($payload) && array_key_exists('grant_type', $payload));

            if ($isOAuthRequest) {
                return Http::response(['access_token' => 'tok-test-stub', 'expires_in' => 3600], 200);
            }
            if (str_contains($request->url(), 'trip/orders/createBooking')) {
                return Http::response($createBookingResponse, 201);
            }
            if (str_contains($request->url(), 'trip/orders/getBooking')) {
                return $getBookingResponder($request);
            }

            return Http::response([], 404);
        });
    }

    private function sabreHttpUrlLooksLikeToken(string $url): bool
    {
        return str_contains(strtolower($url), strtolower((string) config('suppliers.sabre.token_path', '/v2/auth/token')));
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    private function sabreEnrichOfferForGdsContext(array $offer): array
    {
        if (! is_array($offer['segments'] ?? null)) {
            return $offer;
        }

        $segments = [];
        foreach ($offer['segments'] as $segment) {
            if (! is_array($segment)) {
                $segments[] = $segment;

                continue;
            }
            $segments[] = array_merge([
                'fare_basis_code' => 'YLITE',
                'cabin' => 'economy',
                'brand_code' => 'ECON',
            ], $segment);
        }
        $offer['segments'] = $segments;

        if (! isset($offer['validating_carrier']) && is_array($segments[0] ?? null)) {
            $offer['validating_carrier'] = $segments[0]['carrier'] ?? $offer['airline_code'] ?? null;
        }

        return $offer;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function sabreGdsStrategyEligibleMeta(array $offer, array $meta = []): array
    {
        $segments = is_array($offer['segments'] ?? null) ? $offer['segments'] : [];
        $lastSegment = $segments[array_key_last($segments)] ?? [];
        $offer = array_merge([
            'origin' => (string) ($segments[0]['origin'] ?? 'LHE'),
            'destination' => (string) ($lastSegment['destination'] ?? 'DXB'),
            'depart_at' => (string) ($segments[0]['departure_at'] ?? now()->addDays(10)->toIso8601String()),
            'arrive_at' => (string) ($lastSegment['arrival_at'] ?? now()->addDays(10)->toIso8601String()),
            'currency' => (string) ($offer['currency'] ?? data_get($offer, 'fare_breakdown.currency', 'PKR')),
            'total' => (float) ($offer['total'] ?? data_get($offer, 'fare_breakdown.supplier_total', 0)),
            'validating_carrier' => (string) ($offer['validating_carrier'] ?? $offer['airline_code'] ?? $segments[0]['carrier'] ?? 'EK'),
        ], $offer);
        $bookingClasses = array_values(array_filter(array_map(
            fn (array $segment): ?string => $segment['booking_class'] ?? null,
            $segments,
        )));
        $fareBasisCodes = array_values(array_filter(array_map(
            fn (array $segment): ?string => $segment['fare_basis_code'] ?? null,
            $segments,
        )));
        $cabins = array_values(array_map(
            fn (array $segment): string => (string) ($segment['cabin'] ?? 'economy'),
            $segments,
        ));
        $brandCode = 'ECON';
        foreach ($segments as $segment) {
            $candidate = strtoupper(trim((string) ($segment['brand_code'] ?? '')));
            if ($candidate !== '') {
                $brandCode = $candidate;
                break;
            }
        }
        $category = count($segments) > 1 ? 'one_way_connecting' : 'one_way_direct';

        return array_merge([
            'flight_offer_snapshot' => $offer,
            'normalized_offer_snapshot' => $offer,
            'validated_offer_snapshot' => $offer,
            'revalidation_status' => 'success',
            'selected_offer_revalidation_status' => 'success',
            'last_revalidated_at' => now()->subMinutes(2)->toIso8601String(),
            'certified_route_selection' => [
                'category' => $category,
                'route_status' => 'certified',
                'endpoint_path' => '/v2.5.0/passenger/records?mode=create',
                'payload_style' => 'iati_like_cpnr_v2_4_gds',
            ],
            'selected_fare_family_option' => [
                'brand_code' => $brandCode,
                'booking_classes_by_segment' => $bookingClasses,
                'fare_basis_codes_by_segment' => $fareBasisCodes,
                'cabin_by_segment' => $cabins,
            ],
            'sabre_booking_context' => [
                'ready_for_booking_payload' => true,
                'validating_carrier' => (string) ($segments[0]['carrier'] ?? $offer['airline_code'] ?? 'EK'),
                'brand_code' => $brandCode,
                'selected_brand_code' => $brandCode,
                'fare_basis_codes_by_segment' => $fareBasisCodes,
                'booking_classes_by_segment' => $bookingClasses,
                'cabin_by_segment' => $cabins,
            ],
            'pricing_snapshot' => [
                'currency' => (string) ($offer['currency'] ?? 'PKR'),
                'final_total' => (float) ($offer['total'] ?? data_get($offer, 'fare_breakdown.supplier_total', 0)),
                'supplier_total' => (float) data_get($offer, 'fare_breakdown.supplier_total', $offer['total'] ?? 0),
            ],
        ], $meta);
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $searchCriteria
     * @return array<string, mixed>
     */
    private function sabreLiveBookingMeta(SupplierConnection $sabreConn, array $offer, array $searchCriteria): array
    {
        $enrichedOffer = $this->sabreEnrichOfferForGdsContext($offer);

        return array_merge([
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => $sabreConn->id,
            'requires_price_change_confirmation' => false,
            'protection_mode' => 'hold_price_guaranteed',
            'search_criteria' => $searchCriteria,
        ], $this->sabreGdsStrategyEligibleMeta($enrichedOffer, [
            'supplier_connection_id' => $sabreConn->id,
        ]));
    }

    private function draftSabreBookingReadyForReviewSubmit(): Booking
    {
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
            'id' => 'sabre-auto-sync-offer',
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
        $searchCriteria = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => $depart,
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => $this->sabreLiveBookingMeta($sabreConn, $offer, $searchCriteria),
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
            'passport_number' => 'AB9999999',
            'passport_issuing_country' => 'PK',
            'passport_expiry_date' => '2035-12-31',
            'nationality' => 'PK',
            'document_type' => 'passport',
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'auto-sync@example.com',
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

    /**
     * @return array<string, mixed>
     */
    private function cleanFlightsJson(): array
    {
        return [
            'bookingId' => 'auto-sync-booking-id',
            'isCancelable' => true,
            'isTicketed' => false,
            'flights' => [
                [
                    'fromAirportCode' => 'LHE',
                    'toAirportCode' => 'KHI',
                    'departureDate' => '2026-06-06',
                    'departureTime' => '11:00',
                    'arrivalDate' => '2026-06-06',
                    'arrivalTime' => '12:45',
                    'airlineCode' => 'PK',
                    'operatingAirlineCode' => 'PK',
                    'flightNumber' => '303',
                    'bookingClass' => 'V',
                    'flightStatusCode' => 'HK',
                ],
            ],
        ];
    }
}
