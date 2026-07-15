<?php

namespace Tests\Unit\Services\Suppliers\Sabre;

use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Booking\BookingProviderRouter;
use App\Services\Suppliers\Sabre\Gds\SabreBookingOfferRefreshService;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Support\Bookings\ControlledStaffOfferRefreshDiagnostics;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabreOfferRefreshAcceptance;
use App\Support\Bookings\SabrePnrFailureClassifier;
use App\Support\Bookings\SabreSafeRefreshContext;
use App\Support\Bookings\SupplierBookingPreflightGuard;
use App\Support\FlightSearch\SabreOfferFreshness;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreControlledStaffPnrGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => false,
            'suppliers.sabre.certified_route_selector_public_checkout_enabled' => true,
        ]);
        Http::fake();
    }

    public function test_public_checkout_same_carrier_two_segment_still_deferred_when_public_flag_false(): void
    {
        $booking = $this->readyConnectingBooking();

        $result = app(SabreBookingService::class)->runPublicReviewDryRun($booking->fresh(['passengers', 'contact', 'fareBreakdown']));

        $this->assertFalse($result['success'] ?? true);
        $this->assertSame(SabreCertifiedRouteSelector::ERROR_CODE_PENDING, $result['error_code'] ?? null);
        $this->assertStringContainsString(
            'automatic PNR is not enabled for public checkout yet',
            (string) ($result['message'] ?? '')
        );
    }

    public function test_admin_create_supplier_booking_proceeds_when_live_action_allowed(): void
    {
        $booking = $this->readyConnectingBooking();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $result = app(SabreBookingService::class)->createSupplierBooking(
            $booking->fresh(['passengers', 'contact']),
            $admin,
            false,
            true,
        );

        $this->assertFalse($result->success);
        $this->assertSame('dry_run', $result->status);
        $this->assertStringNotContainsString(
            'automatic PNR is not enabled for public checkout yet',
            (string) ($result->error_message ?? '')
        );
        $this->assertStringNotContainsString(
            'requires staff confirmation before supplier confirmation',
            (string) ($result->error_message ?? '')
        );
        $this->assertGreaterThanOrEqual(
            1,
            SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count()
        );
    }

    public function test_admin_create_with_live_calls_and_iati_gds_skips_staff_confirmation_gate(): void
    {
        config([
            'suppliers.sabre.cpnr_iati_style_certified_gds_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.refresh_offer_before_public_pnr' => true,
        ]);

        $booking = $this->readyConnectingBooking();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $result = app(SabreBookingService::class)->createSupplierBooking(
            $booking->fresh(['passengers', 'contact']),
            $admin,
            false,
            true,
        );

        $this->assertStringNotContainsString(
            'requires staff confirmation before supplier confirmation',
            (string) ($result->error_message ?? '')
        );
        $this->assertGreaterThanOrEqual(
            1,
            SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count()
        );

        $meta = is_array($booking->fresh()->meta) ? $booking->fresh()->meta : [];
        $this->assertNotSame('', trim((string) ($meta['staff_supplier_confirmation_confirmed_at'] ?? '')));
    }

    public function test_admin_create_supplier_booking_blocked_with_admin_message_when_readiness_incomplete(): void
    {
        $booking = $this->connectingBookingMissingPricing();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $result = app(SabreBookingService::class)->createSupplierBooking(
            $booking->fresh(['passengers', 'contact']),
            $admin,
            false,
            true,
        );

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Admin/staff PNR is not allowed', (string) ($result->error_message ?? ''));
        $this->assertStringNotContainsString(
            'automatic PNR is not enabled for public checkout yet',
            (string) ($result->error_message ?? '')
        );
        $this->assertGreaterThanOrEqual(
            1,
            SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count()
        );
    }

    public function test_admin_create_mixed_carrier_remains_blocked(): void
    {
        $booking = $this->mixedCarrierConnectingBooking();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $result = app(SabreBookingService::class)->createSupplierBooking(
            $booking->fresh(['passengers', 'contact']),
            $admin,
            false,
            true,
        );

        $this->assertFalse($result->success);
        $this->assertSame(SabreCertifiedRouteSelector::ERROR_CODE_NOT_CERTIFIED, $result->error_code);
        $this->assertStringContainsString('Mixed or interline', (string) ($result->error_message ?? ''));
    }

    public function test_ticketing_remains_disabled(): void
    {
        $this->assertFalse((bool) config('suppliers.sabre.ticketing_enabled'));
        $this->assertFalse(app(SabreBookingService::class)->isTicketingEnabled());
    }

    public function test_defer_meta_blocks_preflight_without_controlled_staff_flag(): void
    {
        $booking = $this->bookingWithDeferMeta($this->readyConnectingBooking());
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $result = app(SupplierBookingPreflightGuard::class)->preflightAutomatedCreate(
            $booking->fresh(['passengers', 'contact']),
            $admin,
            'admin',
        );

        $this->assertNotNull($result);
        $this->assertSame('defer_supplier_booking_to_manual_review', $result->error_code);
    }

    public function test_defer_meta_allows_admin_controlled_preflight_when_live_action_allowed(): void
    {
        $booking = $this->bookingWithDeferMeta($this->readyConnectingBooking());
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $result = app(SupplierBookingPreflightGuard::class)->preflightAutomatedCreate(
            $booking->fresh(['passengers', 'contact']),
            $admin,
            'admin',
            false,
            true,
        );

        $this->assertNull($result);
    }

    public function test_defer_meta_admin_controlled_preflight_blocks_initial_create_when_safe_context_missing(): void
    {
        $booking = $this->bookingWithDeferMeta($this->readyConnectingBooking(), false);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $result = app(SupplierBookingPreflightGuard::class)->preflightAutomatedCreate(
            $booking->fresh(['passengers', 'contact']),
            $admin,
            'admin',
            false,
            true,
        );

        $this->assertNotNull($result);
        $this->assertSame('controlled_initial_create_safe_refresh_context_missing', $result->error_code);
    }

    public function test_defer_meta_admin_controlled_create_proceeds_past_defer_guard(): void
    {
        $booking = $this->bookingWithDeferMeta($this->readyConnectingBooking());
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $result = app(SabreBookingService::class)->createSupplierBooking(
            $booking->fresh(['passengers', 'contact']),
            $admin,
            false,
            true,
        );

        $this->assertNotSame('defer_supplier_booking_to_manual_review', $result->error_code);
        $this->assertSame('dry_run', $result->status);
        $meta = is_array($booking->fresh()->meta) ? $booking->fresh()->meta : [];
        $this->assertTrue((bool) ($meta['defer_supplier_booking_to_manual_review'] ?? false));
        $this->assertNotSame('', trim((string) ($meta['staff_supplier_confirmation_confirmed_at'] ?? '')));
    }

    public function test_defer_meta_admin_controlled_blocked_when_readiness_incomplete(): void
    {
        $booking = $this->bookingWithDeferMeta($this->connectingBookingMissingPricing());
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $result = app(SabreBookingService::class)->createSupplierBooking(
            $booking->fresh(['passengers', 'contact']),
            $admin,
            false,
            true,
        );

        $this->assertFalse($result->success);
        $this->assertSame('defer_supplier_booking_to_manual_review', $result->error_code);
    }

    public function test_staff_controlled_create_passes_staff_supplier_action_source(): void
    {
        $booking = $this->bookingWithDeferMeta($this->readyConnectingBooking());
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $result = app(BookingProviderRouter::class)->createSupplierBooking(
            $booking->fresh(['passengers', 'contact']),
            $staff,
            false,
            allowControlledStaffPnr: true,
        );

        $this->assertNotSame('defer_supplier_booking_to_manual_review', $result->error_code);
        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($attempt);
        $this->assertNotSame('blocked', strtolower((string) $attempt->status));
    }

    public function test_defer_meta_blocks_duffel_even_with_controlled_staff_flag(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'duffel')
            ->firstOrFail();
        $conn->update(['is_active' => true, 'status' => SupplierConnectionStatus::Active]);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => 'duffel',
            'meta' => [
                'validated_offer_snapshot' => ['offer_id' => 'offer-defer-duffel'],
                'supplier_provider' => 'duffel',
                'supplier_connection_id' => $conn->id,
                'defer_supplier_booking_to_manual_review' => true,
            ],
        ]);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $result = app(SupplierBookingPreflightGuard::class)->preflightAutomatedCreate(
            $booking->fresh(),
            $admin,
            'admin',
            false,
            true,
        );

        $this->assertNotNull($result);
        $this->assertSame('defer_supplier_booking_to_manual_review', $result->error_code);
    }

    public function test_controlled_staff_stale_offer_blocks_with_offer_validation_required(): void
    {
        $booking = $this->readyConnectingBooking();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['offer_validated_at'] = now()->subHours(2)->toIso8601String();
        unset($meta['offer_refresh_status']);
        $booking->forceFill(['meta' => $meta])->save();

        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $result = app(SabreBookingService::class)->createSupplierBooking(
            $booking->fresh(['passengers', 'contact']),
            $admin,
            false,
            true,
        );

        $this->assertFalse($result->success);
        $this->assertSame('manual_review', $result->status);
        $this->assertSame('offer_validation_required', $result->safe_summary['reason'] ?? null);
        $this->assertContains(
            (string) ($result->safe_summary['reason_code'] ?? $result->error_code ?? ''),
            ['offer_stale_before_checkout', 'offer_refresh_unavailable', 'selected_offer_revalidation_required', 'selected_offer_revalidation_failed']
        );

        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($attempt);
        $this->assertSame('needs_review', strtolower((string) $attempt->status));
        $safe = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertSame('offer_validation_required', $safe['reason'] ?? null);
        $this->assertFalse((bool) ($safe['live_call_attempted'] ?? true));
        $this->assertArrayNotHasKey('response_payload', $safe);
    }

    public function test_controlled_staff_fresh_offer_context_proceeds_past_offer_validation_gate(): void
    {
        $booking = $this->readyConnectingBooking();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta = app(SabreOfferFreshness::class)->stampBookingMetaAfterSuccessfulOfferRefresh($meta);
        $booking->forceFill(['meta' => $meta])->save();

        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $result = app(SabreBookingService::class)->createSupplierBooking(
            $booking->fresh(['passengers', 'contact']),
            $admin,
            false,
            true,
        );

        $this->assertNotSame('offer_validation_required', $result->safe_summary['reason'] ?? null);
        $this->assertSame('dry_run', $result->status);
    }

    public function test_stamp_booking_meta_after_refresh_updates_all_freshness_keys(): void
    {
        $stamped = app(SabreOfferFreshness::class)->stampBookingMetaAfterSuccessfulOfferRefresh([]);

        foreach ([
            'offer_validated_at',
            'validated_at',
            'selected_offer_last_revalidated_at',
            'last_revalidated_at',
            'selected_offer_revalidation_status',
            'revalidation_status',
            'offer_refresh_status',
            'offer_refresh_refreshed_at',
        ] as $key) {
            $this->assertNotSame('', trim((string) ($stamped[$key] ?? '')));
        }

        $this->assertSame('success', $stamped['revalidation_status']);
        $this->assertSame('refreshed', $stamped['offer_refresh_status']);
    }

    public function test_stale_refreshed_status_forces_controlled_admin_refresh_before_pnr(): void
    {
        $booking = $this->readyConnectingBooking();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['offer_validated_at'] = now()->subHours(2)->toIso8601String();
        $meta['offer_refresh_status'] = 'refreshed';
        unset(
            $meta['last_revalidated_at'],
            $meta['selected_offer_last_revalidated_at'],
            $meta['revalidation_status'],
            $meta['selected_offer_revalidation_status'],
        );
        $booking->forceFill(['meta' => $meta])->save();

        $this->mock(SabreBookingOfferRefreshService::class, function ($mock) use ($booking): void {
            $mock->shouldReceive('refresh')
                ->once()
                ->withArgs(fn (Booking $b, bool $apply): bool => $b->id === $booking->id && $apply === true)
                ->andReturnUsing(function (Booking $b): array {
                    $meta = is_array($b->meta) ? $b->meta : [];
                    $meta = app(SabreOfferFreshness::class)->stampBookingMetaAfterSuccessfulOfferRefresh($meta);
                    $b->forceFill(['meta' => $meta])->save();

                    return [
                        'match_found' => true,
                        'price_changed' => false,
                        'applied' => true,
                        'error' => '',
                    ];
                });
        });

        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $result = app(SabreBookingService::class)->createSupplierBooking(
            $booking->fresh(['passengers', 'contact']),
            $admin,
            false,
            true,
        );

        $this->assertNotSame('sabre_offer_validation_failed', $result->error_code);
        $this->assertSame('dry_run', $result->status);
    }

    public function test_controlled_staff_refresh_failure_blocks_without_pnr(): void
    {
        $booking = $this->readyConnectingBooking();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['offer_validated_at'] = now()->subHours(2)->toIso8601String();
        unset($meta['offer_refresh_status']);
        $booking->forceFill(['meta' => $meta])->save();

        $this->mock(SabreBookingOfferRefreshService::class, function ($mock): void {
            $mock->shouldReceive('refresh')
                ->once()
                ->andReturn([
                    'match_found' => false,
                    'price_changed' => false,
                    'applied' => false,
                    'error' => '',
                ]);
        });

        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $result = app(SabreBookingService::class)->createSupplierBooking(
            $booking->fresh(['passengers', 'contact']),
            $admin,
            false,
            true,
        );

        $this->assertFalse($result->success);
        $this->assertSame('manual_review', $result->status);
        $this->assertSame('offer_refresh_unavailable', $result->error_code);
        $this->assertNull($booking->fresh()->pnr);

        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->orderByDesc('id')
            ->first();
        $safe = is_array($attempt?->safe_summary) ? $attempt->safe_summary : [];
        $this->assertTrue($safe['refresh_attempted'] ?? false);
        $this->assertSame(
            ControlledStaffOfferRefreshDiagnostics::ACTION_FRESH_SEARCH,
            $safe['recommended_staff_action'] ?? null,
        );
        $this->assertArrayNotHasKey('fresh_offer', $safe);
        $this->assertArrayNotHasKey('response_payload', $safe);
    }

    public function test_controlled_staff_refresh_exception_stores_transient_diagnostics(): void
    {
        $booking = $this->readyConnectingBooking();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['offer_validated_at'] = now()->subHours(2)->toIso8601String();
        unset($meta['offer_refresh_status']);
        $booking->forceFill(['meta' => $meta])->save();

        $this->mock(SabreBookingOfferRefreshService::class, function ($mock): void {
            $mock->shouldReceive('refresh')
                ->once()
                ->andThrow(new \RuntimeException('simulated supplier search failure'));
        });

        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $result = app(SabreBookingService::class)->createSupplierBooking(
            $booking->fresh(['passengers', 'contact']),
            $admin,
            false,
            true,
        );

        $this->assertFalse($result->success);
        $this->assertSame('offer_refresh_failed', $result->error_code);
        $this->assertNull($booking->fresh()->pnr);

        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->orderByDesc('id')
            ->first();
        $safe = is_array($attempt?->safe_summary) ? $attempt->safe_summary : [];
        $this->assertTrue($safe['refresh_attempted'] ?? false);
        $this->assertSame('exception', $safe['refresh_status'] ?? null);
        $this->assertSame('refresh_exception', $safe['refresh_reason_code'] ?? null);
        $this->assertSame('RuntimeException', $safe['refresh_exception_class'] ?? null);
        $this->assertSame('refresh_exception', $safe['refresh_stage'] ?? null);
        $this->assertSame(
            ControlledStaffOfferRefreshDiagnostics::ACTION_RETRY_AFTER_COOLDOWN,
            $safe['recommended_staff_action'] ?? null,
        );
        $this->assertTrue(
            SabrePnrFailureClassifier::isControlledStaffOfferValidationRetryable('offer_refresh_failed', $safe),
        );
    }

    public function test_sabre_offer_validation_failed_is_retryable_for_controlled_staff_classifier(): void
    {
        $result = SabrePnrFailureClassifier::classify('sabre_offer_validation_failed', [
            'create_status' => 'validation_failed',
            'source' => 'sabre_booking_service',
        ]);

        $this->assertTrue($result['retry_allowed']);
        $this->assertSame(
            SabrePnrFailureClassifier::CLASSIFICATION_OFFER_FRESHNESS_RETRYABLE,
            $result['classification'],
        );
    }

    public function test_controlled_staff_stale_offer_without_refresh_mock_blocks_before_live_pnr(): void
    {
        config(['suppliers.sabre.refresh_offer_before_public_pnr' => false]);

        $booking = $this->readyConnectingBooking();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['offer_validated_at'] = now()->subHours(2)->toIso8601String();
        $meta['offer_refresh_status'] = 'refreshed';
        $booking->forceFill(['meta' => $meta])->save();

        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $result = app(SabreBookingService::class)->createSupplierBooking(
            $booking->fresh(['passengers', 'contact']),
            $admin,
            false,
            true,
        );

        $this->assertFalse($result->success);
        $this->assertSame('manual_review', $result->status);
        $this->assertSame('offer_validation_required', $result->safe_summary['reason'] ?? null);
        $this->assertNull($booking->fresh()->pnr);

        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($attempt);
        $safe = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertArrayNotHasKey('response_payload', $safe);
    }

    public function test_controlled_staff_blocks_when_fare_refresh_requires_acceptance(): void
    {
        $booking = $this->readyConnectingBooking();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta[SabreOfferRefreshAcceptance::META_REQUIRES_CONFIRMATION] = true;
        $meta[SabreOfferRefreshAcceptance::META_ACCEPTED] = false;
        $meta[SabreOfferRefreshAcceptance::META_PRICE_CHANGED] = true;
        $booking->forceFill(['meta' => $meta])->save();

        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $result = app(SabreBookingService::class)->createSupplierBooking(
            $booking->fresh(['passengers', 'contact']),
            $admin,
            false,
            true,
        );

        $this->assertFalse($result->success);
        $this->assertSame('manual_review', $result->status);
        $this->assertSame('offer_validation_required', $result->safe_summary['reason'] ?? null);
        $this->assertSame(
            SabreOfferRefreshAcceptance::ADMIN_MESSAGE,
            (string) ($result->error_message ?? '')
        );
    }

    public function test_direct_one_segment_create_booking_without_staff_flag_unchanged(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $conn->update(['is_active' => true, 'status' => SupplierConnectionStatus::Active]);

        $snapshot = [
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $conn->id,
            'offer_id' => 'off-direct',
            'segments' => [
                [
                    'origin' => 'KHI',
                    'destination' => 'DXB',
                    'carrier' => 'PK',
                    'booking_class' => 'Y',
                    'fare_basis_code' => 'YLOW',
                    'departure_at' => '2026-06-20T10:00:00Z',
                    'arrival_at' => '2026-06-20T14:00:00Z',
                ],
            ],
            'validating_carrier' => 'PK',
            'fare_breakdown' => [
                'supplier_total' => 100.0,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];

        $result = app(SabreBookingService::class)->createBooking(
            $snapshot,
            [
                'contact' => ['email' => 'lead@example.com', 'phone' => '+923001234567'],
                'passengers' => [['passenger_type' => 'adult', 'first_name' => 'Test', 'last_name' => 'User']],
            ],
            null,
            ['allow_controlled_staff_pnr' => false],
        );

        $this->assertTrue($result['success'] ?? false);
        $this->assertSame('dry_run', $result['status'] ?? null);
    }

    protected function bookingWithDeferMeta(Booking $booking, bool $withSafeRefreshContext = true): Booking
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['defer_supplier_booking_to_manual_review'] = true;
        $meta['supplier_pnr_deferred_reason'] = SabreCertifiedRouteSelector::DEFER_REASON;
        if ($withSafeRefreshContext) {
            $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
            $meta[SabreSafeRefreshContext::META_KEY] = app(SabreSafeRefreshContext::class)->buildFromCheckout($snapshot, [
                'trip_type' => 'one_way',
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-06-20',
                'adults' => 1,
            ], [
                'checkout_search_id' => 'controlled-staff-safe-context',
                'checkout_offer_id' => 'off-conn',
                'supplier_total' => 100.0,
                'supplier_currency' => 'PKR',
            ]);
        } else {
            unset($meta[SabreSafeRefreshContext::META_KEY]);
        }
        $booking->forceFill(['meta' => $meta])->save();

        return $booking->fresh();
    }

    protected function readyConnectingBooking(): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $conn->update(['is_active' => true, 'status' => SupplierConnectionStatus::Active]);

        $segments = [
            $this->segmentRow('LHE', 'JED', 'SV', '739', 'Q', 'QCLASS01', '2026-06-20T08:00:00Z', '2026-06-20T12:00:00Z'),
            $this->segmentRow('JED', 'DXB', 'SV', '568', 'Q', 'QCLASS02', '2026-06-20T14:00:00Z', '2026-06-20T18:00:00Z'),
        ];
        $snapshot = [
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $conn->id,
            'offer_id' => 'off-conn',
            'supplier_offer_id' => 'off-conn',
            'segments' => $segments,
            'validating_carrier' => 'SV',
            'raw_payload' => [
                'distribution_channel' => 'GDS',
                'itinerary_reference' => '2',
                'sabre_shop_context' => [
                    'distribution_channel' => 'GDS',
                    'itinerary_ref' => '2',
                    'pricing_information_index' => 0,
                    'validating_carrier' => 'SV',
                    'leg_refs' => [1],
                    'schedule_refs' => [1, 2],
                    'fare_basis_codes' => ['QCLASS01', 'QCLASS02'],
                ],
                'sabre_booking_context' => [
                    'itinerary_reference' => '2',
                    'pricing_information_index' => 0,
                    'booking_classes_by_segment' => ['Q', 'Q'],
                    'fare_basis_codes_by_segment' => ['QCLASS01', 'QCLASS02'],
                    'segment_slice_count' => 2,
                ],
            ],
            'fare_breakdown' => [
                'supplier_total' => 100.0,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => 'sabre',
            'meta' => [
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => $conn->id,
                'search_criteria' => ['trip_type' => 'one_way'],
                'offer_validation_status' => 'valid',
                'offer_validated_at' => now()->toIso8601String(),
                'offer_refresh_status' => 'refreshed',
                'normalized_offer_snapshot' => $snapshot,
                'validated_offer_snapshot' => $snapshot,
                'sabre_booking_context' => $snapshot['raw_payload']['sabre_booking_context'],
            ],
        ]);

        BookingPassenger::factory()->for($booking)->create([
            'passenger_index' => 0,
            'is_lead_passenger' => true,
            'first_name' => 'Test',
            'last_name' => 'User',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'gender' => 'male',
            'passenger_type' => 'adult',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'test@example.com',
            'phone' => '+923001234567',
        ]);

        return $booking;
    }

    protected function connectingBookingMissingPricing(): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();

        $segments = [
            $this->segmentRow('LHE', 'JED', 'SV', '739', 'Q', 'QCLASS01', '2026-06-20T08:00:00Z', '2026-06-20T12:00:00Z'),
            $this->segmentRow('JED', 'DXB', 'SV', '568', 'Q', 'QCLASS02', '2026-06-20T14:00:00Z', '2026-06-20T18:00:00Z'),
        ];
        $snapshot = [
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $conn->id,
            'offer_id' => 'off-incomplete',
            'supplier_offer_id' => 'off-incomplete',
            'segments' => $segments,
            'validating_carrier' => 'SV',
            'fare_breakdown' => [
                'supplier_total' => 100.0,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => 'sabre',
            'meta' => [
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => $conn->id,
                'search_criteria' => ['trip_type' => 'one_way'],
                'offer_validation_status' => 'valid',
                'normalized_offer_snapshot' => $snapshot,
            ],
        ]);

        BookingPassenger::factory()->for($booking)->create([
            'passenger_index' => 0,
            'is_lead_passenger' => true,
            'first_name' => 'Test',
            'last_name' => 'User',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'gender' => 'male',
            'passenger_type' => 'adult',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'test@example.com',
            'phone' => '+923001234567',
        ]);

        return $booking;
    }

    protected function mixedCarrierConnectingBooking(): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();

        $segments = [
            $this->segmentRow('LHE', 'JED', 'SV', '739', 'Q', 'QCLASS01', '2026-06-20T08:00:00Z', '2026-06-20T12:00:00Z'),
            $this->segmentRow('JED', 'DXB', 'PK', '568', 'Q', 'QCLASS02', '2026-06-20T14:00:00Z', '2026-06-20T18:00:00Z'),
        ];
        $snapshot = [
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $conn->id,
            'offer_id' => 'off-mixed',
            'supplier_offer_id' => 'off-mixed',
            'segments' => $segments,
            'validating_carrier' => 'SV',
            'fare_breakdown' => [
                'supplier_total' => 100.0,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => 'sabre',
            'meta' => [
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => $conn->id,
                'search_criteria' => ['trip_type' => 'one_way'],
                'offer_validation_status' => 'valid',
                'normalized_offer_snapshot' => $snapshot,
            ],
        ]);

        BookingPassenger::factory()->for($booking)->create([
            'passenger_index' => 0,
            'is_lead_passenger' => true,
            'first_name' => 'Test',
            'last_name' => 'User',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'gender' => 'male',
            'passenger_type' => 'adult',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'test@example.com',
            'phone' => '+923001234567',
        ]);

        return $booking;
    }

    /**
     * @return array<string, mixed>
     */
    protected function segmentRow(
        string $origin,
        string $destination,
        string $carrier,
        string $flight,
        string $bookingClass,
        string $fareBasis = '',
        string $departureAt = '2026-06-20T10:00:00Z',
        string $arrivalAt = '2026-06-20T14:00:00Z',
    ): array {
        return array_filter([
            'origin' => $origin,
            'destination' => $destination,
            'carrier' => $carrier,
            'flight_number' => $flight,
            'booking_class' => $bookingClass,
            'departure_at' => $departureAt,
            'arrival_at' => $arrivalAt,
            'fare_basis_code' => $fareBasis !== '' ? $fareBasis : null,
        ], static fn ($v) => $v !== null);
    }
}
