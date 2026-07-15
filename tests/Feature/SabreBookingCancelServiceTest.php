<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Bookings\BookingCancellationService;
use App\Services\Suppliers\Sabre\SabreBookingCancelService;
use App\Services\Suppliers\Sabre\SabreBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreBookingCancelServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Config::set('suppliers.sabre.cancel_enabled', false);
        Config::set('suppliers.sabre.cancel_live_call_enabled', false);
        Config::set('suppliers.sabre.admin_cancel_live_call_enabled', false);
        Config::set('suppliers.sabre.cancel_allow_production_send', false);
        Config::set('suppliers.sabre.cancel_allow_production_host', false);

        parent::tearDown();
    }

    public function test_unticketed_cancel_success_verified(): void
    {
        $conn = $this->certConnection();
        $booking = $this->sabreBooking($conn);

        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            $conn->base_url.'/v1/trip/orders/getBooking' => Http::sequence()
                ->push([
                    'bookingId' => 'TRIP-BK-1',
                    'isCancelable' => true,
                    'isTicketed' => false,
                    'flights' => [['id' => 'SEG-1']],
                ], 200)
                ->push([
                    'isCancelable' => false,
                    'isTicketed' => false,
                ], 200),
            $conn->base_url.'/v1/trip/orders/cancelBooking' => Http::response(['status' => 'Cancelled'], 200),
        ]);
        Cache::flush();
        $this->enableLiveCancel();

        $result = app(SabreBookingCancelService::class)->cancelForBooking($booking);

        $this->assertTrue($result['success'] ?? false);
        $this->assertSame(SabreBookingCancelService::CATEGORY_CANCEL_VERIFIED, $result['safe_summary_category'] ?? null);
        $this->assertTrue($result['supplier_cancel_verified'] ?? false);
        $this->assertTrue($result['live_call_attempted'] ?? false);

        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', 'cancel_booking')
            ->first();
        $this->assertNotNull($attempt);
        $summary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertSame(SabreBookingCancelService::CATEGORY_CANCEL_VERIFIED, $summary['safe_summary_category'] ?? null);

        $json = json_encode($result);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('TRIP-BK-1', $json);
        $this->assertStringNotContainsString('fake-token-for-tests-only', $json);
    }

    public function test_ticketed_cancel_blocked(): void
    {
        $conn = $this->certConnection();
        $booking = $this->sabreBooking($conn);

        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            $conn->base_url.'/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BK-2',
                'isCancelable' => true,
                'isTicketed' => true,
            ], 200),
        ]);
        Cache::flush();
        $this->enableLiveCancel();

        $result = app(SabreBookingCancelService::class)->cancelForBooking($booking);

        $this->assertFalse($result['success'] ?? true);
        $this->assertSame(SabreBookingCancelService::CATEGORY_TICKETED_REFUND_REQUIRED, $result['safe_summary_category'] ?? null);
        $this->assertFalse($result['live_call_attempted'] ?? true);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/cancelBooking'));
    }

    public function test_unknown_is_cancelable_blocks_cancel_http(): void
    {
        $conn = $this->certConnection();
        $booking = $this->sabreBooking($conn);

        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            $conn->base_url.'/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BK-UNKNOWN',
                'isTicketed' => false,
                'flights' => [['id' => 'SEG-UNKNOWN']],
            ], 200),
        ]);
        Cache::flush();
        $this->enableLiveCancel();

        $result = app(SabreBookingCancelService::class)->cancelForBooking($booking);

        $this->assertFalse($result['success'] ?? true);
        $this->assertSame('cancelable_unknown', $result['status'] ?? null);
        $this->assertSame(SabreBookingCancelService::CATEGORY_CANCEL_NOT_ELIGIBLE, $result['safe_summary_category'] ?? null);
        $this->assertFalse($result['live_call_attempted'] ?? true);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/cancelBooking'));

        $json = json_encode($result);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('TRIP-BK-UNKNOWN', $json);
        $this->assertStringNotContainsString('fake-token-for-tests-only', $json);
    }

    public function test_ticket_numbers_present_blocks_cancel_http(): void
    {
        $conn = $this->certConnection();
        $booking = $this->sabreBooking($conn);

        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            $conn->base_url.'/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BK-TIX',
                'isCancelable' => true,
                'isTicketed' => false,
                'ticketNumbers' => ['0012345678901'],
            ], 200),
        ]);
        Cache::flush();
        $this->enableLiveCancel();

        $result = app(SabreBookingCancelService::class)->cancelForBooking($booking);

        $this->assertFalse($result['success'] ?? true);
        $this->assertSame(SabreBookingCancelService::CATEGORY_TICKETED_REFUND_REQUIRED, $result['safe_summary_category'] ?? null);
        $this->assertFalse($result['live_call_attempted'] ?? true);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/cancelBooking'));

        $json = json_encode($result);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('0012345678901', $json);
        $this->assertStringNotContainsString('TRIP-BK-TIX', $json);
    }

    public function test_not_cancelable_blocked(): void
    {
        $conn = $this->certConnection();
        $booking = $this->sabreBooking($conn);

        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            $conn->base_url.'/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BK-3',
                'isCancelable' => false,
                'isTicketed' => false,
            ], 200),
        ]);
        Cache::flush();
        $this->enableLiveCancel();

        $result = app(SabreBookingCancelService::class)->cancelForBooking($booking);

        $this->assertFalse($result['success'] ?? true);
        $this->assertSame(SabreBookingCancelService::CATEGORY_CANCEL_NOT_ELIGIBLE, $result['safe_summary_category'] ?? null);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/cancelBooking'));
    }

    public function test_cancel_http_success_but_post_retrieve_still_active(): void
    {
        $conn = $this->certConnection();
        $booking = $this->sabreBooking($conn);

        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            $conn->base_url.'/v1/trip/orders/getBooking' => Http::sequence()
                ->push([
                    'bookingId' => 'TRIP-BK-4',
                    'isCancelable' => true,
                    'isTicketed' => false,
                    'flights' => [['id' => 'SEG-4']],
                ], 200)
                ->push([
                    'bookingId' => 'TRIP-BK-4',
                    'isCancelable' => true,
                    'isTicketed' => false,
                    'flights' => [['id' => 'SEG-4']],
                ], 200),
            $conn->base_url.'/v1/trip/orders/cancelBooking' => Http::response(['status' => 'Cancelled'], 200),
        ]);
        Cache::flush();
        $this->enableLiveCancel();

        $result = app(SabreBookingCancelService::class)->cancelForBooking($booking);

        $this->assertFalse($result['success'] ?? true);
        $this->assertSame(SabreBookingCancelService::CATEGORY_CANCEL_NOT_VERIFIED, $result['safe_summary_category'] ?? null);
        $this->assertTrue($result['live_call_attempted'] ?? false);
    }

    public function test_missing_pnr_blocked(): void
    {
        Http::fake();
        $conn = $this->certConnection();
        $booking = Booking::factory()->create([
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => null,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
            ],
        ]);
        $this->enableLiveCancel();

        $result = app(SabreBookingCancelService::class)->cancelForBooking($booking);

        $this->assertFalse($result['success'] ?? true);
        $this->assertSame(SabreBookingCancelService::CATEGORY_CANCEL_PAYLOAD_MISSING, $result['safe_summary_category'] ?? null);
        Http::assertNothingSent();
    }

    public function test_live_flag_disabled_returns_dry_run_category(): void
    {
        Http::fake();
        $conn = $this->certConnection();
        $booking = $this->sabreBooking($conn);
        Config::set('suppliers.sabre.cancel_enabled', false);
        Config::set('suppliers.sabre.cancel_live_call_enabled', false);

        $result = app(SabreBookingCancelService::class)->cancelForBooking($booking);

        $this->assertFalse($result['success'] ?? true);
        $this->assertSame(SabreBookingCancelService::CATEGORY_LIVE_CANCEL_DISABLED, $result['safe_summary_category'] ?? null);
        Http::assertNothingSent();
    }

    public function test_wrong_connection_not_used_when_meta_points_to_booking_connection(): void
    {
        $connA = $this->certConnection('https://api-crt.cert.havail.sabre.test');
        $connB = $this->certConnection('https://other-cert.sabre.test');
        $booking = $this->sabreBooking($connA);

        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api-crt.cert.havail.sabre.test/v1/trip/orders/getBooking' => Http::sequence()
                ->push(['bookingId' => 'BK-A', 'isCancelable' => true, 'isTicketed' => false, 'flights' => [['id' => 'SEG-A']]], 200)
                ->push(['isCancelable' => false, 'isTicketed' => false], 200),
            'https://api-crt.cert.havail.sabre.test/v1/trip/orders/cancelBooking' => Http::response(['status' => 'Cancelled'], 200),
        ]);
        Cache::flush();
        $this->enableLiveCancel();

        app(SabreBookingCancelService::class)->cancelForBooking($booking);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api-crt.cert.havail.sabre.test'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'other-cert.sabre.test'));
        $this->assertNotSame($connB->id, $connA->id);
    }

    public function test_workflow_process_blocks_when_admin_web_cancel_gate_disabled(): void
    {
        Http::fake();
        Config::set('suppliers.sabre.cancel_enabled', false);
        Config::set('suppliers.sabre.cancel_live_call_enabled', false);
        Config::set('suppliers.sabre.admin_cancel_live_call_enabled', false);

        $conn = $this->certConnection();
        $booking = $this->sabreBooking($conn, BookingStatus::Confirmed);
        $admin = User::factory()->create();
        $request = BookingCancellationRequest::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'requested_by' => $admin->id,
            'request_source' => 'admin',
            'status' => 'approved',
            'cancellation_type' => 'booking_cancel',
        ]);

        app(BookingCancellationService::class)->processCancellation($request, $admin, true);

        $booking->refresh();
        $request->refresh();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame('approved', $request->status->value);
        $this->assertSame('admin_staff_live_gate_disabled', $booking->meta['sabre_cancel_outcome']['sabre_cancel_execution_blocked_reason'] ?? null);
        Http::assertNothingSent();
    }

    public function test_cancel_booking_resolves_by_pnr(): void
    {
        Http::fake();
        $conn = $this->certConnection();
        $booking = $this->sabreBooking($conn);
        Config::set('suppliers.sabre.cancel_enabled', false);

        $result = app(SabreBookingService::class)->cancelBooking((string) $booking->pnr);

        $this->assertSame($booking->id, $result['booking_id'] ?? null);
        $this->assertSame(SabreBookingCancelService::CATEGORY_LIVE_CANCEL_DISABLED, $result['safe_summary_category'] ?? null);
    }

    protected function enableLiveCancel(): void
    {
        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);
    }

    protected function certConnection(?string $baseUrl = null): SupplierConnection
    {
        return SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => $baseUrl ?? 'https://api-crt.cert.havail.sabre.test',
        ]);
    }

    protected function sabreBooking(SupplierConnection $conn, BookingStatus $status = BookingStatus::Confirmed): Booking
    {
        return Booking::factory()->create([
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => 'IJYJMV',
            'status' => $status,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
            ],
        ]);
    }
}
