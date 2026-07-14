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
use App\Models\User;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Support\PublicBooking;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase B21 — controlled Trip Orders createBooking without successful revalidation (opt-in only).
 */
class SabreBookingPhaseB21Test extends TestCase
{
    use RefreshDatabase;

    public function test_default_revalidation_failure_does_not_call_create_booking(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $sabreBase = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $bookingPath = (string) config('suppliers.sabre.booking_path', '/v1/trip/orders/createBooking');
        $bookingPath = $bookingPath !== '' && $bookingPath[0] === '/' ? $bookingPath : '/'.$bookingPath;
        $revalidatePath = '/v4/shop/flights/revalidate';

        Http::fake([
            $sabreBase.$tokenPath => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            $sabreBase.$revalidatePath => Http::response(['message' => 'revalidation failed'], 422),
            $sabreBase.$bookingPath => Http::response(['recordLocator' => 'SHOULD_NOT_BE_CALLED'], 200),
        ]);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => null,
            'suppliers.sabre.revalidate_before_booking' => true,
            'suppliers.sabre.allow_createbooking_without_revalidation' => false,
            'suppliers.sabre.revalidate_path' => $revalidatePath,
        ]);

        $booking = $this->seedLiveSabreBooking('b21-default@example.com');
        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later']);

        Http::assertNotSent(fn ($request) => $request instanceof Request && str_contains($request->url(), $bookingPath));
    }

    public function test_allow_bypass_calls_create_booking_after_revalidation_failure(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $sabreBase = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $bookingPath = '/v1/trip/orders/createBooking';
        $revalidatePath = '/v4/shop/flights/revalidate';

        Http::fake([
            $sabreBase.$tokenPath => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            $sabreBase.$revalidatePath => Http::response(['message' => 'revalidation failed'], 422),
            $sabreBase.$bookingPath => Http::response(['order' => ['id' => 'ORD-1'], 'pnr' => 'ABC123'], 200),
        ]);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => $bookingPath,
            'suppliers.sabre.booking_schema' => null,
            'suppliers.sabre.revalidate_before_booking' => true,
            'suppliers.sabre.allow_createbooking_without_revalidation' => true,
            'suppliers.sabre.revalidate_path' => $revalidatePath,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $booking = $this->seedLiveSabreBooking('b21-bypass@example.com');
        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later']);

        Http::assertSent(fn ($request) => $request instanceof Request && str_contains($request->url(), $bookingPath));
        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $this->assertNotNull($attempt);
        $summary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertTrue((bool) ($summary['revalidation_skipped_by_config'] ?? false));
        $this->assertTrue((bool) ($summary['revalidation_bypass_enabled'] ?? false));
        $this->assertFalse((bool) ($summary['ticketing_enabled'] ?? true));
        $this->assertArrayHasKey('has_fare_basis', $summary);
        $this->assertArrayHasKey('has_booking_class', $summary);
        $this->assertArrayHasKey('has_validating_carrier', $summary);
    }

    public function test_revalidate_disabled_skips_revalidate_http_and_sets_audit_flags(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $sabreBase = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $bookingPath = '/v1/trip/orders/createBooking';
        $revalidatePath = '/v4/shop/flights/revalidate';

        Http::fake([
            $sabreBase.$tokenPath => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            $sabreBase.$bookingPath => Http::response(['pnr' => 'SKIPPED'], 200),
        ]);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => $bookingPath,
            'suppliers.sabre.booking_schema' => null,
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.allow_createbooking_without_revalidation' => false,
            'suppliers.sabre.revalidate_path' => $revalidatePath,
        ]);

        $booking = $this->seedLiveSabreBooking('b21-norev@example.com');
        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later']);

        Http::assertNotSent(fn ($request) => $request instanceof Request && str_contains($request->url(), $revalidatePath));
        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $summary = is_array($attempt?->safe_summary) ? $attempt->safe_summary : [];
        $this->assertTrue((bool) ($summary['revalidation_skipped_by_config'] ?? false));
    }

    public function test_http_2xx_with_order_id_only_needs_review(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $sabreBase = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $bookingPath = '/v1/trip/orders/createBooking';

        Http::fake([
            $sabreBase.$tokenPath => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            $sabreBase.$bookingPath => Http::response(['orderId' => 'ORD-ONLY', 'booking' => ['id' => 'B1']], 200),
        ]);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => $bookingPath,
            'suppliers.sabre.revalidate_before_booking' => false,
        ]);

        $booking = $this->seedLiveSabreBooking('b21-order@example.com');
        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later']);

        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $this->assertSame('needs_review', $attempt->status);
    }

    public function test_http_400_maps_validation_failed(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $sabreBase = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $bookingPath = '/v1/trip/orders/createBooking';

        Http::fake([
            $sabreBase.$tokenPath => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            $sabreBase.$bookingPath => Http::response(['message' => 'bad'], 400),
        ]);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => $bookingPath,
            'suppliers.sabre.revalidate_before_booking' => false,
        ]);

        $booking = $this->seedLiveSabreBooking('b21-400@example.com');
        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later']);

        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $this->assertSame('sabre_booking_validation_failed', $attempt->error_code);
    }

    public function test_inspect_booking_config_can_attempt_flags(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->seedLiveSabreBooking('b21-inspect@example.com');

        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.revalidate_before_booking' => true,
            'suppliers.sabre.allow_createbooking_without_revalidation' => false,
        ]);
        Artisan::call('sabre:inspect-booking-config', ['--booking' => (string) $booking->id]);
        $this->assertStringContainsString('can_attempt_createbooking_now=false', Artisan::output());

        config(['suppliers.sabre.allow_createbooking_without_revalidation' => true]);
        Artisan::call('sabre:inspect-booking-config', ['--booking' => (string) $booking->id]);
        $this->assertStringContainsString('can_attempt_createbooking_now=true', Artisan::output());

        config([
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.allow_createbooking_without_revalidation' => false,
        ]);
        Artisan::call('sabre:inspect-booking-config', ['--booking' => (string) $booking->id]);
        $this->assertStringContainsString('can_attempt_createbooking_now=true', Artisan::output());
    }

    public function test_safe_summary_excludes_passenger_contact_email(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $sabreBase = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $bookingPath = '/v1/trip/orders/createBooking';

        Http::fake([
            $sabreBase.$tokenPath => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            $sabreBase.$bookingPath => Http::response(['pnr' => 'ZZ99ZZ'], 200),
        ]);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => $bookingPath,
            'suppliers.sabre.revalidate_before_booking' => false,
        ]);

        $booking = $this->seedLiveSabreBooking('b21-log-leak-test@example.com');
        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later']);

        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $json = json_encode($attempt?->safe_summary ?? []);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('b21-log-leak-test@example.com', $json);
    }

    public function test_issue_ticket_does_not_perform_http_when_ticketing_disabled(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        config(['suppliers.sabre.ticketing_enabled' => false]);
        Http::fake();
        $booking = Booking::factory()->create(['supplier' => SupplierProvider::Sabre->value]);
        $user = User::factory()->create();
        $svc = app(SabreBookingService::class);
        $out = $svc->issueTicket($booking, $user);
        $this->assertFalse($out['success'] ?? true);
        Http::assertNothingSent();
    }

    protected function seedLiveSabreBooking(string $email): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-b21-'.uniqid(),
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'validating_carrier' => 'EK',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T14:00:00Z',
            'total' => 100000,
            'currency' => 'PKR',
            'adults' => 1,
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
                'supplier_total' => 500.0,
                'currency' => 'USD',
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

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ]);

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
}
