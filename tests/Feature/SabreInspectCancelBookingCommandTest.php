<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreCancelBookingInspectProbe;
use App\Services\Suppliers\Sabre\SabreCancelPayloadBuilder;
use App\Services\Suppliers\Sabre\SabreCancelProbeDiagnostics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreInspectCancelBookingCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.cancel_enabled', false);
        Config::set('suppliers.sabre.cancel_live_call_enabled', false);
        Config::set('suppliers.sabre.cancel_require_confirmation', true);
        Config::set('suppliers.sabre.cancel_allow_production_send', false);
        Config::set('suppliers.sabre.cancel_allow_production_host', false);
        Config::set('suppliers.sabre.cancel_payload_style', SabreCancelPayloadBuilder::CONFIG_STYLE_AUTO_MATRIX_CURRENT);

        parent::tearDown();
    }

    public function test_dry_run_allowed_when_app_env_is_production(): void
    {
        Http::fake();
        Config::set('app.env', 'production');
        Config::set('suppliers.sabre.cancel_enabled', false);
        Config::set('suppliers.sabre.cancel_live_call_enabled', false);

        $booking = $this->sabreBookingWithPnr();

        $result = app(SabreCancelBookingInspectProbe::class)->inspect($booking, false, null, false, null);
        $this->assertSame('dry_run', $result['mode'] ?? null);
        $this->assertFalse($result['live_call_attempted'] ?? true);

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
        ])
            ->expectsOutputToContain('"mode":"dry_run"')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_cancel_config_defaults_keep_cancellation_disabled_and_payload_style_safe(): void
    {
        $this->assertFalse((bool) config('suppliers.sabre.cancel_enabled'));
        $this->assertFalse((bool) config('suppliers.sabre.cancel_live_call_enabled'));
        $this->assertFalse((bool) config('suppliers.sabre.ticketing_enabled'));
        $this->assertSame(
            SabreCancelPayloadBuilder::CONFIG_STYLE_AUTO_MATRIX_CURRENT,
            SabreCancelPayloadBuilder::configuredPayloadStyle(),
        );
        $this->assertContains(
            SabreCancelPayloadBuilder::CONFIG_STYLE_CONFIRMATION_ID_RETRIEVE_CANCEL_ALL,
            SabreCancelPayloadBuilder::allowedConfiguredPayloadStyles(),
        );
    }

    public function test_configured_confirmation_retrieve_cancel_all_builds_expected_safe_shape(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api-crt.cert.havail.sabre.test/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'bookingSignature' => 'SIG-SECRET-ABC',
                'isCancelable' => true,
                'isTicketed' => false,
            ], 200),
        ]);
        Config::set('suppliers.sabre.cancel_payload_style', SabreCancelPayloadBuilder::CONFIG_STYLE_CONFIRMATION_ID_RETRIEVE_CANCEL_ALL);

        $booking = $this->sabreBookingWithPnr([], 'https://api-crt.cert.havail.sabre.test');
        $result = app(SabreCancelBookingInspectProbe::class)->inspect($booking, false, null, false, null, false, true);

        $this->assertSame(
            SabreCancelPayloadBuilder::STYLE_OFFICIAL_POSTMAN_CONFIRMATION_CANCEL_ALL,
            $result['recommended_payload_style'] ?? null,
        );
        $this->assertSame(
            SabreCancelPayloadBuilder::STYLE_OFFICIAL_POSTMAN_CONFIRMATION_CANCEL_ALL,
            $result['selected_payload_style'] ?? null,
        );
        $this->assertTrue($result['selected_payload_has_confirmation_id'] ?? false);
        $this->assertTrue($result['selected_payload_has_retrieve_booking'] ?? false);
        $this->assertTrue($result['selected_payload_has_cancel_all'] ?? false);
        $this->assertTrue($result['selected_payload_has_error_handling_policy'] ?? false);
        $this->assertFalse($result['selected_payload_has_booking_id'] ?? true);
        $this->assertFalse($result['selected_payload_has_booking_signature'] ?? true);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/trip/orders/getBooking'));
    }

    public function test_configured_booking_source_cancel_all_builds_expected_safe_shape(): void
    {
        Http::fake();
        Config::set('suppliers.sabre.cancel_payload_style', SabreCancelPayloadBuilder::CONFIG_STYLE_CONFIRMATION_ID_CANCEL_ALL_BOOKING_SOURCE);

        $booking = $this->sabreBookingWithPnr();
        $result = app(SabreCancelBookingInspectProbe::class)->inspect($booking, false, null, false, null, false);

        $this->assertSame(
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_BOOKING_SOURCE,
            $result['recommended_payload_style'] ?? null,
        );
        $this->assertTrue($result['selected_payload_has_confirmation_id'] ?? false);
        $this->assertTrue($result['selected_payload_has_cancel_all'] ?? false);
        $this->assertFalse($result['selected_payload_has_retrieve_booking'] ?? true);
        $this->assertFalse($result['selected_payload_has_booking_id'] ?? true);
        Http::assertNothingSent();
    }

    public function test_configured_style_prevents_booking_id_signature_from_overriding_selection(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api-crt.cert.havail.sabre.test/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'bookingSignature' => 'SIG-SECRET-ABC',
                'isCancelable' => true,
                'isTicketed' => false,
            ], 200),
        ]);
        Config::set('suppliers.sabre.cancel_payload_style', SabreCancelPayloadBuilder::CONFIG_STYLE_CONFIRMATION_ID_CANCEL_ALL);

        $booking = $this->sabreBookingWithPnr([], 'https://api-crt.cert.havail.sabre.test');
        $result = app(SabreCancelBookingInspectProbe::class)->inspect($booking, false, null, false, null, false, true);

        $this->assertSame(
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_ROOT,
            $result['recommended_payload_style'] ?? null,
        );
        $this->assertNotSame(
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_ALL,
            $result['recommended_payload_style'] ?? null,
        );
        $this->assertFalse($result['selected_payload_has_booking_id'] ?? true);
        $this->assertFalse($result['selected_payload_has_booking_signature'] ?? true);
    }

    public function test_dry_run_candidate_preview_includes_scalar_cancel_summary(): void
    {
        Http::fake();

        $booking = $this->sabreBookingWithPnr();
        $result = app(SabreCancelBookingInspectProbe::class)->inspect($booking, false, null, false, null, false);
        $candidates = is_array($result['candidate_payloads'] ?? null) ? $result['candidate_payloads'] : [];
        $official = collect($candidates)->firstWhere(
            'style',
            SabreCancelPayloadBuilder::STYLE_OFFICIAL_POSTMAN_CONFIRMATION_CANCEL_ALL,
        );

        $this->assertNotNull($official);
        $this->assertSame('/v1/trip/orders/cancelBooking', $official['endpoint_path'] ?? null);
        $this->assertSame('POST', $official['method'] ?? null);
        $this->assertTrue($official['has_confirmation_id'] ?? false);
        $this->assertTrue($official['has_cancel_all'] ?? false);
        $this->assertTrue($official['has_retrieve_booking'] ?? false);
        $this->assertTrue($official['has_error_handling_policy'] ?? false);
        $this->assertFalse($official['has_booking_id'] ?? true);
        $this->assertFalse($official['has_booking_signature'] ?? true);
        $this->assertFalse($official['has_order_item_ids'] ?? true);
        $this->assertFalse($official['has_segment_ids'] ?? true);
        $this->assertFalse($official['dry_run_only'] ?? true);
        $this->assertFalse($official['suppressed_by_history'] ?? true);
        Http::assertNothingSent();
    }

    public function test_dry_run_production_host_allowed_without_cancel_flags(): void
    {
        Http::fake();
        Config::set('app.env', 'production');
        Config::set('suppliers.sabre.cancel_enabled', false);
        Config::set('suppliers.sabre.cancel_live_call_enabled', false);
        Config::set('suppliers.sabre.cancel_allow_production_send', false);
        Config::set('suppliers.sabre.cancel_allow_production_host', false);

        $booking = $this->sabreBookingWithPnr([], 'https://api.platform.sabre.com');

        $result = app(SabreCancelBookingInspectProbe::class)->inspect($booking, false, null, false, null);
        $this->assertSame('dry_run', $result['mode'] ?? null);
        $this->assertTrue($result['endpoint']['production_host_confirmed'] ?? false);
        $gates = is_array($result['live_send_gates'] ?? null) ? $result['live_send_gates'] : [];
        $this->assertSame('dry_run_default', $gates['block_reason'] ?? null);

        Http::assertNothingSent();
    }

    public function test_dry_run_does_not_call_sabre(): void
    {
        Http::fake();

        $booking = $this->sabreBookingWithPnr();
        $result = app(SabreCancelBookingInspectProbe::class)->inspect($booking, false, null, false, null);

        $this->assertSame('dry_run', $result['mode'] ?? null);
        $this->assertFalse($result['live_call_attempted'] ?? true);
        $this->assertArrayHasKey('candidate_payloads', $result);
        $this->assertNotEmpty($result['candidate_payloads']);

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
        ])->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_missing_pnr_blocks_command(): void
    {
        $conn = $this->sabreConnection();
        $booking = Booking::factory()->create([
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => null,
            'supplier_reference' => null,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
            ],
        ]);

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
        ])
            ->expectsOutputToContain('booking_missing_pnr')
            ->assertExitCode(1);
    }

    public function test_ticketed_booking_blocks_command(): void
    {
        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $booking = $this->sabreBookingWithPnr([
            'ticketed_at' => now(),
            'ticketing_status' => 'ticketed',
        ]);

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE,
        ])
            ->expectsOutputToContain('booking_ticketed_blocked')
            ->assertExitCode(1);
    }

    public function test_non_sabre_booking_blocks_command(): void
    {
        $booking = Booking::factory()->create([
            'supplier' => 'duffel',
            'pnr' => 'ABC123',
            'meta' => ['supplier_provider' => 'duffel'],
        ]);

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
        ])
            ->expectsOutputToContain('booking_not_sabre')
            ->assertExitCode(1);
    }

    public function test_send_without_confirm_is_blocked(): void
    {
        Http::fake();
        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $booking = $this->sabreBookingWithPnr([], 'https://api-crt.cert.havail.sabre.test');

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
            '--send' => true,
        ])
            ->expectsOutputToContain('confirm_phrase_required')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_send_with_flags_disabled_is_blocked(): void
    {
        Http::fake();
        Config::set('suppliers.sabre.cancel_enabled', false);
        Config::set('suppliers.sabre.cancel_live_call_enabled', false);

        $booking = $this->sabreBookingWithPnr([], 'https://api-crt.cert.havail.sabre.test');

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
        ])
            ->expectsOutputToContain('sabre_cancel_disabled')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_send_production_host_blocked_in_production_without_allow_production_send_flag(): void
    {
        Http::fake();
        Config::set('app.env', 'production');
        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);
        Config::set('suppliers.sabre.cancel_allow_production_send', false);
        Config::set('suppliers.sabre.cancel_allow_production_host', true);

        $booking = $this->sabreBookingWithPnr([], 'https://api.platform.sabre.com');

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE_PRODUCTION,
        ])
            ->expectsOutputToContain('production_send_not_allowed')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_send_production_host_blocked_without_allow_production_host_flag(): void
    {
        Http::fake();
        Config::set('app.env', 'production');
        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);
        Config::set('suppliers.sabre.cancel_allow_production_send', true);
        Config::set('suppliers.sabre.cancel_allow_production_host', false);

        $booking = $this->sabreBookingWithPnr([], 'https://api.platform.sabre.com');

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE_PRODUCTION,
        ])
            ->expectsOutputToContain('production_host_not_allowed')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_send_production_host_blocked_with_cert_confirm_token(): void
    {
        Http::fake();
        Config::set('app.env', 'production');
        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);
        Config::set('suppliers.sabre.cancel_allow_production_send', true);
        Config::set('suppliers.sabre.cancel_allow_production_host', true);

        $booking = $this->sabreBookingWithPnr([], 'https://api.platform.sabre.com');

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
        ])
            ->expectsOutputToContain('confirm_phrase_required')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_send_blocked_when_host_is_neither_cert_nor_production(): void
    {
        Http::fake();
        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $booking = $this->sabreBookingWithPnr();

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE,
        ])
            ->expectsOutputToContain('sabre_host_not_cert_or_production')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_send_cert_host_requires_cancel_cert_pnr_token(): void
    {
        Http::fake();
        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $booking = $this->sabreBookingWithPnr([], 'https://api-crt.cert.havail.sabre.test');

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE_PRODUCTION,
        ])
            ->expectsOutputToContain('confirm_phrase_required')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_cancelled_booking_blocks_command(): void
    {
        $booking = $this->sabreBookingWithPnr([
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
        ])
            ->expectsOutputToContain('booking_already_cancelled_locally')
            ->assertExitCode(1);
    }

    public function test_live_send_records_redacted_attempt_when_all_gates_pass(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api-crt.cert.havail.sabre.test/v1/trip/orders/cancelBooking' => Http::response([
                'status' => 'Cancelled',
                'errors' => [],
            ], 200),
            'https://api-crt.cert.havail.sabre.test/v1/trip/orders/getBooking' => Http::response([
                'isCancelable' => false,
                'isTicketed' => false,
            ], 200),
        ]);
        Cache::flush();

        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $booking = $this->sabreBookingWithPnr([
            'supplier_api_booking_id' => 'ORDER-999',
        ], 'https://api-crt.cert.havail.sabre.test');

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE,
        ])
            ->expectsOutputToContain('"live_call_attempted":true')
            ->doesntExpectOutputToContain('fake-token-for-tests-only')
            ->doesntExpectOutputToContain('ORDER-999')
            ->assertExitCode(0);

        Http::assertSentCount(3);

        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', 'inspect_cancel_pnr')
            ->first();
        $this->assertNotNull($attempt);
        $this->assertNull($attempt->request_payload);
        $this->assertNull($attempt->response_payload);
        $summary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertSame('sabre_inspect_cancel_booking', $summary['source'] ?? null);
        $this->assertSame('200', $summary['http_status'] ?? null);
        $this->assertFalse($summary['booking_status_updated'] ?? true);

        $booking->refresh();
        $this->assertNotSame(BookingStatus::Cancelled, $booking->status);
    }

    public function test_post_cancel_shell_without_air_segments_is_confirmed_removed(): void
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

        $booking = $this->sabreBookingWithPnr([
            'supplier_api_booking_id' => 'ORDER-999',
        ], 'https://api-crt.cert.havail.sabre.test');

        $result = app(SabreCancelBookingInspectProbe::class)->inspect(
            $booking,
            true,
            SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            false,
            null,
            false,
        );

        $this->assertSame('CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED', $result['cancel_outcome_classification'] ?? null);
        $postCancel = is_array($result['post_cancel_get_booking'] ?? null) ? $result['post_cancel_get_booking'] : [];
        $this->assertFalse($postCancel['post_cancel_air_segments_present'] ?? true);
        $this->assertSame(0, $postCancel['post_cancel_segment_count'] ?? null);
        $this->assertTrue($postCancel['post_cancel_pnr_shell_present'] ?? false);
        $this->assertTrue($postCancel['cancel_air_segments_removed'] ?? false);
        $this->assertFalse($postCancel['post_cancel_ticket_numbers_present'] ?? true);

        $json = json_encode($result);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('TRIP-BOOKING-SECRET-99', $json);
        $this->assertStringNotContainsString('ORDER-999', $json);
    }

    public function test_post_cancel_retrieve_with_active_segments_stays_http_200_but_active(): void
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
                'flights' => [['carrier' => 'PK', 'flightNumber' => '203']],
                'allSegments' => [['status' => 'HK']],
                'journeys' => [['id' => 'J1']],
            ], 200),
        ]);
        Cache::flush();

        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $booking = $this->sabreBookingWithPnr([
            'supplier_api_booking_id' => 'ORDER-999',
        ], 'https://api-crt.cert.havail.sabre.test');

        $result = app(SabreCancelBookingInspectProbe::class)->inspect(
            $booking,
            true,
            SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            false,
            null,
            false,
        );

        $this->assertSame('HTTP_200_BUT_STILL_ACTIVE', $result['cancel_outcome_classification'] ?? null);
        $postCancel = is_array($result['post_cancel_get_booking'] ?? null) ? $result['post_cancel_get_booking'] : [];
        $this->assertTrue($postCancel['post_cancel_air_segments_present'] ?? false);
        $this->assertSame(1, $postCancel['post_cancel_segment_count'] ?? null);
        $this->assertFalse($postCancel['cancel_air_segments_removed'] ?? true);
    }

    public function test_no_items_cancelled_precedes_removed_air_shell_classification(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api-crt.cert.havail.sabre.test/v1/trip/orders/cancelBooking' => Http::response([
                'errors' => [[
                    'code' => 'NO_ITEMS_CANCELLED',
                    'message' => 'No items cancelled.',
                ]],
            ], 200),
            'https://api-crt.cert.havail.sabre.test/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'isCancelable' => true,
                'isTicketed' => false,
                'travelers' => [['id' => 'T1']],
            ], 200),
        ]);
        Cache::flush();

        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $booking = $this->sabreBookingWithPnr([
            'supplier_api_booking_id' => 'ORDER-999',
        ], 'https://api-crt.cert.havail.sabre.test');

        $result = app(SabreCancelBookingInspectProbe::class)->inspect(
            $booking,
            true,
            SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            false,
            null,
            false,
        );

        $this->assertSame('NO_ITEMS_CANCELLED', $result['cancel_outcome_classification'] ?? null);
    }

    public function test_cancel_data_missing_precedes_removed_air_shell_classification(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api-crt.cert.havail.sabre.test/v1/trip/orders/cancelBooking' => Http::response([
                'errors' => [[
                    'code' => 'CANCEL_DATA_MISSING',
                    'message' => 'No cancel data provided.',
                ]],
            ], 200),
            'https://api-crt.cert.havail.sabre.test/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'isCancelable' => true,
                'isTicketed' => false,
                'travelers' => [['id' => 'T1']],
            ], 200),
        ]);
        Cache::flush();

        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $booking = $this->sabreBookingWithPnr([
            'supplier_api_booking_id' => 'ORDER-999',
        ], 'https://api-crt.cert.havail.sabre.test');

        $result = app(SabreCancelBookingInspectProbe::class)->inspect(
            $booking,
            true,
            SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            false,
            null,
            false,
        );

        $this->assertSame('CANCEL_DATA_MISSING', $result['cancel_outcome_classification'] ?? null);
    }

    public function test_cancel_data_missing_demotes_confirmation_only_and_recommends_official_cancel_intent(): void
    {
        Http::fake();

        $booking = $this->sabreBookingWithPnr();
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'inspect_cancel_pnr',
            'status' => 'attempted',
            'safe_summary' => [
                'source' => 'sabre_inspect_cancel_booking',
                'payload_style' => SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR,
                'http_status' => '200',
                'response_error_codes' => ['CANCEL_DATA_MISSING'],
                'response_error_messages' => ['CANCEL_DATA_MISSING — No cancel data provided. Nothing was cancelled.'],
            ],
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        $result = app(SabreCancelBookingInspectProbe::class)->inspect($booking, false, null, false, null, true);

        $this->assertTrue($result['cancel_diagnostics']['cancel_data_missing_detected'] ?? false);
        $candidates = is_array($result['candidate_payloads'] ?? null) ? $result['candidate_payloads'] : [];
        $pnrOnly = collect($candidates)->firstWhere('style', SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR);
        $this->assertNotNull($pnrOnly);
        $this->assertFalse($pnrOnly['recommended'] ?? true);
        $this->assertSame('CANCEL_DATA_MISSING', $pnrOnly['previously_failed_reason'] ?? null);

        $recommended = collect($candidates)->firstWhere('recommended', true);
        $this->assertNotNull($recommended);
        $this->assertSame(
            SabreCancelPayloadBuilder::STYLE_OFFICIAL_POSTMAN_CONFIRMATION_CANCEL_ALL,
            $recommended['style'] ?? null,
        );
        $this->assertArrayHasKey('retrieveBooking', $recommended['request_body_redacted'] ?? []);
        $this->assertArrayHasKey('cancelAll', $recommended['request_body_redacted'] ?? []);
    }

    public function test_failed_history_classifies_no_items_and_invalid_cancel_target_safely(): void
    {
        Http::fake();

        $booking = $this->sabreBookingWithPnr();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['pnr_itinerary_snapshot'] = [
            'segments' => [['origin' => 'LOS', 'destination' => 'ABV']],
            'orderId' => 'ORD-SAFE-1',
        ];
        $booking->meta = $meta;
        $booking->save();
        $this->seedCancelAttempt(
            $booking,
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_ROOT,
            '200',
            ['NO_ITEMS_CANCELLED'],
            ['No items cancelled.'],
        );
        $this->seedCancelAttempt(
            $booking,
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_ORDER_ID_CANCEL_DATA,
            '400',
            ['INVALID_CANCEL_TARGET'],
            ['Invalid cancel target.'],
        );

        $result = app(SabreCancelBookingInspectProbe::class)->inspect($booking, false, null, false, null, true);
        $candidates = is_array($result['candidate_payloads'] ?? null) ? $result['candidate_payloads'] : [];
        $root = collect($candidates)->firstWhere(
            'style',
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_ROOT,
        );
        $order = collect($candidates)->firstWhere(
            'style',
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_ORDER_ID_CANCEL_DATA,
        );

        $this->assertSame('NO_ITEMS_CANCELLED', $root['previously_failed_reason'] ?? null);
        $this->assertTrue($root['suppressed_by_history'] ?? false);
        $this->assertSame('INVALID_CANCEL_TARGET', $order['previously_failed_reason'] ?? null);
        $this->assertTrue($order['suppressed_by_history'] ?? false);
        Http::assertNothingSent();
    }

    public function test_snapshot_order_ids_surface_order_item_candidate(): void
    {
        Http::fake();

        $booking = $this->sabreBookingWithPnr();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['pnr_itinerary_snapshot'] = [
            'source' => 'sabre_trip_orders_get_booking',
            'pnr' => 'IJYJMV',
            'segments' => [
                ['origin' => 'LOS', 'destination' => 'ABV'],
            ],
            'orderId' => 'ORD-SNAP-1',
            'orderItemIds' => ['OI-1', 'OI-2'],
        ];
        $booking->meta = $meta;
        $booking->save();

        $result = app(SabreCancelBookingInspectProbe::class)->inspect($booking, false, null, false, null, true);

        $this->assertTrue($result['cancel_diagnostics']['pnr_snapshot_present'] ?? false);
        $this->assertSame(2, $result['cancel_diagnostics']['order_item_ids_count'] ?? 0);

        $candidates = is_array($result['candidate_payloads'] ?? null) ? $result['candidate_payloads'] : [];
        $orderItems = collect($candidates)->firstWhere(
            'style',
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_ORDER_ITEMS_CANCEL,
        );
        $this->assertNotNull($orderItems);
        $this->assertTrue($orderItems['recommended'] ?? false);
        $this->assertTrue($orderItems['required_snapshot_fields_present'] ?? false);
    }

    public function test_candidate_preview_redacts_nested_identifiers(): void
    {
        Http::fake();

        $booking = $this->sabreBookingWithPnr([
            'supplier_api_booking_id' => 'SECRET-ORDER-42',
        ]);

        $result = app(SabreCancelBookingInspectProbe::class)->inspect($booking, false, null, false, null, false);
        $candidates = is_array($result['candidate_payloads'] ?? null) ? $result['candidate_payloads'] : [];
        $json = json_encode($candidates);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('SECRET-ORDER-42', $json);
        $this->assertStringContainsString('***REDACTED***', $json);
    }

    public function test_two_failed_styles_are_demoted(): void
    {
        Http::fake();

        $booking = $this->sabreBookingWithPnr();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['pnr_itinerary_snapshot'] = [
            'segments' => [['origin' => 'LOS', 'destination' => 'ABV']],
            'segmentIds' => ['SEG-1'],
        ];
        $booking->meta = $meta;
        $booking->save();

        foreach ([
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR,
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_SEGMENT_IDS_CANCEL,
        ] as $style) {
            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'provider' => SupplierProvider::Sabre->value,
                'action' => 'inspect_cancel_pnr',
                'status' => 'attempted',
                'safe_summary' => [
                    'payload_style' => $style,
                    'http_status' => '200',
                    'response_error_codes' => ['CANCEL_DATA_MISSING'],
                    'response_error_messages' => ['CANCEL_DATA_MISSING — No cancel data provided.'],
                ],
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);
        }

        $result = app(SabreCancelBookingInspectProbe::class)->inspect($booking, false, null, false, null, true);
        $diag = is_array($result['cancel_diagnostics'] ?? null) ? $result['cancel_diagnostics'] : [];

        $this->assertContains(
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR,
            $diag['cancel_data_missing_styles'] ?? [],
        );
        $this->assertContains(
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_SEGMENT_IDS_CANCEL,
            $diag['cancel_data_missing_styles'] ?? [],
        );

        $candidates = is_array($result['candidate_payloads'] ?? null) ? $result['candidate_payloads'] : [];
        foreach ([
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR,
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_SEGMENT_IDS_CANCEL,
        ] as $style) {
            $row = collect($candidates)->firstWhere('style', $style);
            $this->assertNotNull($row);
            $this->assertFalse($row['recommended'] ?? true);
            $this->assertSame('CANCEL_DATA_MISSING', $row['previously_failed_reason'] ?? null);
        }

        $recommended = (string) ($result['recommended_payload_style'] ?? '');
        $this->assertNotContains($recommended, $diag['cancel_data_missing_styles'] ?? []);
    }

    public function test_style_option_selects_exact_style(): void
    {
        Http::fake();

        $booking = $this->sabreBookingWithPnr();
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CANCEL_REQUEST_ROOT;

        $result = app(SabreCancelBookingInspectProbe::class)->inspect($booking, false, null, false, $style, false);

        $this->assertSame($style, $result['selected_payload_style'] ?? null);
        $this->assertTrue($result['style_explicitly_selected'] ?? false);
        $candidates = is_array($result['candidate_payloads'] ?? null) ? $result['candidate_payloads'] : [];
        $selected = collect($candidates)->firstWhere('style', $style);
        $this->assertNotNull($selected);
        $this->assertArrayHasKey('CancelBookingRequest', $selected['request_body_redacted'] ?? []);
    }

    public function test_unknown_style_returns_error(): void
    {
        Http::fake();

        $booking = $this->sabreBookingWithPnr();

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
            '--style' => 'not_a_real_cancel_style',
        ])
            ->expectsOutputToContain('unknown_cancel_payload_style')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_list_styles_prints_without_pii(): void
    {
        Http::fake();

        $booking = $this->sabreBookingWithPnr([
            'pnr' => 'SECRET1',
            'supplier_api_booking_id' => 'ORDER-SECRET-99',
        ]);

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
            '--list-styles' => true,
        ])
            ->expectsOutputToContain('"mode":"list_styles"')
            ->doesntExpectOutputToContain('SECRET1')
            ->doesntExpectOutputToContain('ORDER-SECRET-99')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_live_send_uses_only_selected_style(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api-crt.cert.havail.sabre.test/v1/trip/orders/cancelBooking' => Http::response(['errors' => []], 200),
            'https://api-crt.cert.havail.sabre.test/v1/trip/orders/getBooking' => Http::response([
                'isCancelable' => true,
                'isTicketed' => false,
            ], 200),
        ]);
        Cache::flush();

        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $booking = $this->sabreBookingWithPnr([], 'https://api-crt.cert.havail.sabre.test');
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CANCEL_REQUEST_CONFIRMATION;

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            '--style' => $style,
        ])
            ->expectsOutputToContain('"selected_payload_style":"'.$style.'"')
            ->assertExitCode(0);

        Http::assertSent(function ($request) {
            $body = $request->data();
            if (! is_array($body)) {
                return false;
            }

            return array_key_exists('CancelBookingRQ', $body)
                && is_array($body['CancelBookingRQ'])
                && array_key_exists('confirmationId', $body['CancelBookingRQ']);
        });

        Http::assertSentCount(3);
    }

    public function test_refresh_trip_order_context_recommends_booking_id_cancel_style(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api-crt.cert.havail.sabre.test/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'bookingSignature' => 'SIG-SECRET-ABC',
                'isCancelable' => true,
                'isTicketed' => false,
            ], 200),
        ]);

        $booking = $this->sabreBookingWithPnr([], 'https://api-crt.cert.havail.sabre.test');

        $result = app(SabreCancelBookingInspectProbe::class)->inspect(
            $booking,
            false,
            null,
            false,
            null,
            false,
            true,
        );

        $diag = is_array($result['cancel_diagnostics'] ?? null) ? $result['cancel_diagnostics'] : [];
        $this->assertTrue($diag['trip_order_booking_id_present'] ?? false);
        $this->assertTrue($diag['trip_order_booking_signature_present'] ?? false);
        $this->assertTrue($diag['trip_order_is_cancelable'] ?? false);
        $this->assertSame('getBooking', $diag['trip_order_context_source'] ?? null);
        $this->assertSame(
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_ALL,
            $result['recommended_payload_style'] ?? null,
        );

        $json = json_encode($result);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('TRIP-BOOKING-SECRET-99', $json);
        $this->assertStringNotContainsString('SIG-SECRET-ABC', $json);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v1/trip/orders/getBooking');
        });
    }

    public function test_http_200_cancel_all_root_demoted_when_still_cancelable(): void
    {
        Http::fake();

        $booking = $this->sabreBookingWithPnr();
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'inspect_cancel_pnr',
            'status' => 'attempted',
            'safe_summary' => [
                'payload_style' => SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_ROOT,
                'http_status' => '200',
                'response_error_codes' => [],
                'response_error_messages' => [],
            ],
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['trip_order_cancel_context'] = [
            'bookingId' => 'CACHED-BOOKING-ID-1',
            'isCancelable' => true,
            'isTicketed' => false,
        ];
        $booking->meta = $meta;
        $booking->save();

        $result = app(SabreCancelBookingInspectProbe::class)->inspect(
            $booking,
            false,
            null,
            false,
            null,
            true,
            false,
        );

        $candidates = is_array($result['candidate_payloads'] ?? null) ? $result['candidate_payloads'] : [];
        $rootStyle = collect($candidates)->firstWhere(
            'style',
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_ROOT,
        );
        $this->assertNotNull($rootStyle);
        $this->assertFalse($rootStyle['recommended'] ?? true);
        $this->assertSame(
            'HTTP_200_BUT_STILL_ACTIVE',
            $rootStyle['previously_ineffective_reason'] ?? null,
        );
        $this->assertSame(
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_ALL_ROOT,
            $result['recommended_payload_style'] ?? null,
        );

        $json = json_encode($result);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('CACHED-BOOKING-ID-1', $json);
    }

    public function test_live_send_refresh_without_style_is_blocked(): void
    {
        Http::fake();
        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $booking = $this->sabreBookingWithPnr([], 'https://api-crt.cert.havail.sabre.test');

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--refresh-trip-order-context' => true,
            '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
        ])
            ->expectsOutputToContain('refresh_trip_order_context_live_send_requires_explicit_style')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_live_send_refresh_non_booking_id_style_is_blocked(): void
    {
        Http::fake();
        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $booking = $this->sabreBookingWithPnr([], 'https://api-crt.cert.havail.sabre.test');
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR;

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--refresh-trip-order-context' => true,
            '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            '--style' => $style,
        ])
            ->expectsOutputToContain('refresh_trip_order_context_live_send_requires_booking_id_style')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_live_send_refresh_probe_sets_live_call_attempted(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api-crt.cert.havail.sabre.test/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'isCancelable' => true,
                'isTicketed' => false,
            ], 200),
            'https://api-crt.cert.havail.sabre.test/v1/trip/orders/cancelBooking' => Http::response(['status' => 'Cancelled'], 200),
        ]);
        Cache::flush();

        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $booking = $this->sabreBookingWithPnr([], 'https://api-crt.cert.havail.sabre.test');
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_ALL_ROOT;

        $result = app(SabreCancelBookingInspectProbe::class)->inspect(
            $booking,
            true,
            SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            false,
            $style,
            false,
            true,
        );

        $this->assertTrue($result['live_call_attempted'] ?? false, json_encode($result));
        $this->assertTrue($result['trip_order_context_refreshed_for_live_send'] ?? false);
        $this->assertTrue($result['style_explicitly_selected'] ?? false);
        $this->assertFalse($result['booking_status_updated'] ?? true);
        $json = json_encode($result);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('TRIP-BOOKING-SECRET-99', $json);
    }

    public function test_live_send_refresh_booking_id_style_calls_get_booking_then_cancel_once(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api-crt.cert.havail.sabre.test/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'bookingSignature' => 'SIG-SECRET-ABC',
                'isCancelable' => true,
                'isTicketed' => false,
            ], 200),
            'https://api-crt.cert.havail.sabre.test/v1/trip/orders/cancelBooking' => Http::response([
                'status' => 'Cancelled',
                'errors' => [],
            ], 200),
        ]);
        Cache::flush();

        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $booking = $this->sabreBookingWithPnr([], 'https://api-crt.cert.havail.sabre.test');
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_ALL_ROOT;

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--refresh-trip-order-context' => true,
            '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            '--style' => $style,
        ])
            ->expectsOutputToContain('cancel_inspect_json=')
            ->assertExitCode(0);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v1/trip/orders/getBooking');
        });
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/v1/trip/orders/cancelBooking')) {
                return false;
            }
            $body = $request->data();

            return is_array($body)
                && ($body['bookingId'] ?? null) === 'TRIP-BOOKING-SECRET-99'
                && ($body['cancelAll'] ?? null) === true;
        });
        Http::assertSentCount(4);

        $booking->refresh();
        $this->assertNotSame(BookingStatus::Cancelled, $booking->status);
        $this->assertFalse($booking->wasChanged('status'));
    }

    public function test_live_send_refresh_missing_booking_id_blocks(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api-crt.cert.havail.sabre.test/v1/trip/orders/getBooking' => Http::response([
                'isCancelable' => true,
                'isTicketed' => false,
            ], 200),
        ]);
        Cache::flush();

        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $booking = $this->sabreBookingWithPnr([], 'https://api-crt.cert.havail.sabre.test');
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_ALL_ROOT;

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--refresh-trip-order-context' => true,
            '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            '--style' => $style,
        ])
            ->expectsOutputToContain('trip_order_booking_id_missing')
            ->assertExitCode(1);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v1/trip/orders/getBooking');
        });
        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/v1/trip/orders/cancelBooking');
        });
    }

    public function test_live_send_refresh_not_cancelable_blocks(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api-crt.cert.havail.sabre.test/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'isCancelable' => false,
                'isTicketed' => false,
            ], 200),
        ]);
        Cache::flush();

        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $booking = $this->sabreBookingWithPnr([], 'https://api-crt.cert.havail.sabre.test');
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_ALL_ROOT;

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--refresh-trip-order-context' => true,
            '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            '--style' => $style,
        ])
            ->expectsOutputToContain('trip_order_not_cancelable')
            ->assertExitCode(1);

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/v1/trip/orders/cancelBooking');
        });
    }

    public function test_live_send_refresh_signature_style_missing_signature_blocks(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api-crt.cert.havail.sabre.test/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'isCancelable' => true,
                'isTicketed' => false,
            ], 200),
        ]);
        Cache::flush();

        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $booking = $this->sabreBookingWithPnr([], 'https://api-crt.cert.havail.sabre.test');
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_ALL;

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--refresh-trip-order-context' => true,
            '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            '--style' => $style,
        ])
            ->expectsOutputToContain('trip_order_booking_signature_missing')
            ->assertExitCode(1);

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/v1/trip/orders/cancelBooking');
        });
    }

    public function test_live_send_production_host_allowed_with_all_flags_and_prod_confirm_token(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api.platform.sabre.com/v1/trip/orders/cancelBooking' => Http::response([
                'status' => 'Cancelled',
                'errors' => [],
            ], 200),
            'https://api.platform.sabre.com/v1/trip/orders/getBooking' => Http::response([
                'isCancelable' => false,
                'isTicketed' => false,
            ], 200),
        ]);
        Cache::flush();

        Config::set('app.env', 'production');
        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);
        Config::set('suppliers.sabre.cancel_allow_production_send', true);
        Config::set('suppliers.sabre.cancel_allow_production_host', true);

        $booking = $this->sabreBookingWithPnr([
            'supplier_api_booking_id' => 'ORDER-PROD-1',
        ], 'https://api.platform.sabre.com');

        $connId = (int) data_get($booking->meta, 'supplier_connection_id');
        $conn = SupplierConnection::query()->find($connId);
        $this->assertNotNull($conn);
        $this->assertSame('https://api.platform.sabre.com', $conn->base_url);

        $result = app(SabreCancelBookingInspectProbe::class)->inspect(
            $booking,
            true,
            SabreCancelBookingInspectProbe::CONFIRM_PHRASE_PRODUCTION,
            false,
            null,
            false,
        );
        $this->assertTrue($result['endpoint']['production_host_confirmed'] ?? false);
        $this->assertTrue($result['live_call_attempted'] ?? false);
        $this->assertSame(
            SabreCancelPayloadBuilder::STYLE_OFFICIAL_POSTMAN_CONFIRMATION_CANCEL_ALL,
            $result['selected_payload_style'] ?? null,
        );
        $this->assertNotEmpty($result['cancel_diagnostics'] ?? null);
        $this->assertSame('CANCEL_CONFIRMED', $result['cancel_outcome_classification'] ?? null);

        Http::assertSentCount(3);

        $booking->refresh();
        $this->assertNotSame(BookingStatus::Cancelled, $booking->status);
        $this->assertFalse($booking->wasChanged('status'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function sabreBookingWithPnr(array $overrides = [], ?string $baseUrl = null): Booking
    {
        $conn = $this->sabreConnection($baseUrl);

        return Booking::factory()->create(array_merge([
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => 'IJYJMV',
            'status' => BookingStatus::Confirmed,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
            ],
        ], $overrides));
    }

    protected function sabreConnection(?string $baseUrl = null): SupplierConnection
    {
        return SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => $baseUrl ?? 'https://example.sabre.test',
        ]);
    }

    public function test_http_400_nested_source_pointer_sanitized_in_cancel_probe(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api-crt.cert.havail.sabre.test/v1/trip/orders/cancelBooking' => Http::response([
                'errors' => [[
                    'code' => 'INVALID_VALUE',
                    'type' => 'Validation',
                    'message' => 'Validation Failed: must not be null',
                    'detail' => 'bookingSignature must not be null',
                    'source' => [
                        'pointer' => '/cancelBookingRequest/bookingSignature',
                        'parameter' => 'bookingSignature',
                        'field' => 'bookingSignature',
                    ],
                ]],
            ], 400),
        ]);
        Cache::flush();

        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $booking = $this->sabreBookingWithPnr([], 'https://api-crt.cert.havail.sabre.test');
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_REQUEST_CONFIRMATION_CANCEL_DATA;

        $result = app(SabreCancelBookingInspectProbe::class)->inspect(
            $booking,
            true,
            SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            false,
            $style,
            false,
            false,
        );

        $this->assertTrue($result['live_call_attempted'] ?? false, json_encode($result));
        $probe = is_array($result['cancel_probe'] ?? null) ? $result['cancel_probe'] : [];
        $details = is_array($probe['response_error_details_sanitized'] ?? null) ? $probe['response_error_details_sanitized'] : [];
        $this->assertNotEmpty($details, json_encode($probe));
        $first = $details[0] ?? [];
        $this->assertSame('INVALID_VALUE', $first['code'] ?? null);
        $this->assertSame('/cancelBookingRequest/bookingSignature', $first['source']['pointer'] ?? null);
        $missing = is_array($probe['validation_missing_fields_sanitized'] ?? null) ? $probe['validation_missing_fields_sanitized'] : [];
        $this->assertContains('/cancelBookingRequest/bookingSignature', $missing);
        $this->assertContains('bookingSignature', $missing);

        $json = json_encode($probe);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('fake-token-for-tests-only', $json);

        $booking->refresh();
        $this->assertNotSame(BookingStatus::Cancelled, $booking->status);
    }

    public function test_sanitized_error_details_redact_raw_identifiers(): void
    {
        $details = SabreCancelProbeDiagnostics::extractSanitizedErrorDetailsFromJson([
            'errors' => [[
                'code' => 'CANCEL_DATA_MISSING',
                'message' => 'confirmationId IJYJMV missing cancel data',
                'source' => ['pointer' => '/confirmationId'],
            ]],
        ]);

        $json = json_encode($details);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('IJYJMV', $json);
        $this->assertStringContainsString('[REDACTED]', $json);
    }

    public function test_duplicate_cancel_data_body_detected_and_not_recommended(): void
    {
        Http::fake();

        $booking = $this->sabreBookingWithPnr();
        $this->seedCancelAttempt(
            $booking,
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR.'_cancel_data',
            '200',
            ['CANCEL_DATA_MISSING'],
            ['CANCEL_DATA_MISSING'],
        );

        $result = app(SabreCancelBookingInspectProbe::class)->inspect($booking, false, null, false, null, true);
        $candidates = is_array($result['candidate_payloads'] ?? null) ? $result['candidate_payloads'] : [];
        $duplicateStyle = collect($candidates)->firstWhere(
            'style',
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_DATA_CANCEL_ALL,
        );
        $this->assertNotNull($duplicateStyle);
        $this->assertSame(
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR.'_cancel_data',
            $duplicateStyle['duplicate_of_style'] ?? null,
        );
        $this->assertTrue($duplicateStyle['duplicate_of_failed_style'] ?? false);
        $this->assertFalse($duplicateStyle['recommended'] ?? true);
        $this->assertNotSame(
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_DATA_CANCEL_ALL,
            $result['recommended_payload_style'] ?? null,
        );
        $blocked = (string) ($result['recommended_style_blocked_reason'] ?? '');
        $this->assertStringContainsString('semantically equivalent', $blocked);
    }

    public function test_next_action_stop_when_unique_simple_bodies_exhausted_booking_26_style(): void
    {
        Http::fake();

        $booking = $this->sabreBookingWithPnr();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['trip_order_cancel_context'] = [
            'bookingId' => 'CACHED-BOOKING-ID-1',
            'bookingSignature' => 'SIG-CACHED-1',
            'isCancelable' => true,
            'isTicketed' => false,
        ];
        $booking->meta = $meta;
        $booking->save();

        $this->seedCancelAttempt($booking, SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR, '400', ['INVALID_VALUE'], ['Validation Failed: must not be null']);
        $this->seedCancelAttempt($booking, SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR.'_cancel_all', '400', ['INVALID_VALUE'], ['Validation Failed: must not be null']);
        $this->seedCancelAttempt($booking, SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR.'_cancel_data', '200', ['CANCEL_DATA_MISSING'], ['CANCEL_DATA_MISSING']);
        $this->seedCancelAttempt($booking, SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_RECORD_LOCATOR, '400', ['INVALID_VALUE'], ['Validation Failed: must not be null']);
        $this->seedCancelAttempt($booking, SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_ALL_ROOT, '400', ['INVALID_VALUE'], ['Validation Failed: must not be null']);
        $this->seedCancelAttempt($booking, SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_DATA, '400', ['INVALID_VALUE'], ['Validation Failed: must not be null']);
        $this->seedCancelAttempt($booking, SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_ALL, '400', ['INVALID_VALUE'], ['Validation Failed: must not be null']);
        $this->seedCancelAttempt($booking, SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_DATA, '400', ['INVALID_VALUE'], ['Validation Failed: must not be null']);
        $this->seedCancelAttempt(
            $booking,
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_ROOT,
            '200',
            [],
            [],
        );

        $result = app(SabreCancelBookingInspectProbe::class)->inspect($booking, false, null, false, null, true);
        $this->assertSame(
            SabreCancelProbeDiagnostics::NEXT_ACTION_STOP_LIVE_PROBING,
            $result['next_action_recommendation'] ?? null,
        );
        $this->assertNull($result['recommended_payload_style'] ?? null);
        $total = (int) ($result['unique_payload_bodies_failed_or_ineffective_count'] ?? 0);
        $tested = (int) ($result['unique_payload_bodies_tested_count'] ?? 0);
        $this->assertGreaterThan(0, $tested);
        $this->assertSame($tested, $total);
    }

    public function test_stop_recommendation_when_tested_bodies_exhausted_despite_untested_simple_fingerprints(): void
    {
        Http::fake();

        $booking = $this->sabreBookingWithPnr([
            'supplier_api_booking_id' => 'ORDER-API-UNTESTED',
        ]);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['trip_order_cancel_context'] = [
            'bookingId' => 'CACHED-BOOKING-ID-1',
            'bookingSignature' => 'SIG-CACHED-1',
            'isCancelable' => true,
            'isTicketed' => false,
        ];
        $booking->meta = $meta;
        $booking->save();

        $this->seedCancelAttempt($booking, SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR, '400', ['INVALID_VALUE'], ['Validation Failed: must not be null']);
        $this->seedCancelAttempt($booking, SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR.'_cancel_all', '400', ['INVALID_VALUE'], ['Validation Failed: must not be null']);
        $this->seedCancelAttempt($booking, SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR.'_cancel_data', '200', ['CANCEL_DATA_MISSING'], ['CANCEL_DATA_MISSING']);
        $this->seedCancelAttempt($booking, SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_RECORD_LOCATOR, '400', ['INVALID_VALUE'], ['Validation Failed: must not be null']);
        $this->seedCancelAttempt($booking, SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_ALL_ROOT, '400', ['INVALID_VALUE'], ['Validation Failed: must not be null']);
        $this->seedCancelAttempt($booking, SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_DATA, '400', ['INVALID_VALUE'], ['Validation Failed: must not be null']);
        $this->seedCancelAttempt($booking, SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_ALL, '400', ['INVALID_VALUE'], ['Validation Failed: must not be null']);
        $this->seedCancelAttempt($booking, SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_DATA, '400', ['INVALID_VALUE'], ['Validation Failed: must not be null']);
        $this->seedCancelAttempt(
            $booking,
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_ROOT,
            '200',
            [],
            [],
        );

        $result = app(SabreCancelBookingInspectProbe::class)->inspect($booking, false, null, false, null, true);
        $tested = (int) ($result['unique_payload_bodies_tested_count'] ?? 0);
        $settled = (int) ($result['unique_payload_bodies_failed_or_ineffective_count'] ?? 0);
        $this->assertGreaterThan(0, $tested);
        $this->assertSame($tested, $settled);
        $this->assertSame(
            SabreCancelProbeDiagnostics::NEXT_ACTION_STOP_LIVE_PROBING,
            $result['next_action_recommendation'] ?? null,
        );
        $this->assertNull($result['recommended_payload_style'] ?? null);
        $this->assertNotSame(
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CANCEL_REQUEST_CONFIRMATION,
            $result['recommended_payload_style'] ?? null,
        );
        $this->assertStringContainsString(
            'All unique simple cancel payload bodies',
            (string) ($result['recommended_style_blocked_reason'] ?? ''),
        );

        $listStyles = app(SabreCancelBookingInspectProbe::class)->listStyles($booking, true, false);
        $this->assertSame(
            SabreCancelProbeDiagnostics::NEXT_ACTION_STOP_LIVE_PROBING,
            $listStyles['next_action_recommendation'] ?? null,
        );
        $this->assertNull($listStyles['recommended_payload_style'] ?? null);
        $styles = is_array($listStyles['styles'] ?? null) ? $listStyles['styles'] : [];
        $wrapper = collect($styles)->firstWhere(
            'style',
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CANCEL_REQUEST_CONFIRMATION,
        );
        $this->assertNotNull($wrapper);
        $this->assertFalse($wrapper['recommended'] ?? true);
        $this->assertSame(
            SabreCancelProbeDiagnostics::NEXT_ACTION_STOP_LIVE_PROBING,
            $wrapper['recommendation_suppressed_reason'] ?? null,
        );

        $supportPacket = app(SabreCancelBookingInspectProbe::class)->supportPacket($booking, true, false);
        $this->assertSame(
            SabreCancelProbeDiagnostics::NEXT_ACTION_STOP_LIVE_PROBING,
            $supportPacket['next_action_recommendation'] ?? null,
        );
        $this->assertNull($supportPacket['recommended_payload_style'] ?? null);
        $this->assertStringContainsString(
            'All unique simple cancel payload bodies',
            (string) ($supportPacket['recommended_style_blocked_reason'] ?? ''),
        );

        $booking->refresh();
        $this->assertNotSame(BookingStatus::Cancelled, $booking->status);
    }

    public function test_support_packet_includes_duplicate_payload_styles(): void
    {
        Http::fake();

        $booking = $this->sabreBookingWithPnr();
        $this->seedCancelAttempt(
            $booking,
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR.'_cancel_data',
            '200',
            ['CANCEL_DATA_MISSING'],
            ['CANCEL_DATA_MISSING'],
        );

        $payload = app(SabreCancelBookingInspectProbe::class)->supportPacket($booking, true, false);
        $duplicates = is_array($payload['duplicate_payload_styles'] ?? null) ? $payload['duplicate_payload_styles'] : [];
        $this->assertNotEmpty($duplicates);
        $match = collect($duplicates)->firstWhere(
            'style',
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_DATA_CANCEL_ALL,
        );
        $this->assertNotNull($match);
        $this->assertTrue($match['duplicate_of_failed_style'] ?? false);
        $this->assertArrayHasKey('next_action_recommendation', $payload);
    }

    public function test_next_action_recommendation_when_matrix_exhausted(): void
    {
        Http::fake();

        $booking = $this->sabreBookingWithPnr();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['trip_order_cancel_context'] = [
            'bookingId' => 'CACHED-BOOKING-ID-1',
            'isCancelable' => true,
            'isTicketed' => false,
        ];
        $booking->meta = $meta;
        $booking->save();

        foreach (SabreCancelProbeDiagnostics::MATRIX_CONFIRMATION_AND_CANCEL_DATA_STYLES as $style) {
            $this->seedCancelAttempt($booking, $style, '200', ['CANCEL_DATA_MISSING'], ['CANCEL_DATA_MISSING']);
        }
        foreach (SabreCancelProbeDiagnostics::MATRIX_BOOKING_ID_CANCEL_ALL_DATA_STYLES as $style) {
            $this->seedCancelAttempt($booking, $style, '400', ['INVALID_VALUE'], ['Validation Failed: must not be null']);
        }
        $this->seedCancelAttempt(
            $booking,
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_ROOT,
            '200',
            [],
            [],
        );

        $result = app(SabreCancelBookingInspectProbe::class)->inspect($booking, false, null, false, null, true);

        $this->assertSame(
            SabreCancelProbeDiagnostics::NEXT_ACTION_STOP_LIVE_PROBING,
            $result['next_action_recommendation'] ?? null,
        );

        $booking->refresh();
        $this->assertNotSame(BookingStatus::Cancelled, $booking->status);
    }

    public function test_list_styles_official_shape_audit_marks_ineffective_cancel_all_root(): void
    {
        Http::fake();

        $booking = $this->sabreBookingWithPnr();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['trip_order_cancel_context'] = [
            'bookingId' => 'CACHED-BOOKING-ID-1',
            'isCancelable' => true,
            'isTicketed' => false,
        ];
        $booking->meta = $meta;
        $booking->save();

        $this->seedCancelAttempt(
            $booking,
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_ROOT,
            '200',
            [],
            [],
        );

        $payload = app(SabreCancelBookingInspectProbe::class)->listStyles($booking, true, false);
        $styles = is_array($payload['styles'] ?? null) ? $payload['styles'] : [];
        $root = collect($styles)->firstWhere(
            'style',
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_ROOT,
        );
        $this->assertNotNull($root);
        $audit = is_array($root['official_shape_audit'] ?? null) ? $root['official_shape_audit'] : [];
        $this->assertSame(
            SabreCancelProbeDiagnostics::OFFICIAL_AUDIT_OFFICIAL_FULL_CANCEL_SHAPE_CANDIDATE,
            $audit['label'] ?? null,
        );
        $this->assertTrue($audit['verified_ineffective'] ?? false);
        $this->assertTrue($audit['do_not_auto_recommend'] ?? false);
        $this->assertFalse($root['recommended'] ?? true);
    }

    public function test_support_packet_includes_f3c_schema_inventory_and_escalation_template(): void
    {
        Http::fake();

        $booking = $this->sabreBookingWithPnr([], 'https://api.cert.platform.sabre.com');
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['trip_order_cancel_context'] = [
            'bookingId' => 'CACHED-BOOKING-ID-1',
            'bookingSignature' => 'CACHED-SIG-1',
            'isCancelable' => true,
            'isTicketed' => false,
        ];
        $meta['pnr_itinerary_sync'] = [
            'status' => 'synced',
            'is_cancelable' => true,
            'is_ticketed' => false,
            'ticket_numbers_present' => false,
            'booking_id_present' => true,
        ];
        $booking->meta = $meta;
        $booking->save();

        foreach (SabreCancelProbeDiagnostics::MATRIX_BOOKING_ID_CANCEL_ALL_DATA_STYLES as $style) {
            $this->seedCancelAttempt(
                $booking,
                $style,
                '400',
                ['INVALID_VALUE'],
                ['Validation Failed: must not be null'],
                ['/cancelBookingRequest/bookingSignature'],
            );
        }
        $this->seedCancelAttempt(
            $booking,
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CANCEL_BOOKING_REQUEST_BOOKING_ID_SIGNATURE_CANCEL_ALL,
            '400',
            ['INVALID_VALUE'],
            ['Validation Failed: must not be null'],
            ['/cancelBookingRequest/bookingSignature'],
        );

        $payload = app(SabreCancelBookingInspectProbe::class)->supportPacket($booking, true, false);

        $this->assertSame('support_packet', $payload['mode'] ?? null);
        $this->assertSame('CERT', $payload['endpoint']['host_type'] ?? null);
        $this->assertArrayHasKey('get_booking_cancel_schema_inventory', $payload);
        $this->assertNotEmpty($payload['cancel_schema_gap_diagnosis'] ?? []);
        $this->assertArrayHasKey('sabre_escalation_note_template', $payload);
        $this->assertSame(
            SabreCancelProbeDiagnostics::NEXT_ACTION_STOP_LIVE_PROBING,
            $payload['next_action_recommendation'] ?? null,
        );

        $json = json_encode($payload);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('CACHED-BOOKING-ID-1', $json);
        $this->assertStringNotContainsString('CACHED-SIG-1', $json);
        $this->assertStringContainsString('stop_live_probing_collect_sabre_contract_details', $json);

        Http::assertNothingSent();
    }

    public function test_dry_run_with_snapshot_includes_cancel_schema_inventory(): void
    {
        Http::fake();

        $booking = $this->sabreBookingWithPnr([], 'https://api.cert.platform.sabre.com');
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['trip_order_cancel_context'] = [
            'bookingId' => 'CACHED-BOOKING-ID-2',
            'bookingSignature' => 'CACHED-SIG-2',
            'isCancelable' => true,
            'isTicketed' => false,
        ];
        $booking->meta = $meta;
        $booking->save();

        $result = app(SabreCancelBookingInspectProbe::class)->inspect(
            $booking,
            false,
            null,
            false,
            null,
            true,
            false,
        );

        $this->assertArrayHasKey('get_booking_cancel_schema_inventory', $result);
        $this->assertArrayHasKey('cancel_schema_gap_diagnosis', $result);
        $this->assertSame('CERT', $result['endpoint_host_type'] ?? null);

        Http::assertNothingSent();
    }

    public function test_support_packet_outputs_sanitized_summary_without_pii(): void
    {
        Http::fake();

        $booking = $this->sabreBookingWithPnr([
            'pnr' => 'SECRET1',
            'supplier_api_booking_id' => 'ORDER-SECRET-99',
        ]);
        $this->seedCancelAttempt(
            $booking,
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_DATA,
            '400',
            ['INVALID_VALUE'],
            ['bookingSignature must not be null'],
            ['/cancelBookingRequest/bookingSignature'],
        );

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
            '--support-packet' => true,
        ])
            ->expectsOutputToContain('"mode":"support_packet"')
            ->doesntExpectOutputToContain('SECRET1')
            ->doesntExpectOutputToContain('ORDER-SECRET-99')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_direct_pnr_without_connection_fails(): void
    {
        $this->artisan('sabre:inspect-cancel-booking', [
            '--pnr' => 'RWGWZO',
        ])
            ->expectsOutputToContain('--connection={id} with --pnr')
            ->assertExitCode(1);
    }

    public function test_direct_pnr_with_booking_fails(): void
    {
        $this->artisan('sabre:inspect-cancel-booking', [
            '--pnr' => 'RWGWZO',
            '--booking' => '1',
            '--connection' => '2',
        ])
            ->expectsOutputToContain('not both')
            ->assertExitCode(1);
    }

    public function test_neither_pnr_nor_booking_fails(): void
    {
        $this->artisan('sabre:inspect-cancel-booking')
            ->expectsOutputToContain('--booking={id} or --pnr={locator}')
            ->assertExitCode(1);
    }

    public function test_direct_pnr_dry_run_builds_candidates_without_cancel_http(): void
    {
        Http::fake();

        $conn = $this->certSabreConnection();

        $result = app(SabreCancelBookingInspectProbe::class)->inspectDirectPnr(
            $conn,
            'RWGWZO',
            false,
            null,
            null,
            false,
        );

        $this->assertSame('direct_pnr', $result['probe_mode'] ?? null);
        $this->assertSame('dry_run', $result['mode'] ?? null);
        $this->assertFalse($result['live_call_attempted'] ?? true);
        $this->assertNotEmpty($result['candidate_payloads'] ?? []);

        $this->artisan('sabre:inspect-cancel-booking', [
            '--pnr' => 'RWGWZO',
            '--connection' => (string) $conn->id,
        ])
            ->expectsOutputToContain('"probe_mode":"direct_pnr"')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_direct_pnr_send_without_confirm_fails(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api.cert.platform.sabre.com/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'isCancelable' => true,
                'isTicketed' => false,
            ], 200),
        ]);
        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $conn = $this->certSabreConnection();

        $this->artisan('sabre:inspect-cancel-booking', [
            '--pnr' => 'RWGWZO',
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--refresh-trip-order-context' => true,
            '--style' => SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_ALL_ROOT,
        ])
            ->expectsOutputToContain('confirm_phrase_required')
            ->assertExitCode(1);

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/v1/trip/orders/cancelBooking');
        });
    }

    public function test_direct_pnr_send_with_cancel_flags_off_fails(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api.cert.platform.sabre.com/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'isCancelable' => true,
                'isTicketed' => false,
            ], 200),
        ]);
        Config::set('suppliers.sabre.cancel_enabled', false);
        Config::set('suppliers.sabre.cancel_live_call_enabled', false);

        $conn = $this->certSabreConnection();

        $this->artisan('sabre:inspect-cancel-booking', [
            '--pnr' => 'RWGWZO',
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            '--refresh-trip-order-context' => true,
            '--style' => SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_ALL_ROOT,
        ])
            ->expectsOutputToContain('sabre_cancel_disabled')
            ->assertExitCode(1);

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/v1/trip/orders/cancelBooking');
        });
    }

    public function test_direct_pnr_send_without_refresh_trip_order_context_fails(): void
    {
        Http::fake();
        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $conn = $this->certSabreConnection();

        $this->artisan('sabre:inspect-cancel-booking', [
            '--pnr' => 'RWGWZO',
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            '--style' => SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_ALL_ROOT,
        ])
            ->expectsOutputToContain('direct_pnr_live_send_requires_refresh_trip_order_context')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_direct_pnr_send_without_explicit_style_fails(): void
    {
        Http::fake();
        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $conn = $this->certSabreConnection();

        $this->artisan('sabre:inspect-cancel-booking', [
            '--pnr' => 'RWGWZO',
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            '--refresh-trip-order-context' => true,
        ])
            ->expectsOutputToContain('direct_pnr_live_send_requires_explicit_booking_id_style')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_direct_pnr_get_booking_ticketed_blocks_live_send(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api.cert.platform.sabre.com/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'isCancelable' => true,
                'isTicketed' => true,
            ], 200),
        ]);
        Cache::flush();

        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $conn = $this->certSabreConnection();
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_ALL_ROOT;

        $this->artisan('sabre:inspect-cancel-booking', [
            '--pnr' => 'RWGWZO',
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            '--refresh-trip-order-context' => true,
            '--style' => $style,
        ])
            ->expectsOutputToContain('trip_order_ticketed_blocked')
            ->assertExitCode(1);

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/v1/trip/orders/cancelBooking');
        });
    }

    public function test_direct_pnr_get_booking_not_cancelable_blocks_live_send(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api.cert.platform.sabre.com/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'isCancelable' => false,
                'isTicketed' => false,
            ], 200),
        ]);
        Cache::flush();

        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $conn = $this->certSabreConnection();
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_ALL_ROOT;

        $this->artisan('sabre:inspect-cancel-booking', [
            '--pnr' => 'RWGWZO',
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            '--refresh-trip-order-context' => true,
            '--style' => $style,
        ])
            ->expectsOutputToContain('trip_order_not_cancelable')
            ->assertExitCode(1);

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/v1/trip/orders/cancelBooking');
        });
    }

    public function test_direct_pnr_refresh_and_style_sends_one_cancel_call(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api.cert.platform.sabre.com/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'isCancelable' => true,
                'isTicketed' => false,
            ], 200),
            'https://api.cert.platform.sabre.com/v1/trip/orders/cancelBooking' => Http::response([
                'status' => 'Cancelled',
                'errors' => [],
            ], 200),
        ]);
        Cache::flush();

        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $conn = $this->certSabreConnection();
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_ALL_ROOT;

        $result = app(SabreCancelBookingInspectProbe::class)->inspectDirectPnr(
            $conn,
            'RWGWZO',
            true,
            SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            $style,
            true,
        );

        $this->assertSame('direct_pnr', $result['probe_mode'] ?? null);
        $this->assertTrue($result['live_call_attempted'] ?? false, json_encode($result));
        $this->assertFalse($result['supplier_booking_attempt_recorded'] ?? true);
        $this->assertSame('HTTP_200_BUT_STILL_ACTIVE', $result['cancel_outcome_classification'] ?? null);
        $json = json_encode($result);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('TRIP-BOOKING-SECRET-99', $json);
        $this->assertStringNotContainsString('fake-token-for-tests-only', $json);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/v1/trip/orders/cancelBooking')) {
                return false;
            }
            $body = $request->data();

            return is_array($body)
                && ($body['bookingId'] ?? null) === 'TRIP-BOOKING-SECRET-99'
                && ($body['cancelAll'] ?? null) === true;
        });

        $this->assertSame(
            0,
            SupplierBookingAttempt::query()->where('action', 'inspect_cancel_pnr')->count(),
        );
    }

    public function test_direct_pnr_dry_run_refresh_recommends_booking_id_cancel_all_root_without_signature(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api.cert.platform.sabre.com/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'isCancelable' => true,
                'isTicketed' => false,
            ], 200),
        ]);

        $conn = $this->certSabreConnection();

        $result = app(SabreCancelBookingInspectProbe::class)->inspectDirectPnr(
            $conn,
            'RWGWZO',
            false,
            null,
            null,
            true,
        );

        $this->assertSame(
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_ALL_ROOT,
            $result['recommended_payload_style'] ?? null,
        );
        $this->assertFalse($result['live_call_attempted'] ?? true);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v1/trip/orders/getBooking');
        });
        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/v1/trip/orders/cancelBooking');
        });
    }

    public function test_direct_pnr_dry_run_refresh_recommends_signature_cancel_all_when_signature_present(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api.cert.platform.sabre.com/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'bookingSignature' => 'SIG-SECRET-ABC',
                'isCancelable' => true,
                'isTicketed' => false,
            ], 200),
        ]);

        $conn = $this->certSabreConnection();

        $result = app(SabreCancelBookingInspectProbe::class)->inspectDirectPnr(
            $conn,
            'RWGWZO',
            false,
            null,
            null,
            true,
        );

        $this->assertSame(
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_ALL,
            $result['recommended_payload_style'] ?? null,
        );
        $this->assertFalse($result['live_call_attempted'] ?? true);
        $this->assertTrue($result['trip_order_booking_signature_present'] ?? false);

        $json = json_encode($result);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('TRIP-BOOKING-SECRET-99', $json);
        $this->assertStringNotContainsString('SIG-SECRET-ABC', $json);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v1/trip/orders/getBooking');
        });
        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/v1/trip/orders/cancelBooking');
        });
    }

    public function test_direct_pnr_live_send_allows_signature_cancel_all_when_signature_present(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api.cert.platform.sabre.com/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'bookingSignature' => 'SIG-SECRET-ABC',
                'isCancelable' => true,
                'isTicketed' => false,
            ], 200),
            'https://api.cert.platform.sabre.com/v1/trip/orders/cancelBooking' => Http::response([
                'status' => 'Cancelled',
                'errors' => [],
            ], 200),
        ]);
        Cache::flush();

        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $conn = $this->certSabreConnection();
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_ALL;

        $result = app(SabreCancelBookingInspectProbe::class)->inspectDirectPnr(
            $conn,
            'RWGWZO',
            true,
            SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            $style,
            true,
        );

        $this->assertTrue($result['live_call_attempted'] ?? false, json_encode($result));
        $this->assertSame($style, $result['selected_payload_style'] ?? null);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/v1/trip/orders/cancelBooking')) {
                return false;
            }
            $body = $request->data();

            return is_array($body)
                && ($body['bookingId'] ?? null) === 'TRIP-BOOKING-SECRET-99'
                && ($body['bookingSignature'] ?? null) === 'SIG-SECRET-ABC'
                && ($body['cancelAll'] ?? null) === true;
        });

        $json = json_encode($result);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('TRIP-BOOKING-SECRET-99', $json);
        $this->assertStringNotContainsString('SIG-SECRET-ABC', $json);
    }

    public function test_direct_pnr_live_send_blocks_signature_style_when_signature_missing(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api.cert.platform.sabre.com/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'isCancelable' => true,
                'isTicketed' => false,
            ], 200),
        ]);
        Cache::flush();

        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $conn = $this->certSabreConnection();
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_ALL;

        $this->artisan('sabre:inspect-cancel-booking', [
            '--pnr' => 'RWGWZO',
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            '--refresh-trip-order-context' => true,
            '--style' => $style,
        ])
            ->expectsOutputToContain('trip_order_booking_signature_missing')
            ->assertExitCode(1);

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/v1/trip/orders/cancelBooking');
        });
    }

    public function test_direct_pnr_live_send_allows_request_wrapped_when_signature_present(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api.cert.platform.sabre.com/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'bookingSignature' => 'SIG-SECRET-ABC',
                'isCancelable' => true,
                'isTicketed' => false,
            ], 200),
            'https://api.cert.platform.sabre.com/v1/trip/orders/cancelBooking' => Http::response([
                'status' => 'Cancelled',
                'errors' => [],
            ], 200),
        ]);
        Cache::flush();

        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $conn = $this->certSabreConnection();
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_REQUEST_WRAPPED;

        $result = app(SabreCancelBookingInspectProbe::class)->inspectDirectPnr(
            $conn,
            'RWGWZO',
            true,
            SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            $style,
            true,
        );

        $this->assertTrue($result['live_call_attempted'] ?? false, json_encode($result));
        $this->assertSame($style, $result['selected_payload_style'] ?? null);
        $shapeKeys = is_array($result['selected_payload_safe_shape_keys'] ?? null)
            ? $result['selected_payload_safe_shape_keys']
            : [];
        $this->assertContains('request', $shapeKeys);
        $this->assertTrue($result['selected_payload_has_booking_id'] ?? false);
        $this->assertTrue($result['selected_payload_has_booking_signature'] ?? false);
        $this->assertTrue($result['selected_payload_has_cancel_all'] ?? false);
        $this->assertFalse($result['selected_payload_has_cancel_data'] ?? true);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/v1/trip/orders/cancelBooking')) {
                return false;
            }
            $body = $request->data();
            if (! is_array($body) || ! is_array($body['request'] ?? null)) {
                return false;
            }
            $inner = $body['request'];

            return ($inner['bookingId'] ?? null) === 'TRIP-BOOKING-SECRET-99'
                && ($inner['bookingSignature'] ?? null) === 'SIG-SECRET-ABC'
                && ($inner['cancelAll'] ?? null) === true;
        });

        $json = json_encode($result);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('TRIP-BOOKING-SECRET-99', $json);
        $this->assertStringNotContainsString('SIG-SECRET-ABC', $json);
        $this->assertStringNotContainsString('fake-token-for-tests-only', $json);
    }

    public function test_direct_pnr_live_send_blocks_request_wrapped_when_signature_missing(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api.cert.platform.sabre.com/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'isCancelable' => true,
                'isTicketed' => false,
            ], 200),
        ]);
        Cache::flush();

        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $conn = $this->certSabreConnection();
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_REQUEST_WRAPPED;

        $this->artisan('sabre:inspect-cancel-booking', [
            '--pnr' => 'RWGWZO',
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            '--refresh-trip-order-context' => true,
            '--style' => $style,
        ])
            ->expectsOutputToContain('trip_order_booking_signature_missing')
            ->assertExitCode(1);

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/v1/trip/orders/cancelBooking');
        });
    }

    public function test_direct_pnr_production_host_blocked(): void
    {
        Http::fake();
        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $conn = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://api.platform.sabre.com',
        ]);
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_REQUEST_WRAPPED;

        $this->artisan('sabre:inspect-cancel-booking', [
            '--pnr' => 'RWGWZO',
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            '--refresh-trip-order-context' => true,
            '--style' => $style,
        ])
            ->expectsOutputToContain('direct_pnr_production_host_blocked')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_dry_run_selected_payload_diagnostics_for_booking_id_cancel_all_root(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api.cert.platform.sabre.com/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'bookingSignature' => 'SIG-SECRET-ABC',
                'isCancelable' => true,
                'isTicketed' => false,
            ], 200),
        ]);

        $conn = $this->certSabreConnection();
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_ALL_ROOT;

        $result = app(SabreCancelBookingInspectProbe::class)->inspectDirectPnr(
            $conn,
            'RWGWZO',
            false,
            null,
            $style,
            true,
        );

        $this->assertSame($style, $result['selected_payload_style'] ?? null);
        $this->assertTrue($result['selected_payload_has_booking_id'] ?? false);
        $this->assertFalse($result['selected_payload_has_booking_signature'] ?? true);
        $this->assertTrue($result['selected_payload_has_cancel_all'] ?? false);
        $this->assertFalse($result['selected_payload_has_cancel_data'] ?? true);
        $shapeKeys = is_array($result['selected_payload_safe_shape_keys'] ?? null)
            ? $result['selected_payload_safe_shape_keys']
            : [];
        $this->assertContains('bookingId', $shapeKeys);
        $this->assertContains('cancelAll', $shapeKeys);
        $this->assertSame([], $result['selected_payload_null_keys'] ?? null);
        $this->assertTrue($result['trip_order_booking_signature_present'] ?? false);

        $json = json_encode($result);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('TRIP-BOOKING-SECRET-99', $json);
        $this->assertStringNotContainsString('SIG-SECRET-ABC', $json);
        $this->assertStringNotContainsString('fake-token-for-tests-only', $json);

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/v1/trip/orders/cancelBooking');
        });
    }

    public function test_dry_run_selected_payload_diagnostics_for_booking_id_signature_cancel_all(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api.cert.platform.sabre.com/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'bookingSignature' => 'SIG-SECRET-ABC',
                'isCancelable' => true,
                'isTicketed' => false,
            ], 200),
        ]);

        $conn = $this->certSabreConnection();
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_ALL;

        $result = app(SabreCancelBookingInspectProbe::class)->inspectDirectPnr(
            $conn,
            'RWGWZO',
            false,
            null,
            $style,
            true,
        );

        $this->assertSame($style, $result['selected_payload_style'] ?? null);
        $this->assertTrue($result['selected_payload_has_booking_signature'] ?? false);
        $shapeKeys = is_array($result['selected_payload_safe_shape_keys'] ?? null)
            ? $result['selected_payload_safe_shape_keys']
            : [];
        $this->assertContains('bookingSignature', $shapeKeys);

        $json = json_encode($result);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('SIG-SECRET-ABC', $json);
    }

    public function test_selected_payload_null_keys_reports_explicit_null(): void
    {
        $builder = app(SabreCancelPayloadBuilder::class);
        $diag = $builder->selectedPayloadDiagnostics([
            'bookingId' => 'x',
            'cancelData' => null,
        ]);

        $nullKeys = is_array($diag['selected_payload_null_keys'] ?? null)
            ? $diag['selected_payload_null_keys']
            : [];
        $this->assertContains('cancelData', $nullKeys);
        $this->assertTrue($diag['selected_payload_has_booking_id'] ?? false);
        $this->assertFalse($diag['selected_payload_has_cancel_data'] ?? true);
    }

    public function test_dry_run_builds_cancel_booking_request_wrapper_candidates_redacted(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api.cert.platform.sabre.com/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'bookingSignature' => 'SIG-SECRET-ABC',
                'isCancelable' => true,
                'isTicketed' => false,
            ], 200),
        ]);

        $conn = $this->certSabreConnection();
        $result = app(SabreCancelBookingInspectProbe::class)->inspectDirectPnr(
            $conn,
            'RWGWZO',
            false,
            null,
            null,
            true,
        );

        $candidates = is_array($result['candidate_payloads'] ?? null) ? $result['candidate_payloads'] : [];
        $wrapperStyles = [
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CANCEL_BOOKING_REQUEST_BOOKING_ID_SIGNATURE_CANCEL_ALL,
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CANCEL_BOOKING_REQUEST_BOOKING_ID_SIGNATURE_CANCEL_DATA,
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CANCELBOOKINGREQUEST_BOOKING_ID_SIGNATURE_CANCEL_ALL,
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CANCELBOOKINGRQ_BOOKING_ID_SIGNATURE_CANCEL_ALL,
        ];
        foreach ($wrapperStyles as $style) {
            $row = collect($candidates)->firstWhere('style', $style);
            $this->assertNotNull($row, 'Missing candidate style: '.$style);
            $this->assertFalse($row['recommended'] ?? true);
            $this->assertTrue($row['required_trip_order_fields_present'] ?? false);
        }

        $cancelBookingRequestRow = collect($candidates)->firstWhere(
            'style',
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CANCEL_BOOKING_REQUEST_BOOKING_ID_SIGNATURE_CANCEL_ALL,
        );
        $this->assertNotNull($cancelBookingRequestRow);
        $this->assertSame(
            ['cancelBookingRequest'],
            $cancelBookingRequestRow['safe_shape_keys'] ?? null,
        );
        $body = is_array($cancelBookingRequestRow['request_body_redacted'] ?? null)
            ? $cancelBookingRequestRow['request_body_redacted']
            : [];
        $this->assertArrayHasKey('cancelBookingRequest', $body);
        $inner = is_array($body['cancelBookingRequest'] ?? null) ? $body['cancelBookingRequest'] : [];
        $this->assertSame('***REDACTED***', $inner['bookingId'] ?? null);
        $this->assertSame('***REDACTED***', $inner['bookingSignature'] ?? null);
        $this->assertTrue($inner['cancelAll'] ?? false);

        $this->assertSame(
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_ALL,
            $result['recommended_payload_style'] ?? null,
        );

        $json = json_encode($result);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('TRIP-BOOKING-SECRET-99', $json);
        $this->assertStringNotContainsString('SIG-SECRET-ABC', $json);

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/v1/trip/orders/cancelBooking');
        });
    }

    public function test_dry_run_selected_payload_diagnostics_for_cancel_booking_request_wrapper(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api.cert.platform.sabre.com/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'bookingSignature' => 'SIG-SECRET-ABC',
                'isCancelable' => true,
                'isTicketed' => false,
            ], 200),
        ]);

        $conn = $this->certSabreConnection();
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CANCEL_BOOKING_REQUEST_BOOKING_ID_SIGNATURE_CANCEL_ALL;

        $result = app(SabreCancelBookingInspectProbe::class)->inspectDirectPnr(
            $conn,
            'RWGWZO',
            false,
            null,
            $style,
            true,
        );

        $this->assertSame($style, $result['selected_payload_style'] ?? null);
        $shapeKeys = is_array($result['selected_payload_safe_shape_keys'] ?? null)
            ? $result['selected_payload_safe_shape_keys']
            : [];
        $this->assertContains('cancelBookingRequest', $shapeKeys);
        $this->assertTrue($result['selected_payload_has_booking_id'] ?? false);
        $this->assertTrue($result['selected_payload_has_booking_signature'] ?? false);
        $this->assertTrue($result['selected_payload_has_cancel_all'] ?? false);
        $this->assertFalse($result['selected_payload_has_cancel_data'] ?? true);
        $this->assertSame([], $result['selected_payload_null_keys'] ?? null);

        $json = json_encode($result);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('SIG-SECRET-ABC', $json);
    }

    public function test_live_send_blocked_for_dry_run_only_cancel_booking_request_style_booking(): void
    {
        Http::fake();
        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $booking = $this->sabreBookingWithPnr([], 'https://api-crt.cert.havail.sabre.test');
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CANCEL_BOOKING_REQUEST_BOOKING_ID_SIGNATURE_CANCEL_ALL;

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            '--style' => $style,
        ])
            ->expectsOutputToContain('cancel_payload_style_dry_run_only')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_live_send_blocked_for_dry_run_only_cancel_booking_request_style_booking_with_refresh(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api-crt.cert.havail.sabre.test/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'bookingSignature' => 'SIG-SECRET-ABC',
                'isCancelable' => true,
                'isTicketed' => false,
            ], 200),
        ]);
        Cache::flush();

        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $booking = $this->sabreBookingWithPnr([], 'https://api-crt.cert.havail.sabre.test');
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CANCEL_BOOKING_REQUEST_BOOKING_ID_SIGNATURE_CANCEL_ALL;

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--refresh-trip-order-context' => true,
            '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            '--style' => $style,
        ])
            ->expectsOutputToContain('cancel_payload_style_dry_run_only')
            ->assertExitCode(1);

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/v1/trip/orders/cancelBooking');
        });
    }

    public function test_direct_pnr_live_send_allows_cancel_booking_request_wrapper_when_signature_present(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api.cert.platform.sabre.com/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'bookingSignature' => 'SIG-SECRET-ABC',
                'isCancelable' => true,
                'isTicketed' => false,
            ], 200),
            'https://api.cert.platform.sabre.com/v1/trip/orders/cancelBooking' => Http::response([
                'status' => 'Cancelled',
                'errors' => [],
            ], 200),
        ]);
        Cache::flush();

        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $conn = $this->certSabreConnection();
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CANCEL_BOOKING_REQUEST_BOOKING_ID_SIGNATURE_CANCEL_ALL;

        $result = app(SabreCancelBookingInspectProbe::class)->inspectDirectPnr(
            $conn,
            'RWGWZO',
            true,
            SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            $style,
            true,
        );

        $this->assertTrue($result['live_call_attempted'] ?? false, json_encode($result));
        $this->assertSame($style, $result['selected_payload_style'] ?? null);
        $shapeKeys = is_array($result['selected_payload_safe_shape_keys'] ?? null)
            ? $result['selected_payload_safe_shape_keys']
            : [];
        $this->assertContains('cancelBookingRequest', $shapeKeys);
        $this->assertTrue($result['selected_payload_has_booking_id'] ?? false);
        $this->assertTrue($result['selected_payload_has_booking_signature'] ?? false);
        $this->assertTrue($result['selected_payload_has_cancel_all'] ?? false);
        $this->assertFalse($result['selected_payload_has_cancel_data'] ?? true);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/v1/trip/orders/cancelBooking')) {
                return false;
            }
            $body = $request->data();
            if (! is_array($body) || ! is_array($body['cancelBookingRequest'] ?? null)) {
                return false;
            }
            $inner = $body['cancelBookingRequest'];

            return ($inner['bookingId'] ?? null) === 'TRIP-BOOKING-SECRET-99'
                && ($inner['bookingSignature'] ?? null) === 'SIG-SECRET-ABC'
                && ($inner['cancelAll'] ?? null) === true;
        });

        $json = json_encode($result);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('TRIP-BOOKING-SECRET-99', $json);
        $this->assertStringNotContainsString('SIG-SECRET-ABC', $json);
        $this->assertStringNotContainsString('fake-token-for-tests-only', $json);
    }

    public function test_direct_pnr_live_send_blocks_cancel_booking_request_wrapper_when_signature_missing(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api.cert.platform.sabre.com/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'isCancelable' => true,
                'isTicketed' => false,
            ], 200),
        ]);
        Cache::flush();

        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $conn = $this->certSabreConnection();
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CANCEL_BOOKING_REQUEST_BOOKING_ID_SIGNATURE_CANCEL_ALL;

        $this->artisan('sabre:inspect-cancel-booking', [
            '--pnr' => 'RWGWZO',
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--refresh-trip-order-context' => true,
            '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
            '--style' => $style,
        ])
            ->expectsOutputToContain('trip_order_booking_signature_missing')
            ->assertExitCode(1);

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/v1/trip/orders/cancelBooking');
        });
    }

    public function test_direct_pnr_live_send_blocks_other_dry_run_wrapper_styles(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://api.cert.platform.sabre.com/v1/trip/orders/getBooking' => Http::response([
                'bookingId' => 'TRIP-BOOKING-SECRET-99',
                'bookingSignature' => 'SIG-SECRET-ABC',
                'isCancelable' => true,
                'isTicketed' => false,
            ], 200),
        ]);
        Cache::flush();

        Config::set('suppliers.sabre.cancel_enabled', true);
        Config::set('suppliers.sabre.cancel_live_call_enabled', true);

        $conn = $this->certSabreConnection();
        $blockedStyles = [
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CANCEL_BOOKING_REQUEST_BOOKING_ID_SIGNATURE_CANCEL_DATA,
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CANCELBOOKINGREQUEST_BOOKING_ID_SIGNATURE_CANCEL_ALL,
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CANCELBOOKINGRQ_BOOKING_ID_SIGNATURE_CANCEL_ALL,
        ];

        foreach ($blockedStyles as $style) {
            Http::fake([
                '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
                'https://api.cert.platform.sabre.com/v1/trip/orders/getBooking' => Http::response([
                    'bookingId' => 'TRIP-BOOKING-SECRET-99',
                    'bookingSignature' => 'SIG-SECRET-ABC',
                    'isCancelable' => true,
                    'isTicketed' => false,
                ], 200),
            ]);

            $this->artisan('sabre:inspect-cancel-booking', [
                '--pnr' => 'RWGWZO',
                '--connection' => (string) $conn->id,
                '--send' => true,
                '--refresh-trip-order-context' => true,
                '--confirm' => SabreCancelBookingInspectProbe::CONFIRM_PHRASE_CERT,
                '--style' => $style,
            ])
                ->expectsOutputToContain('cancel_payload_style_dry_run_only')
                ->assertExitCode(1);
        }

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/v1/trip/orders/cancelBooking');
        });
    }

    protected function certSabreConnection(): SupplierConnection
    {
        return SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://api.cert.platform.sabre.com',
        ]);
    }

    public function test_certified_gds_cancel_all_booking_source_candidate_shape(): void
    {
        Http::fake();

        $booking = $this->sabreBookingWithPnr();
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_BOOKING_SOURCE;

        $result = app(SabreCancelBookingInspectProbe::class)->inspect(
            $booking,
            false,
            null,
            false,
            $style,
            false,
            false,
        );

        $this->assertSame($style, $result['selected_payload_style'] ?? null);
        $this->assertFalse($result['live_call_attempted'] ?? true);

        $shapeKeys = is_array($result['selected_payload_safe_shape_keys'] ?? null)
            ? $result['selected_payload_safe_shape_keys']
            : [];
        $this->assertEqualsCanonicalizing(
            ['confirmationId', 'cancelAll', 'bookingSource', 'receivedFrom'],
            $shapeKeys,
        );
        $this->assertFalse($result['selected_payload_has_booking_signature'] ?? true);
        $this->assertFalse($result['selected_payload_has_cancel_data'] ?? true);
        $this->assertTrue($result['selected_payload_has_cancel_all'] ?? false);

        $candidates = is_array($result['candidate_payloads'] ?? null) ? $result['candidate_payloads'] : [];
        $row = collect($candidates)->firstWhere('style', $style);
        $this->assertNotNull($row);
        $this->assertFalse($row['recommended'] ?? true);
        $preview = is_array($row['request_body_redacted'] ?? null) ? $row['request_body_redacted'] : [];
        $this->assertTrue($preview['cancelAll'] ?? false);
        $this->assertArrayHasKey('bookingSource', $preview);
        $this->assertArrayHasKey('receivedFrom', $preview);
        foreach (['cancelBookingRequest', 'request', 'CancelBookingRQ', 'CancelBookingRequest'] as $wrapper) {
            $this->assertArrayNotHasKey($wrapper, $preview);
        }
        foreach (['bookingSignature', 'cancelData', 'orderId', 'orderItemIds'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $preview);
        }

        $built = app(SabreCancelPayloadBuilder::class)->buildCandidatePayloads('IJYJMV', null, null);
        $rootBuilt = collect($built)->firstWhere(
            'style',
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_ROOT,
        );
        $certBuilt = collect($built)->firstWhere('style', $style);
        $this->assertNotNull($rootBuilt);
        $this->assertNotNull($certBuilt);
        $wireBody = is_array($certBuilt['body'] ?? null) ? $certBuilt['body'] : [];
        $this->assertSame('SABRE', $wireBody['bookingSource'] ?? null);
        $this->assertSame('LW CANCEL API', $wireBody['receivedFrom'] ?? null);
        $this->assertNotSame(
            SabreCancelProbeDiagnostics::semanticBodyFingerprint($rootBuilt['body']),
            SabreCancelProbeDiagnostics::semanticBodyFingerprint($certBuilt['body']),
        );

        Http::assertNothingSent();
    }

    public function test_dry_run_artisan_explicit_certified_gds_style_does_not_send_cancel_http(): void
    {
        Http::fake();
        Config::set('suppliers.sabre.cancel_enabled', false);
        Config::set('suppliers.sabre.cancel_live_call_enabled', false);

        $booking = $this->sabreBookingWithPnr();
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_BOOKING_SOURCE;

        $this->artisan('sabre:inspect-cancel-booking', [
            '--booking' => (string) $booking->id,
            '--style' => $style,
        ])
            ->expectsOutputToContain('"selected_payload_style":"'.$style.'"')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_ticketed_booking_blocked_before_certified_gds_style_http(): void
    {
        Http::fake();

        $booking = $this->sabreBookingWithPnr([
            'status' => BookingStatus::Ticketed,
            'ticketed_at' => now(),
        ]);
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_BOOKING_SOURCE;

        $result = app(SabreCancelBookingInspectProbe::class)->inspect(
            $booking,
            false,
            null,
            false,
            $style,
            false,
            false,
        );

        $this->assertSame('booking_ticketed_blocked', $result['error'] ?? null);
        Http::assertNothingSent();
    }

    public function test_certified_gds_style_not_default_recommendation_when_booking_id_present(): void
    {
        Http::fake();

        $booking = $this->sabreBookingWithPnr();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['trip_order_cancel_context'] = [
            'bookingId' => 'CACHED-BOOKING-ID-1',
            'bookingSignature' => 'SIG-CACHED',
            'isCancelable' => true,
            'isTicketed' => false,
        ];
        $booking->meta = $meta;
        $booking->save();

        $result = app(SabreCancelBookingInspectProbe::class)->inspect($booking, false, null, false, null, true);

        $certStyle = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_BOOKING_SOURCE;
        $candidates = is_array($result['candidate_payloads'] ?? null) ? $result['candidate_payloads'] : [];
        $certRow = collect($candidates)->firstWhere('style', $certStyle);
        $this->assertNotNull($certRow);
        $this->assertFalse($certRow['recommended'] ?? true);
        $this->assertNotSame($certStyle, $result['recommended_payload_style'] ?? null);
        $this->assertSame(
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_ALL,
            $result['recommended_payload_style'] ?? null,
        );
    }

    public function test_list_styles_official_shape_audit_for_certified_gds_cancel_style(): void
    {
        Http::fake();

        $booking = $this->sabreBookingWithPnr();
        $style = SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_BOOKING_SOURCE;

        $payload = app(SabreCancelBookingInspectProbe::class)->listStyles($booking, false, false);
        $styles = is_array($payload['styles'] ?? null) ? $payload['styles'] : [];
        $row = collect($styles)->firstWhere('style', $style);
        $this->assertNotNull($row);
        $audit = is_array($row['official_shape_audit'] ?? null) ? $row['official_shape_audit'] : [];
        $this->assertSame(
            SabreCancelProbeDiagnostics::OFFICIAL_AUDIT_SABRE_CONFIRMED_GDS_FULL_CANCEL,
            $audit['label'] ?? null,
        );
        $this->assertTrue($audit['do_not_auto_recommend'] ?? false);
        $this->assertFalse($row['recommended'] ?? true);
    }

    /**
     * @param  list<string>  $codes
     * @param  list<string>  $messages
     * @param  list<string>  $paths
     */
    protected function seedCancelAttempt(
        Booking $booking,
        string $style,
        string $httpStatus,
        array $codes,
        array $messages,
        array $paths = [],
    ): void {
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'inspect_cancel_pnr',
            'status' => (int) $httpStatus >= 200 && (int) $httpStatus < 300 ? 'attempted' : 'failed',
            'safe_summary' => [
                'payload_style' => $style,
                'http_status' => $httpStatus,
                'response_error_codes' => $codes,
                'response_error_messages' => $messages,
                'response_error_paths' => $paths,
            ],
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);
    }
}
