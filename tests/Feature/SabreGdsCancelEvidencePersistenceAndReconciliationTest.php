<?php

namespace Tests\Feature;

use App\Enums\BookingCommunicationEvent;
use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\CommunicationLog;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Cancel\SabreBookingCancelService;
use App\Services\Suppliers\Sabre\Cancel\SabreCancelBookingInspectProbe;
use App\Services\Suppliers\Sabre\Cancel\SabreGdsCancellationReconciliationService;
use App\Services\Suppliers\Sabre\Cancel\SabreGdsControlledCancelEvidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreGdsCancelEvidencePersistenceAndReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_future_inspect_cancel_persists_classification_and_post_cancel_evidence(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api-crt.cert.havail.sabre.test/v1/trip/orders/cancelBooking' => Http::response([
                'status' => 'Cancelled',
                'errors' => [],
            ], 200),
            'https://api-crt.cert.havail.sabre.test/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'isCancelable' => true,
                'isTicketed' => false,
                'travelers' => [['id' => 'T1']],
                'contactInfo' => ['present' => true],
                'fares' => [['id' => 'F1']],
                'remarks' => [['id' => 'R1']],
            ], 200),
        ]);
        Cache::flush();

        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $booking = $this->seedSabreBooking([
            'supplier_api_booking_id' => 'ORDER-999',
        ], 'https://api-crt.cert.havail.sabre.test');

        app(SabreCancelBookingInspectProbe::class)->inspect(
            $booking,
            true,
            SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            false,
            null,
            false,
        );

        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', 'inspect_cancel_pnr')
            ->first();
        $this->assertNotNull($attempt);
        $summary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertSame(
            SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED,
            $summary['cancel_outcome_classification'] ?? null,
        );
        $this->assertSame(
            SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED,
            $summary['classification'] ?? null,
        );
        $this->assertTrue($summary['live_call_attempted'] ?? false);
        $this->assertSame('200', $summary['http_status'] ?? null);
        $this->assertTrue($summary['cancel_http_success'] ?? false);
        $this->assertSame(0, $summary['post_cancel_segment_count'] ?? null);
        $this->assertFalse($summary['post_cancel_air_segments_present'] ?? true);
        $this->assertTrue($summary['cancel_air_segments_removed'] ?? false);
        $this->assertFalse($summary['post_cancel_ticket_numbers_present'] ?? true);
        $this->assertNotEmpty($summary['verification_timestamp'] ?? null);
        $this->assertNull($attempt->request_payload);
        $this->assertNull($attempt->response_payload);
    }

    public function test_legacy_evidence_command_rejects_invalid_classification(): void
    {
        Http::fake();
        $booking = $this->seedSabreBooking();

        $exit = Artisan::call('sabre:gds-record-cancel-evidence', [
            '--booking' => (string) $booking->id,
            '--classification' => 'HTTP_200_BUT_STILL_ACTIVE',
            '--confirm' => SabreGdsControlledCancelEvidenceService::CONFIRM_PHRASE,
        ]);

        $this->assertSame(1, $exit);
        $output = Artisan::output();
        $this->assertStringContainsString('invalid_classification', $output);
        Http::assertNothingSent();
    }

    public function test_legacy_evidence_command_rejects_ticketed_booking(): void
    {
        Http::fake();
        $booking = $this->seedSabreBooking([
            'status' => BookingStatus::Ticketed,
            'ticketed_at' => now(),
            'ticketing_status' => 'ticketed',
        ]);

        $exit = Artisan::call('sabre:gds-record-cancel-evidence', [
            '--booking' => (string) $booking->id,
            '--classification' => SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED,
            '--confirm' => SabreGdsControlledCancelEvidenceService::CONFIRM_PHRASE,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('ticketed_booking', Artisan::output());
        Http::assertNothingSent();
    }

    public function test_legacy_evidence_command_is_idempotent(): void
    {
        Http::fake();
        $booking = $this->seedSabreBooking();
        $service = app(SabreGdsControlledCancelEvidenceService::class);

        $first = $service->recordEvidence(
            $booking,
            SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED,
            false,
        );
        $auditCount = AuditLog::query()
            ->where('auditable_id', $booking->id)
            ->where('action', SabreGdsControlledCancelEvidenceService::AUDIT_ACTION)
            ->count();

        $second = $service->recordEvidence(
            $booking->fresh(),
            SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED,
            false,
        );

        $this->assertTrue($first['success'] ?? false);
        $this->assertTrue($second['already_recorded'] ?? false);
        $this->assertSame($auditCount, AuditLog::query()
            ->where('auditable_id', $booking->id)
            ->where('action', SabreGdsControlledCancelEvidenceService::AUDIT_ACTION)
            ->count());
        Http::assertNothingSent();
    }

    public function test_reconciliation_accepts_controlled_legacy_evidence(): void
    {
        Http::fake();
        $booking = $this->seedSabreBookingWithLegacyInspectAttemptOnly();

        app(SabreGdsControlledCancelEvidenceService::class)->recordEvidence(
            $booking,
            SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED,
            false,
        );

        $result = app(SabreGdsCancellationReconciliationService::class)->reconcileFromStoredEvidence($booking->fresh(), [
            'source' => 'test',
        ]);

        $this->assertTrue($result['success'] ?? false);
        $booking->refresh();
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertSame('cancelled', $booking->supplier_booking_status);
        $this->assertSame('FEZJFP', $booking->pnr);
        Http::assertNothingSent();
    }

    public function test_reconciliation_rejects_live_call_attempted_without_confirmed_evidence(): void
    {
        Http::fake();
        $booking = $this->seedSabreBookingWithLegacyInspectAttemptOnly();

        $result = app(SabreGdsCancellationReconciliationService::class)->reconcileFromStoredEvidence($booking, [
            'source' => 'test',
        ]);

        $this->assertFalse($result['success'] ?? true);
        $this->assertSame('no_confirmed_cancel_evidence', $result['reason_code'] ?? null);
        Http::assertNothingSent();
    }

    public function test_duplicate_evidence_and_reconciliation_create_no_duplicate_rows(): void
    {
        Http::fake();
        $booking = $this->seedSabreBookingWithLegacyInspectAttemptOnly();
        $evidenceService = app(SabreGdsControlledCancelEvidenceService::class);
        $reconcileService = app(SabreGdsCancellationReconciliationService::class);

        $evidenceService->recordEvidence(
            $booking,
            SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED,
            false,
        );
        $evidenceService->recordEvidence(
            $booking->fresh(),
            SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED,
            false,
        );

        $reconcileService->reconcileFromStoredEvidence($booking->fresh(), ['source' => 'test']);
        $attemptCount = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count();
        $auditCount = AuditLog::query()
            ->where('auditable_id', $booking->id)
            ->where('action', SabreGdsControlledCancelEvidenceService::AUDIT_ACTION)
            ->count();
        $reconcileAuditCount = AuditLog::query()
            ->where('auditable_id', $booking->id)
            ->where('action', SabreGdsCancellationReconciliationService::AUDIT_ACTION)
            ->count();
        $commCount = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->whereIn('event', [BookingCommunicationEvent::BookingCancelled->value, 'booking_cancelled'])
            ->count();

        $reconcileService->reconcileFromStoredEvidence($booking->fresh(), ['source' => 'test']);

        $this->assertSame(1, $auditCount);
        $this->assertSame(1, $reconcileAuditCount);
        $this->assertSame($attemptCount, SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count());
        $this->assertSame($commCount, CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->whereIn('event', [BookingCommunicationEvent::BookingCancelled->value, 'booking_cancelled'])
            ->count());
        $this->assertSame(0, SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', 'ticket')
            ->count());
        Http::assertNothingSent();
    }

    public function test_legacy_evidence_verify_mode_requires_zero_segments_and_no_tickets(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api-crt.cert.havail.sabre.test/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'isTicketed' => false,
                'flights' => [['carrier' => 'PK', 'flightNumber' => '203']],
                'allSegments' => [['status' => 'HK']],
            ], 200),
        ]);
        Cache::flush();

        $booking = $this->seedSabreBooking([], 'https://api-crt.cert.havail.sabre.test');

        $exit = Artisan::call('sabre:gds-record-cancel-evidence', [
            '--booking' => (string) $booking->id,
            '--classification' => SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED,
            '--confirm' => SabreGdsControlledCancelEvidenceService::CONFIRM_PHRASE,
            '--verify' => true,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('read_only_verification_failed', Artisan::output());
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'getBooking'));
        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'cancelBooking'));
    }

    protected function seedSabreBooking(array $overrides = [], ?string $baseUrl = null): Booking
    {
        $conn = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => $baseUrl ?? 'https://api-crt.cert.havail.sabre.test',
        ]);

        $meta = array_merge([
            'supplier_connection_id' => $conn->id,
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_api_booking_id' => 'ORDER-1',
        ], is_array($overrides['meta'] ?? null) ? $overrides['meta'] : []);
        unset($overrides['meta']);

        $booking = Booking::factory()->create(array_merge([
            'agency_id' => $conn->agency_id,
            'status' => BookingStatus::Confirmed,
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => 'FEZJFP',
            'supplier_reference' => 'FEZJFP',
            'supplier_booking_status' => 'pending_ticketing',
            'meta' => $meta,
        ], $overrides));

        SupplierBooking::query()->create([
            'agency_id' => $conn->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $conn->id,
            'provider' => SupplierProvider::Sabre->value,
            'pnr' => 'FEZJFP',
            'supplier_reference' => 'FEZJFP',
            'status' => 'pending_ticketing',
        ]);

        return $booking->fresh();
    }

    protected function seedSabreBookingWithLegacyInspectAttemptOnly(): Booking
    {
        $booking = $this->seedSabreBooking();
        $connId = (int) (is_array($booking->meta) ? ($booking->meta['supplier_connection_id'] ?? 0) : 0);

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connId > 0 ? $connId : null,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'inspect_cancel_pnr',
            'status' => 'attempted',
            'safe_summary' => [
                'source' => 'sabre_inspect_cancel_booking',
                'live_call_attempted' => true,
                'http_status' => '200',
            ],
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        return $booking->fresh();
    }
}
