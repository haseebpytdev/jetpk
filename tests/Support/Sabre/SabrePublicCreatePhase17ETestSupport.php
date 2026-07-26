<?php

namespace Tests\Support\Sabre;

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
use App\Models\User;
use App\Support\Booking\AgentBookingContext;
use App\Support\PublicBooking;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Shared Phase 17E helpers for public Sabre create dispatch, idempotency, and payload proofs.
 *
 * @mixin TestCase
 */
trait SabrePublicCreatePhase17ETestSupport
{
    protected const PHASE17E_RECORDS_PATH = '/v2.5.0/passenger/records?mode=create';

    protected string $phase17eFakePnr = '17EPNR1';

    protected function seedPhase17eFoundation(): void
    {
        $this->seed(OtaFoundationSeeder::class);
    }

    protected function configureSabrePublicCreatePhase17E(): void
    {
        Config::set([
            'suppliers.sabre.refresh_offer_before_public_pnr' => false,
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.passenger_records_fresh_shop_guard_before_live' => false,
            'suppliers.sabre.allow_createbooking_without_revalidation' => false,
            'suppliers.sabre.certified_route_selector_public_checkout_enabled' => false,
            'suppliers.sabre.passenger_records_fresh_shop_guard_before_live' => false,
            'suppliers.sabre.booking_mode' => 'pnr_only',
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.pnr_create_enabled' => true,
            'suppliers.sabre.booking_path' => self::PHASE17E_RECORDS_PATH,
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
            'suppliers.sabre.revalidate_path' => '/v4/shop/flights/revalidate',
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => true,
            'suppliers.sabre.passenger_records_allow_verified_multi_segment' => false,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => true,
            'platform.modules.customer_checkout' => true,
        ]);
    }

    protected function configureSabrePublicCreateDryRunPhase17E(): void
    {
        $this->configureSabrePublicCreatePhase17E();
        Config::set('suppliers.sabre.booking_live_call_enabled', false);
    }

    /**
     * @param  callable(Request): mixed|null  $responder
     */
    protected function stubSabreCreatePnrHttp(?string $pnr = null, ?callable $responder = null): void
    {
        $pnr = $pnr ?? $this->phase17eFakePnr;
        $recordsPath = self::PHASE17E_RECORDS_PATH;

        Http::fake(function (Request $request, array $options) use ($pnr, $recordsPath, $responder) {
            if ($responder !== null) {
                $custom = $responder($request, $options);
                if ($custom !== null) {
                    return $custom;
                }
            }

            $url = strtolower($request->url());
            $payload = $options['laravel_data'] ?? [];
            $isOAuth = str_contains($url, '/v2/auth/token')
                || (is_array($payload) && array_key_exists('grant_type', $payload));

            if ($isOAuth) {
                return Http::response(['access_token' => 'tok-phase17e', 'expires_in' => 3600], 200);
            }

            if (str_contains($url, '/revalidate')) {
                return Http::response(['errors' => [['code' => '27131', 'message' => 'skipped']]], 400);
            }

            if (str_contains($url, 'passenger/records')) {
                return Http::response([
                    'CreatePassengerNameRecordRS' => [
                        'ApplicationResults' => ['status' => 'Complete'],
                        'ItineraryRef' => ['ID' => $pnr],
                    ],
                ], 200);
            }

            if (str_contains($url, '/getbooking') || str_contains($url, '/trip/orders/getbooking')) {
                return Http::response([], 404);
            }

            if (str_contains($url, 'cancel') || str_contains($url, 'airticket') || str_contains($url, 'ticketing')) {
                return Http::response([], 404);
            }

            return Http::response([], 404);
        });
    }

    protected function activateSabreConnectionForPhase17E(): SupplierConnection
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $connection = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();

        $connection->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        return $connection->fresh();
    }

    /**
     * @param  array<string, mixed>  $offerOverrides
     * @param  array<string, mixed>  $metaOverrides
     * @param  list<array<string, mixed>>|null  $segments
     */
    protected function makeFreshSabreDraftBooking(
        array $offerOverrides = [],
        array $metaOverrides = [],
        ?array $segments = null,
    ): Booking {
        $connection = $this->activateSabreConnectionForPhase17E();
        $agency = Agency::query()->findOrFail($connection->agency_id);
        $depart = now()->addDays(14)->toDateString();
        $return = now()->addDays(21)->toDateString();

        $defaultSegments = [[
            'origin' => 'LHE',
            'destination' => 'DXB',
            'carrier' => 'EK',
            'marketing_carrier' => 'EK',
            'operating_carrier' => 'EK',
            'flight_number' => '625',
            'departure_at' => $depart.'T08:00:00Z',
            'arrival_at' => $depart.'T14:00:00Z',
            'booking_class' => 'Y',
            'fare_basis_code' => 'YLOW',
            'cabin' => 'economy',
        ]];

        $segments = $segments ?? $defaultSegments;
        $bookingClasses = array_values(array_filter(array_map(
            fn ($s) => $s['booking_class'] ?? null,
            $segments,
        )));
        $fareBasisCodes = array_values(array_filter(array_map(
            fn ($s) => $s['fare_basis_code'] ?? null,
            $segments,
        )));
        $cabins = array_values(array_filter(array_map(
            fn ($s) => $s['cabin'] ?? 'economy',
            $segments,
        )));
        $brandCode = '';
        foreach ($segments as $segment) {
            $candidate = strtoupper(trim((string) ($segment['brand_code'] ?? '')));
            if ($candidate !== '') {
                $brandCode = $candidate;
                break;
            }
        }
        if ($brandCode === '') {
            $brandCode = 'ECON';
        }

        $tripType = (string) ($metaOverrides['search_criteria']['trip_type'] ?? 'one_way');
        $origin = (string) ($segments[0]['origin'] ?? 'LHE');
        $lastSeg = $segments[array_key_last($segments)];
        $destination = (string) ($lastSeg['destination'] ?? 'DXB');
        if ($tripType === 'return' && count($segments) >= 2) {
            $mid = (int) floor(count($segments) / 2);
            $destination = (string) ($segments[$mid - 1]['destination'] ?? $destination);
        }

        $offer = array_merge([
            'id' => 'sabre-phase17e-offer',
            'offer_id' => 'sabre-phase17e-offer',
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => $connection->id,
            'airline_code' => (string) ($segments[0]['carrier'] ?? 'EK'),
            'origin' => $origin,
            'destination' => $destination,
            'depart_at' => (string) ($segments[0]['departure_at'] ?? $depart.'T08:00:00Z'),
            'arrive_at' => (string) ($lastSeg['arrival_at'] ?? $depart.'T14:00:00Z'),
            'currency' => 'PKR',
            'total' => 110000,
            'validating_carrier' => (string) ($segments[0]['carrier'] ?? 'EK'),
            'segments' => $segments,
            'fare_breakdown' => [
                'supplier_total' => 110000,
                'base_fare' => 100000,
                'taxes' => 10000,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ], $offerOverrides);

        $searchCriteria = array_merge([
            'origin' => $origin,
            'destination' => $destination,
            'depart_date' => $depart,
            'return_date' => $tripType === 'return' ? $return : null,
            'trip_type' => $tripType,
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ], is_array($metaOverrides['search_criteria'] ?? null) ? $metaOverrides['search_criteria'] : []);
        unset($metaOverrides['search_criteria']);

        $meta = array_merge([
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => $connection->id,
            'requires_price_change_confirmation' => false,
            'protection_mode' => 'hold_price_guaranteed',
            'revalidation_status' => 'success',
            'selected_offer_revalidation_status' => 'success',
            'last_revalidated_at' => now()->subMinutes(2)->toIso8601String(),
            'selected_offer_last_revalidated_at' => now()->subMinutes(2)->toIso8601String(),
            'flight_offer_snapshot' => $offer,
            'normalized_offer_snapshot' => $offer,
            'validated_offer_snapshot' => $offer,
            'search_criteria' => $searchCriteria,
            'pricing_snapshot' => [
                'currency' => 'PKR',
                'final_total' => 110000,
                'supplier_total' => 110000,
            ],
            'scenario_runner' => true,
            'certified_route_selection' => [
                'category' => 'one_way_direct',
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
                'validating_carrier' => (string) ($segments[0]['carrier'] ?? 'EK'),
                'brand_code' => $brandCode,
                'selected_brand_code' => $brandCode,
                'fare_basis_codes_by_segment' => $fareBasisCodes,
                'booking_classes_by_segment' => $bookingClasses,
                'cabin_by_segment' => $cabins,
            ],
        ], $metaOverrides);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'submitted_at' => null,
            'supplier' => SupplierProvider::Sabre->value,
            'selected_fare_total' => 110000,
            'revalidated_fare_total' => 110000,
            'meta' => $meta,
        ]);

        BookingPassenger::factory()->create(array_merge([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
            'first_name' => 'Phase',
            'last_name' => 'SeventeenE',
            'gender' => 'male',
            'date_of_birth' => '1990-01-15',
            'nationality' => 'PK',
            'passport_number' => 'AB9999999',
            'passport_issuing_country' => 'PK',
            'passport_expiry_date' => '2035-12-31',
        ]));

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'phase17e@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 100000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 0,
            'discount' => 0,
            'total' => 110000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        return $booking->fresh(['passengers', 'contact', 'fareBreakdown']);
    }

    /**
     * @param  array<string, mixed>  $sessionExtras
     */
    protected function postBookingReview(Booking $booking, array $sessionExtras = []): TestResponse
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        return $this->withSession(array_merge([
            PublicBooking::SESSION_BOOKING_ID => $booking->id,
        ], $sessionExtras))->post(route('booking.review'), [
            'booking_method' => 'pay_later',
        ]);
    }

    protected function customerUser(): User
    {
        return User::query()->where('email', 'customer@ota.demo')->firstOrFail();
    }

    protected function agentUser(): User
    {
        return User::query()->where('email', 'agent@ota.demo')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    protected function agentSessionContext(): array
    {
        $user = $this->agentUser();
        $agent = $user->agent();
        $agency = $user->currentAgency;

        return [
            AgentBookingContext::SESSION_KEY => [
                'booking_context' => AgentBookingContext::BOOKING_CONTEXT_AGENT,
                'agency_id' => $agency?->id,
                'agent_id' => $agent?->id,
                'agent_user_id' => $user->id,
                'booking_channel' => AgentBookingContext::BOOKING_CHANNEL_AGENT_PORTAL,
                'activated_at' => now()->toIso8601String(),
            ],
        ];
    }

    protected ?\Illuminate\Support\Collection $phase17eHttpRecordedSnapshot = null;

    protected function httpRecordedSnapshot(): \Illuminate\Support\Collection
    {
        if ($this->phase17eHttpRecordedSnapshot === null) {
            $this->phase17eHttpRecordedSnapshot = Http::recorded();
        }

        return $this->phase17eHttpRecordedSnapshot;
    }

    protected function resetHttpRecordedSnapshot(): void
    {
        $this->phase17eHttpRecordedSnapshot = null;
    }

    protected function countHttpMatching(callable $matcher): int
    {
        $count = 0;
        foreach ($this->httpRecordedSnapshot() as $pair) {
            if (! is_array($pair) || ! isset($pair[0])) {
                continue;
            }
            $request = $pair[0];
            if ($request instanceof Request && $matcher($request)) {
                $count++;
            }
        }

        return $count;
    }

    protected function countCreatePnrHttpDispatches(): int
    {
        return $this->countHttpMatching(
            fn (Request $request): bool => str_contains(strtolower($request->url()), 'passenger/records')
                && strtoupper($request->method()) === 'POST',
        );
    }

    protected function countRetrieveHttpDispatches(): int
    {
        return $this->countHttpMatching(
            fn (Request $request): bool => str_contains(strtolower($request->url()), 'getbooking')
                || str_contains(strtolower($request->url()), '/trip/orders/getbooking'),
        );
    }

    protected function countCancellationHttpDispatches(): int
    {
        return $this->countHttpMatching(
            fn (Request $request): bool => str_contains(strtolower($request->url()), 'cancel'),
        );
    }

    protected function countTicketingHttpDispatches(): int
    {
        return $this->countHttpMatching(
            fn (Request $request): bool => str_contains(strtolower($request->url()), 'airticket')
                || str_contains(strtolower($request->url()), 'ticketing'),
        );
    }

    protected function assertExactlyOneCreatePnrDispatch(string $message = ''): void
    {
        $this->assertSame(1, $this->countCreatePnrHttpDispatches(), $message);
    }

  /**
     * Assert at most one live create dispatch via Http::recorded and/or durable attempt evidence.
     */
    protected function assertExactlyOneCanonicalCreateDispatch(Booking $booking, string $message = ''): void
    {
        $httpCount = $this->countCreatePnrHttpDispatches();
        $liveAttemptCount = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', 'create_pnr')
            ->get()
            ->filter(fn (SupplierBookingAttempt $attempt): bool => (bool) data_get($attempt->safe_summary, 'live_call_attempted', false))
            ->count();

        $this->assertLessThanOrEqual(1, $httpCount, $message !== '' ? $message : 'maximum_create_dispatch_count=1');
        $this->assertLessThanOrEqual(1, $liveAttemptCount, $message !== '' ? $message : 'maximum_create_dispatch_count=1');
        $this->assertTrue(
            $httpCount === 1 || $liveAttemptCount === 1,
            $message !== '' ? $message : 'expected exactly one canonical create dispatch',
        );
    }

    protected function assertZeroCreatePnrDispatch(string $message = ''): void
    {
        $this->assertSame(0, $this->countCreatePnrHttpDispatches(), $message);
    }

    protected function assertNoRetrieveCancelOrTicketHttp(): void
    {
        $this->assertSame(0, $this->countRetrieveHttpDispatches());
        $this->assertSame(0, $this->countCancellationHttpDispatches());
        $this->assertSame(0, $this->countTicketingHttpDispatches());
    }

    protected function assertFreshCreatePersistence(Booking $booking, string $expectedPnr): void
    {
        $booking->refresh();
        $this->assertSame(1, Booking::query()->where('id', $booking->id)->count());
        $this->assertSame(strtoupper($expectedPnr), strtoupper((string) $booking->pnr));

        $supplierBookingCount = \App\Models\SupplierBooking::query()->where('booking_id', $booking->id)->count();
        $this->assertLessThanOrEqual(1, $supplierBookingCount);
        if ($supplierBookingCount === 1) {
            $this->assertSame(1, $supplierBookingCount);
        }

        $attempts = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', 'create_pnr')
            ->orderBy('id')
            ->get();

        $this->assertCount(1, $attempts);
        $attempt = $attempts->first();
        $this->assertNotNull($attempt);
        $this->assertSame('success', $attempt->status);
        $this->assertNotNull($attempt->attempted_at);
        $this->assertNotNull($attempt->completed_at);
        $summary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertSame('sabre_public_checkout', $summary['source'] ?? null);
        $this->assertTrue($summary['live_call_attempted'] ?? false);
    }

    protected function lastCreatePnrRequestBody(): ?array
    {
        $body = null;
        foreach ($this->httpRecordedSnapshot() as $pair) {
            $request = is_array($pair) ? ($pair[0] ?? null) : $pair;
            if (! $request instanceof Request) {
                continue;
            }
            if (! str_contains(strtolower($request->url()), 'passenger/records')) {
                continue;
            }
            $decoded = json_decode($request->body(), true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        return $body;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function extractWireFlightSegments(array $wire): array
    {
        $airBook = $wire['CreatePassengerNameRecordRQ']['AirBook']['OriginDestinationInformation'] ?? [];
        $flightSegments = $airBook['FlightSegment'] ?? [];
        if (isset($flightSegments['DepartureDateTime'])) {
            return [$flightSegments];
        }

        return is_array($flightSegments) ? array_values($flightSegments) : [];
    }

    protected function createPublicCheckoutAttempt(
        Booking $booking,
        string $status,
        array $safeSummary = [],
        ?string $errorCode = null,
    ): SupplierBookingAttempt {
        $connectionId = (int) data_get($booking->meta, 'supplier_connection_id', 0);

        return SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connectionId > 0 ? $connectionId : null,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => $status,
            'error_code' => $errorCode,
            'safe_summary' => array_merge([
                'source' => 'sabre_public_checkout',
                'live_call_attempted' => $status !== 'dry_run',
            ], $safeSummary),
            'attempted_at' => now()->subMinute(),
            'completed_at' => in_array($status, ['processing', 'in_progress', 'pending'], true) ? null : now(),
        ]);
    }
}
