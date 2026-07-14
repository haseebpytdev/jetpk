<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Cancel\SabreBookingCancelService;
use App\Services\Suppliers\Sabre\Cancel\SabreGdsCancelReadiness;
use App\Services\Suppliers\Sabre\Cancel\SabreGdsCancelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreGdsCancelServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Config::set('suppliers.sabre.cancel_enabled', false);
        Config::set('suppliers.sabre.cancel_live_call_enabled', false);
        Config::set('suppliers.sabre.admin_cancel_live_call_enabled', false);

        parent::tearDown();
    }

    public function test_duplicate_cancelled_booking_does_not_call_cancel_booking_http(): void
    {
        Http::fake();
        $booking = $this->booking(BookingStatus::Cancelled, [
            SabreGdsCancelReadiness::META_KEY => ['status' => 'cancelled'],
        ]);

        $result = app(SabreGdsCancelService::class)->cancelForBooking($booking, true, [
            'admin_live_cancel_approved' => true,
            'actor_context' => 'admin',
        ]);

        $this->assertTrue($result['success'] ?? false);
        $this->assertSame('already_cancelled', $result['status'] ?? null);
        Http::assertNothingSent();
    }

    public function test_in_progress_booking_blocks_second_cancel_attempt(): void
    {
        Http::fake();
        $booking = $this->booking(BookingStatus::Confirmed, [
            SabreGdsCancelReadiness::META_KEY => ['status' => 'in_progress'],
        ]);

        $result = app(SabreGdsCancelService::class)->cancelForBooking($booking, true, [
            'admin_live_cancel_approved' => true,
            'actor_context' => 'admin',
        ]);

        $this->assertFalse($result['success'] ?? true);
        $this->assertSame('cancellation_in_progress', $result['sabre_cancel_execution_blocked_reason'] ?? null);
        Http::assertNothingSent();
    }

    public function test_ticketed_booking_blocks_cancel_booking_http(): void
    {
        Http::fake();
        $booking = $this->booking(BookingStatus::Ticketed, [
            'pnr_itinerary_sync' => ['is_ticketed' => true, 'ticket_numbers_present' => true],
        ]);
        $booking->forceFill(['ticketing_status' => 'ticketed'])->save();

        $result = app(SabreGdsCancelService::class)->cancelForBooking($booking, true, [
            'admin_live_cancel_approved' => true,
            'actor_context' => 'admin',
        ]);

        $this->assertSame(SabreBookingCancelService::CATEGORY_TICKETED_REFUND_REQUIRED, $result['safe_summary_category'] ?? null);
        $this->assertSame('ticketed_manual_review', $result['status'] ?? null);
        Http::assertNothingSent();
    }

    public function test_verified_cancel_persists_meta_and_audit_using_http_fake(): void
    {
        $conn = $this->certConnection();
        $booking = $this->booking(BookingStatus::Confirmed, [
            'supplier_connection_id' => $conn->id,
        ]);

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
                ], 200)
                ->push([
                    'isCancelable' => false,
                    'isTicketed' => false,
                ], 200),
            $conn->base_url.'/v1/trip/orders/cancelBooking' => Http::response(['status' => 'Cancelled'], 200),
        ]);
        Cache::flush();
        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);
        Config::set('suppliers.sabre.admin_cancel_live_call_enabled', true);

        $result = app(SabreGdsCancelService::class)->cancelForBooking($booking, true, [
            'admin_live_cancel_approved' => true,
            'actor_context' => 'admin',
            'bypass_global_cancel_flags_for_admin' => true,
        ]);

        $this->assertTrue($result['success'] ?? false);
        $booking->refresh();
        $cancelMeta = $booking->meta[SabreGdsCancelReadiness::META_KEY] ?? [];
        $this->assertSame('cancelled', $cancelMeta['status'] ?? null);
        $this->assertContains('HX', $cancelMeta['airline_segment_statuses'] ?? []);

        $this->assertTrue(
            AuditLog::query()
                ->where('auditable_id', $booking->id)
                ->where('action', 'booking.sabre_gds_cancel_confirmed')
                ->exists()
        );

        $this->assertFalse(
            SupplierBookingAttempt::query()
                ->where('booking_id', $booking->id)
                ->where('action', 'cancel_booking')
                ->whereIn('status', ['pending', 'processing', 'in_progress'])
                ->exists()
        );
    }

    protected function certConnection(): SupplierConnection
    {
        return SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://api-crt.cert.havail.sabre.test',
        ]);
    }

    /**
     * @param  array<string, mixed>  $metaExtra
     */
    protected function booking(BookingStatus $status, array $metaExtra = []): Booking
    {
        $conn = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'base_url' => 'https://api-crt.cert.havail.sabre.test',
        ]);

        return Booking::factory()->create([
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => 'IJYJMV',
            'status' => $status,
            'meta' => array_merge([
                'supplier_provider' => SupplierProvider::Sabre->value,
                'distribution_channel' => 'gds',
                'supplier_connection_id' => $conn->id,
            ], $metaExtra),
        ]);
    }
}
