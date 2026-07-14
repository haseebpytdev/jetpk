<?php

namespace Tests\Feature;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPassenger;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Support\PublicBooking;
use App\Support\Bookings\PublicCheckoutFareChangeState;
use App\Support\Bookings\SabreHostErrorClassifier;
use App\Support\Bookings\SabreOfferRefreshAcceptance;
use App\Support\Sabre\GdsPnrCreate\SabreConnectingBrandedFarePublicAutoCertification;
use App\Support\Sabre\GdsPnrCreate\SabreGdsOneWayTripShapeClassifier;
use App\Support\Sabre\GdsPnrCreate\SabreGdsAutoPnrContextCompletionService;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategyRegistry;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategySelector;
use App\Enums\SupplierConnectionStatus;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicCheckoutPnrStrategyAndFareChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->configureSabreCheckout();
        Http::fake();
    }

    public function test_direct_pk_selector_prefers_iati_over_traditional_and_v25(): void
    {
        $booking = $this->makeFreedomPkBooking();
        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForBooking($booking);

        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $selection['selected_strategy'],
        );
        $this->assertSame(
            SabreGdsPnrCreateStrategySelector::REASON_KNOWN_GOOD,
            $selection['selection_reason'],
        );
        $this->assertContains(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $selection['eligible_strategies'],
        );
        $this->assertContains(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
            $selection['blocked_strategies'],
        );
        $this->assertContains(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            $selection['blocked_strategies'],
        );
        $this->assertSame(
            SabreGdsPnrCreateStrategySelector::V25_NOT_SELECTED_AUTOMATIC_DISABLED,
            $selection['passenger_records_v2_5_gds_not_selected_reason'],
        );
        $this->assertSame(
            SabreGdsPnrCreateStrategySelector::TRADITIONAL_NOT_SELECTED_MIXED_SUCCESS,
            $selection['traditional_not_selected_reason'],
        );
        $this->assertTrue($selection['fallback_available']);
    }

    public function test_public_checkout_dry_run_uses_registry_iati_strategy(): void
    {
        Config::set('suppliers.sabre.booking_enabled', true);
        Config::set('suppliers.sabre.booking_live_call_enabled', false);
        Config::set('suppliers.sabre.pnr_create_enabled', true);

        $booking = $this->makeFreedomPkBooking();
        $result = app(SabreBookingService::class)->runPublicReviewDryRun($booking->fresh(['passengers', 'contact', 'fareBreakdown']));

        $this->assertFalse($result['live_call_attempted'] ?? true);
        $payloadSummary = is_array($result['create_payload_safe_summary'] ?? null)
            ? $result['create_payload_safe_summary']
            : [];
        $style = (string) (
            $payloadSummary['selected_payload_style']
            ?? $payloadSummary['create_payload_style']
            ?? $payloadSummary['payload_schema']
            ?? ''
        );
        $this->assertSame(SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS, $style);
        $endpoint = (string) ($payloadSummary['endpoint_path'] ?? $payloadSummary['create_endpoint_path'] ?? '');
        $this->assertStringContainsString('/v2.4.0/passenger/records', $endpoint);

        $meta = is_array($booking->fresh()->meta) ? $booking->fresh()->meta : [];
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            data_get($meta, 'public_checkout_pnr_strategy.selected_strategy'),
        );
    }

    public function test_public_checkout_does_not_create_multiple_live_strategy_attempts(): void
    {
        Config::set('suppliers.sabre.booking_enabled', true);
        Config::set('suppliers.sabre.booking_live_call_enabled', true);
        Config::set('suppliers.sabre.pnr_create_enabled', true);

        $booking = $this->makeFreedomPkBooking();
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'attempted_at' => now(),
            'completed_at' => now(),
            'safe_summary' => [
                'payload_schema' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
                'live_call_attempted' => true,
            ],
        ]);

        $before = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count();
        app(SabreBookingService::class)->runPublicReviewDryRun($booking->fresh(['passengers', 'contact', 'fareBreakdown', 'supplierBookings']));
        $after = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count();

        $this->assertSame($before + 1, $after);
        $latest = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $safe = is_array($latest?->safe_summary) ? $latest->safe_summary : [];
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            (string) ($safe['payload_schema'] ?? $safe['selected_payload_style'] ?? ''),
        );
    }

    public function test_booking_83_style_does_not_show_persisted_fare_change(): void
    {
        $booking = $this->makeFreedomPkBooking([
            'selected_fare_total' => 88602,
            'revalidated_fare_total' => 88602,
            'meta' => array_merge($this->freedomMeta(), [
                'requires_price_change_confirmation' => true,
                'price_change_old_total' => 88602,
                'price_change_new_total' => 78871,
            ]),
        ]);

        $state = app(PublicCheckoutFareChangeState::class);
        $this->assertFalse($state->persistedFareChanged($booking));
        $this->assertNull($state->customerModalDisplay($booking));
        $this->assertFalse($state->requiresCustomerAcceptance($booking));
    }

    public function test_fare_change_modal_only_when_persisted_fare_changed_true(): void
    {
        $booking = $this->makeFreedomPkBooking([
            'meta' => array_merge($this->freedomMeta(), [
                PublicCheckoutFareChangeState::META_FARE_CHANGE => [
                    'fare_changed' => true,
                    'old_total' => 88602,
                    'new_total' => 78871,
                    'difference' => -9731,
                    'currency' => 'PKR',
                ],
            ]),
        ]);

        $state = app(PublicCheckoutFareChangeState::class);
        $this->assertTrue($state->persistedFareChanged($booking));
        $display = $state->customerModalDisplay($booking);
        $this->assertIsArray($display);
        $this->assertSame(88602.0, $display['old_total']);
        $this->assertSame(78871.0, $display['new_total']);
        $this->assertTrue($state->requiresCustomerAcceptance($booking));
    }

    public function test_reconcile_clears_stale_requires_price_change_when_totals_match(): void
    {
        $booking = $this->makeFreedomPkBooking([
            'meta' => array_merge($this->freedomMeta(), [
                'requires_price_change_confirmation' => true,
                'price_change_old_total' => 88602,
                'price_change_new_total' => 78871,
            ]),
        ]);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $outcome = app(PublicCheckoutFareChangeState::class)->reconcileAfterRevalidation(
            $booking,
            $meta,
            88602,
            88602,
            true,
        );

        $this->assertFalse($outcome['fare_changed']);
        $this->assertArrayNotHasKey('requires_price_change_confirmation', $meta);
        $this->assertArrayNotHasKey(PublicCheckoutFareChangeState::META_FARE_CHANGE, $meta);
    }

    public function test_pnr_create_disabled_stores_explicit_block_reason_without_live_call(): void
    {
        Config::set('suppliers.sabre.pnr_create_enabled', false);
        Config::set('suppliers.sabre.booking_enabled', true);
        Config::set('suppliers.sabre.booking_live_call_enabled', true);

        $booking = $this->makeFreedomPkBooking();
        $result = app(SabreBookingService::class)->runPublicReviewDryRun($booking->fresh(['passengers', 'contact', 'fareBreakdown']));

        $this->assertFalse($result['live_call_attempted'] ?? true);
        $this->assertSame('pnr_create_disabled', $result['reason_code'] ?? null);
        $meta = is_array($booking->fresh()->meta) ? $booking->fresh()->meta : [];
        $this->assertSame('pnr_create_disabled', data_get($meta, 'sabre_checkout_outcome.pnr_block_reason_code'));
    }

    public function test_checkout_diagnostics_include_fare_and_pnr_strategy_fields(): void
    {
        $booking = $this->makeFreedomPkBooking();
        $diag = app(PublicCheckoutFareChangeState::class)->checkoutDiagnostics($booking, [
            'selected_strategy' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
        ]);

        $this->assertFalse($diag['fare_changed']);
        $this->assertFalse($diag['fare_change_present']);
        $this->assertSame(88602.0, $diag['selected_fare_total']);
        $this->assertSame(88602.0, $diag['revalidated_fare_total']);
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
            $diag['pnr_strategy_selected'],
        );
    }

    public function test_offer_refresh_acceptance_still_blocks_until_accepted(): void
    {
        $booking = $this->makeFreedomPkBooking([
            'meta' => array_merge($this->freedomMeta(), [
                SabreOfferRefreshAcceptance::META_REQUIRES_CONFIRMATION => true,
                SabreOfferRefreshAcceptance::META_PRICE_CHANGED => true,
                SabreOfferRefreshAcceptance::META_OLD_CUSTOMER_TOTAL => 88602,
                SabreOfferRefreshAcceptance::META_NEW_CUSTOMER_TOTAL => 78871,
                SabreOfferRefreshAcceptance::META_CUSTOMER_PRICE_DELTA => -9731,
            ]),
        ]);

        $this->assertTrue(app(PublicCheckoutFareChangeState::class)->requiresCustomerAcceptance($booking));
    }

    public function test_matching_totals_without_fare_change_do_not_block_confirmation_mismatch(): void
    {
        $booking = $this->makeFreedomPkBooking([
            'selected_fare_total' => 84912,
            'revalidated_fare_total' => 84912,
            'fare_change_accepted_at' => now()->subHour(),
            'meta' => array_merge($this->freedomMeta(), [
                PublicCheckoutFareChangeState::META_ACCEPTED_FARE_TOTAL => 80000,
                PublicCheckoutFareChangeState::META_ACCEPTED_FARE_CONTEXT_HASH => 'stale-hash',
            ]),
        ]);

        $state = app(PublicCheckoutFareChangeState::class);
        $state->synchronizeAcceptanceOnReview($booking);
        $booking->refresh();

        $this->assertFalse($state->persistedFareChanged($booking));
        $this->assertFalse($state->requiresCustomerAcceptance($booking));
        $this->assertFalse($state->confirmationTotalMismatchBlocksSubmit($booking));
        $this->assertNull($booking->fare_change_accepted_at);
    }

    public function test_booking_85_style_ydeluxe_review_does_not_block_without_fare_change(): void
    {
        $booking = $this->makeBrandedCheckoutBooking([
            'selected_fare_total' => 84912,
            'revalidated_fare_total' => 84912,
            'brand_code' => 'YDELUXE',
            'fare_basis' => 'EN900F6R/E',
            'fare_breakdown_total' => 84912,
        ]);

        $state = app(PublicCheckoutFareChangeState::class);
        $state->synchronizeAcceptanceOnReview($booking);
        $booking->refresh();

        $this->assertFalse($state->confirmationTotalMismatchBlocksSubmit($booking));
        $this->assertFalse($state->requiresCustomerAcceptance($booking));
        $this->assertNull($state->customerModalDisplay($booking));
    }

    public function test_booking_84_style_ecomfort_review_does_not_block_without_fare_change(): void
    {
        $booking = $this->makeBrandedCheckoutBooking([
            'selected_fare_total' => 290770,
            'revalidated_fare_total' => 290770,
            'brand_code' => 'ECOMFORT',
            'fare_basis' => 'HJR4R1FI/H',
            'fare_breakdown_total' => 290770,
        ]);

        $state = app(PublicCheckoutFareChangeState::class);
        $state->synchronizeAcceptanceOnReview($booking);
        $booking->refresh();

        $this->assertFalse($state->confirmationTotalMismatchBlocksSubmit($booking));
        $this->assertFalse($state->requiresCustomerAcceptance($booking));
    }

    public function test_stale_accepted_context_hash_is_discarded_on_review(): void
    {
        $booking = $this->makeBrandedCheckoutBooking([
            'selected_fare_total' => 84912,
            'revalidated_fare_total' => 84912,
            'brand_code' => 'YDELUXE',
            'fare_basis' => 'EN900F6R/E',
            'fare_breakdown_total' => 84912,
            'fare_change_accepted_at' => now()->subDay(),
        ]);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta[PublicCheckoutFareChangeState::META_ACCEPTED_FARE_CONTEXT_HASH] = 'deadbeef';
        $meta[PublicCheckoutFareChangeState::META_ACCEPTED_FARE_TOTAL] = 84912;
        $booking->forceFill(['meta' => $meta])->save();

        $state = app(PublicCheckoutFareChangeState::class);
        $state->synchronizeAcceptanceOnReview($booking->fresh(['passengers', 'fareBreakdown']));
        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];

        $this->assertNull($booking->fare_change_accepted_at);
        $this->assertArrayNotHasKey(PublicCheckoutFareChangeState::META_ACCEPTED_FARE_CONTEXT_HASH, $meta);
        $this->assertFalse($state->confirmationTotalMismatchBlocksSubmit($booking));
    }

    public function test_active_fare_changed_still_requires_acceptance(): void
    {
        $booking = $this->makeFreedomPkBooking([
            'meta' => array_merge($this->freedomMeta(), [
                PublicCheckoutFareChangeState::META_FARE_CHANGE => [
                    'fare_changed' => true,
                    'old_total' => 88602,
                    'new_total' => 78871,
                    'difference' => -9731,
                    'currency' => 'PKR',
                ],
            ]),
        ]);

        $state = app(PublicCheckoutFareChangeState::class);
        $this->assertTrue($state->requiresCustomerAcceptance($booking));
        $this->assertNotNull($state->customerModalDisplay($booking));
        $this->assertFalse($state->confirmationTotalMismatchBlocksSubmit($booking));
    }

    public function test_accepted_fare_must_match_confirmation_display_when_fare_changed(): void
    {
        $booking = $this->makeFreedomPkBooking([
            'selected_fare_total' => 88602,
            'revalidated_fare_total' => 78871,
            'meta' => array_merge($this->freedomMeta(), [
                PublicCheckoutFareChangeState::META_FARE_CHANGE => [
                    'fare_changed' => true,
                    'old_total' => 88602,
                    'new_total' => 78871,
                    'difference' => -9731,
                    'currency' => 'PKR',
                ],
            ]),
        ]);
        $booking->fareBreakdown?->forceFill(['total' => 78871])->save();

        $state = app(PublicCheckoutFareChangeState::class);
        $state->recordCustomerAcceptance($booking->fresh(['passengers', 'fareBreakdown']));
        $booking->refresh();

        $this->assertFalse($state->confirmationTotalMismatchBlocksSubmit($booking));

        $booking->fareBreakdown?->forceFill(['total' => 88602])->save();
        $booking->refresh();

        $this->assertTrue($state->confirmationTotalMismatchBlocksSubmit($booking));
    }

    public function test_changing_brand_resets_acceptance_context(): void
    {
        $booking = $this->makeBrandedCheckoutBooking([
            'selected_fare_total' => 84912,
            'revalidated_fare_total' => 84912,
            'brand_code' => 'YDELUXE',
            'fare_basis' => 'EN900F6R/E',
            'fare_breakdown_total' => 84912,
            'meta' => array_merge($this->freedomMeta(), [
                PublicCheckoutFareChangeState::META_FARE_CHANGE => [
                    'fare_changed' => true,
                    'old_total' => 90000,
                    'new_total' => 84912,
                    'difference' => -5088,
                    'currency' => 'PKR',
                ],
            ]),
        ]);

        $state = app(PublicCheckoutFareChangeState::class);
        $state->recordCustomerAcceptance($booking->fresh(['passengers', 'fareBreakdown']));
        $booking->refresh();
        $hashBefore = data_get($booking->meta, PublicCheckoutFareChangeState::META_ACCEPTED_FARE_CONTEXT_HASH);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['selected_fare_family_option']['brand_code'] = 'ECOMFORT';
        $meta['sabre_booking_context']['selected_brand_code'] = 'ECOMFORT';
        $booking->forceFill(['meta' => $meta])->save();

        $state->synchronizeAcceptanceOnReview($booking->fresh(['passengers', 'fareBreakdown']));
        $booking->refresh();

        $this->assertNull($booking->fare_change_accepted_at);
        $this->assertNotSame($hashBefore, data_get($booking->meta, PublicCheckoutFareChangeState::META_ACCEPTED_FARE_CONTEXT_HASH));
    }

    public function test_successful_iati_public_checkout_confirmation_shows_sabre_pnr(): void
    {
        $booking = $this->makeFreedomPkBooking([
            'pnr' => 'QNCBBB',
            'supplier_reference' => 'QNCBBB',
            'supplier_booking_status' => 'pending_payment_or_ticketing',
            'ticketing_status' => 'pending',
            'meta' => array_merge($this->freedomMeta(), [
                'public_checkout_pnr_strategy' => [
                    'selected_strategy' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
                    'pnr_strategy_used' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
                ],
                'sabre_checkout_outcome' => [
                    'success' => true,
                    'status' => 'pending_payment_or_ticketing',
                    'pnr' => 'QNCBBB',
                    'live_call_attempted' => true,
                    'ticketing_attempted' => false,
                ],
            ]),
        ]);

        $response = $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->get(route('booking.confirmation'));

        $response->assertOk();
        $response->assertSee('QNCBBB', false);
        $response->assertSee('Sabre PNR', false);
        $response->assertDontSee('Sabre PNR: Under review', false);
    }

    public function test_booking_86_style_live_checkout_honors_registry_iati_not_traditional(): void
    {
        $this->activateSabreConnectionForLiveHttp();
        $this->stubSabreOAuthAndHttp(fn () => Http::response([
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => ['status' => 'Complete'],
                'ItineraryRef' => ['ID' => 'QNCBBB'],
            ],
        ], 200));

        Config::set('suppliers.sabre.booking_enabled', true);
        Config::set('suppliers.sabre.booking_live_call_enabled', true);
        Config::set('suppliers.sabre.pnr_create_enabled', true);
        Config::set('suppliers.sabre.booking_payload_style', SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS);
        Config::set('suppliers.sabre.revalidate_before_booking', false);
        Config::set('suppliers.sabre.refresh_offer_before_public_pnr', false);
        Config::set('suppliers.sabre.passenger_records_fresh_shop_guard_before_live', false);
        Config::set('suppliers.sabre.passenger_records_block_risky_itinerary_live', false);
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.certified_route_selector_public_checkout_enabled', false);

        $booking = $this->makeFreedomPkBooking();
        $booking = $booking->fresh(['passengers', 'contact', 'fareBreakdown']);

        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForBooking($booking);
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $selection['selected_strategy'],
        );
        $this->assertContains(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
            $selection['blocked_strategies'],
        );
        $this->assertContains(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            $selection['blocked_strategies'],
        );

        $beforeAttempts = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count();
        $result = app(SabreBookingService::class)->runPublicReviewDryRun(
            $booking->fresh(['passengers', 'contact', 'fareBreakdown', 'supplierBookings']),
        );
        $afterAttempts = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count();

        $this->assertSame($beforeAttempts + 1, $afterAttempts);
        $this->assertTrue((bool) ($result['live_call_attempted'] ?? false));
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $result['pnr_strategy_used'] ?? $result['payload_schema'] ?? null,
        );
        $this->assertStringContainsString('/v2.4.0/passenger/records', (string) ($result['endpoint_path'] ?? ''));

        $latest = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $safe = is_array($latest?->safe_summary) ? $latest->safe_summary : [];
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            (string) ($safe['payload_schema'] ?? $safe['selected_payload_style'] ?? ''),
        );
        $this->assertStringContainsString('/v2.4.0/passenger/records', (string) ($safe['endpoint_path'] ?? ''));
        $encoded = json_encode($safe, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString(SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1, $encoded);

        $meta = is_array($booking->fresh()->meta) ? $booking->fresh()->meta : [];
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            data_get($meta, 'public_checkout_pnr_strategy.pnr_strategy_used'),
        );
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            data_get($meta, 'public_checkout_pnr_strategy.selected_strategy'),
        );
    }

    public function test_gds_refresh_ok_skips_redundant_revalidation_and_proceeds_to_live_pnr(): void
    {
        $this->activateSabreConnectionForLiveHttp();
        $revalidateCalled = false;
        Http::fake(function (Request $request, array $options) use (&$revalidateCalled) {
            $payload = $options['laravel_data'] ?? [];
            $tokenPath = strtolower((string) config('suppliers.sabre.token_path', '/v2/auth/token'));
            $isOAuth = str_contains(strtolower($request->url()), $tokenPath)
                || (is_array($payload) && array_key_exists('grant_type', $payload));
            if ($isOAuth) {
                return Http::response(['access_token' => 'tok-test-stub', 'expires_in' => 3600], 200);
            }
            if (str_contains(strtolower($request->url()), 'revalidate')) {
                $revalidateCalled = true;

                return Http::response(['status' => 'failed'], 400);
            }

            return Http::response([
                'CreatePassengerNameRecordRS' => [
                    'ApplicationResults' => ['status' => 'Complete'],
                    'ItineraryRef' => ['ID' => 'QNCBBB'],
                ],
            ], 200);
        });

        Config::set('suppliers.sabre.booking_enabled', true);
        Config::set('suppliers.sabre.booking_live_call_enabled', true);
        Config::set('suppliers.sabre.pnr_create_enabled', true);
        Config::set('suppliers.sabre.revalidate_before_booking', true);
        Config::set('suppliers.sabre.refresh_offer_before_public_pnr', true);
        Config::set('suppliers.sabre.passenger_records_fresh_shop_guard_before_live', false);
        Config::set('suppliers.sabre.passenger_records_block_risky_itinerary_live', false);
        Config::set('suppliers.sabre.allow_createbooking_without_revalidation', false);
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.certified_route_selector_public_checkout_enabled', false);
        Config::set('suppliers.sabre.verified_multiseg_auto_pnr_enabled', true);

        $booking = $this->makeFreedomPkBooking([
            'meta' => array_merge($this->freedomMeta(), [
                'offer_refresh_status' => 'refreshed',
            ]),
        ])->fresh(['passengers', 'contact', 'fareBreakdown']);

        $beforeAttempts = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count();
        $result = app(SabreBookingService::class)->runPublicReviewDryRun($booking);
        $afterAttempts = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count();

        $this->assertFalse($revalidateCalled, 'Redundant BFM revalidation must not run when offer refresh already satisfied freshness.');
        $this->assertSame($beforeAttempts + 1, $afterAttempts);
        $this->assertTrue((bool) ($result['live_call_attempted'] ?? false));
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $result['pnr_strategy_used'] ?? $result['payload_schema'] ?? null,
        );

        $checkoutOutcome = data_get($booking->fresh()->meta, 'sabre_checkout_outcome');
        $this->assertTrue((bool) data_get($checkoutOutcome, 'freshness_satisfied'));
        $this->assertSame('offer_refresh', data_get($checkoutOutcome, 'freshness_source'));
    }

    public function test_booking_87_style_freshness_satisfied_proceeds_to_iati_pnr(): void
    {
        $this->activateSabreConnectionForLiveHttp();
        Http::fake(function (Request $request, array $options) {
            $payload = $options['laravel_data'] ?? [];
            $tokenPath = strtolower((string) config('suppliers.sabre.token_path', '/v2/auth/token'));
            $isOAuth = str_contains(strtolower($request->url()), $tokenPath)
                || (is_array($payload) && array_key_exists('grant_type', $payload));
            if ($isOAuth) {
                return Http::response(['access_token' => 'tok-test-stub', 'expires_in' => 3600], 200);
            }
            if (str_contains(strtolower($request->url()), 'revalidate')) {
                return Http::response(['status' => 'failed'], 400);
            }

            return Http::response([
                'CreatePassengerNameRecordRS' => [
                    'ApplicationResults' => ['status' => 'Complete'],
                    'ItineraryRef' => ['ID' => 'QNCBBB'],
                ],
            ], 200);
        });

        Config::set('suppliers.sabre.booking_enabled', true);
        Config::set('suppliers.sabre.booking_live_call_enabled', true);
        Config::set('suppliers.sabre.pnr_create_enabled', true);
        Config::set('suppliers.sabre.revalidate_before_booking', true);
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.certified_route_selector_public_checkout_enabled', false);
        Config::set('suppliers.sabre.passenger_records_fresh_shop_guard_before_live', false);
        Config::set('suppliers.sabre.passenger_records_block_risky_itinerary_live', false);

        $booking = $this->makeFreedomPkBooking([
            'booking_reference' => null,
            'selected_fare_total' => 88602,
            'revalidated_fare_total' => 88602,
            'meta' => array_merge($this->freedomMeta(), [
                'offer_refresh_status' => 'refreshed',
            ]),
        ])->fresh(['passengers', 'contact', 'fareBreakdown']);

        $result = app(SabreBookingService::class)->runPublicReviewDryRun($booking);

        $this->assertTrue((bool) ($result['live_call_attempted'] ?? false));
        $this->assertStringContainsString('/v2.4.0/passenger/records', (string) ($result['endpoint_path'] ?? ''));
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $result['pnr_strategy_used'] ?? null,
        );

        $latest = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $safe = is_array($latest?->safe_summary) ? $latest->safe_summary : [];
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            (string) ($safe['selected_payload_style'] ?? $safe['payload_schema'] ?? ''),
        );
        $this->assertStringContainsString('/v2.4.0/passenger/records', (string) ($safe['endpoint_path'] ?? ''));
    }

    public function test_booking_88_style_payload_validation_passes_with_ticketing_config_on(): void
    {
        Config::set('suppliers.sabre.booking_enabled', true);
        Config::set('suppliers.sabre.booking_live_call_enabled', false);
        Config::set('suppliers.sabre.pnr_create_enabled', true);
        Config::set('suppliers.sabre.revalidate_before_booking', false);
        Config::set('suppliers.sabre.refresh_offer_before_public_pnr', false);
        Config::set('suppliers.sabre.ticketing_enabled', true);
        Config::set('suppliers.sabre.booking_mode', 'pnr_only');
        Config::set('suppliers.sabre.certified_route_selector_public_checkout_enabled', false);
        Config::set('suppliers.sabre.passenger_records_fresh_shop_guard_before_live', false);
        Config::set('suppliers.sabre.passenger_records_block_risky_itinerary_live', false);

        $total = 88623.0;
        $snapshot = array_merge($this->directPkSnapshot(), [
            'total' => $total,
            'fare_breakdown' => [
                'supplier_total' => $total,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ]);

        $booking = $this->makeFreedomPkBooking([
            'selected_fare_total' => $total,
            'revalidated_fare_total' => $total,
            'meta' => array_merge($this->freedomMeta(), [
                'offer_refresh_status' => 'refreshed',
                'normalized_offer_snapshot' => $snapshot,
                'selected_fare_family_option' => array_merge($this->freedomMeta()['selected_fare_family_option'], [
                    'displayed_price' => $total,
                ]),
                'sabre_booking_context' => array_merge($this->freedomMeta()['sabre_booking_context'], [
                    'selected_price_total' => $total,
                ]),
            ]),
        ])->fresh(['passengers', 'contact', 'fareBreakdown']);
        $booking->fareBreakdown?->forceFill(['total' => $total])->save();

        $selection = app(SabreGdsPnrCreateStrategySelector::class)->selectForBooking($booking);
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $selection['selected_strategy'],
        );

        $result = app(SabreBookingService::class)->runPublicReviewDryRun($booking);

        $this->assertFalse($result['live_call_attempted'] ?? true);
        $this->assertNotSame('payload_validation_failed', $result['status'] ?? null);
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $result['pnr_strategy_used'] ?? $result['payload_schema'] ?? null,
        );

        $payloadSummary = is_array($result['create_payload_safe_summary'] ?? null)
            ? $result['create_payload_safe_summary']
            : [];
        $invalidKeys = is_array($payloadSummary['wire_invalid_traditional_pnr_contract_keys'] ?? null)
            ? $payloadSummary['wire_invalid_traditional_pnr_contract_keys']
            : [];
        $this->assertNotContains('ticketing_enabled_in_config', $invalidKeys);
        $this->assertTrue(
            ($payloadSummary['wire_traditional_pnr_contract_valid'] ?? null) === true
            || ($result['status'] ?? '') !== 'payload_validation_failed',
        );
    }

    public function test_gds_revalidation_http400_blocks_without_passenger_records_call(): void
    {
        $this->activateSabreConnectionForLiveHttp();
        $sabreBase = 'https://example.sabre.test';
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $revalidatePath = '/v4/shop/flights/revalidate';
        $pnrCalled = false;
        Http::fake([
            $sabreBase.$tokenPath => Http::response(['access_token' => 'tok-test-stub', 'expires_in' => 3600], 200),
            $sabreBase.$revalidatePath => Http::response([
                'errors' => [[
                    'code' => 'ERR.TEST',
                    'message' => 'Revalidation failed',
                ]],
            ], 400),
            $sabreBase.'/v2.4.0/passenger/records*' => function () use (&$pnrCalled) {
                $pnrCalled = true;

                return Http::response([], 500);
            },
            $sabreBase.'/v2.5.0/passenger/records*' => function () use (&$pnrCalled) {
                $pnrCalled = true;

                return Http::response([], 500);
            },
        ]);

        Config::set('suppliers.sabre.booking_enabled', true);
        Config::set('suppliers.sabre.booking_live_call_enabled', true);
        Config::set('suppliers.sabre.pnr_create_enabled', true);
        Config::set('suppliers.sabre.revalidate_before_booking', true);
        Config::set('suppliers.sabre.revalidate_path', $revalidatePath);
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.certified_route_selector_public_checkout_enabled', false);
        Config::set('suppliers.sabre.passenger_records_fresh_shop_guard_before_live', false);
        Config::set('suppliers.sabre.passenger_records_block_risky_itinerary_live', false);
        Config::set('suppliers.sabre.allow_createbooking_without_revalidation', false);
        Config::set('suppliers.sabre.refresh_offer_before_public_pnr', false);

        $booking = $this->makeFreedomPkBooking([
            'meta' => array_merge($this->freedomMeta(), [
                'normalized_offer_snapshot' => array_merge($this->directPkSnapshot(), [
                    'segments' => [[
                        'origin' => 'LHE',
                        'destination' => 'DXB',
                        'carrier' => 'PK',
                        'marketing_carrier' => 'PK',
                        'operating_carrier' => 'PK',
                        'flight_number' => '303',
                        'departure_at' => '2026-06-15T05:00:00',
                        'arrival_at' => '2026-06-15T08:00:00',
                        'booking_class' => 'Y',
                        'fare_basis_code' => 'YCTX1',
                    ]],
                    'raw_payload' => [
                        'sabre_shop_context' => [
                            'itinerary_ref' => 'itin-rv-fail',
                            'pricing_information_ref' => 'offer-rv-fail',
                            'leg_refs' => [1],
                            'schedule_refs' => [1],
                            'fare_basis_codes' => ['YCTX1'],
                        ],
                    ],
                ]),
            ]),
        ])->fresh(['passengers', 'contact', 'fareBreakdown']);

        $svc = app(SabreBookingService::class);
        $snapshot = is_array($booking->meta['normalized_offer_snapshot'] ?? null)
            ? $booking->meta['normalized_offer_snapshot']
            : [];
        $beforeAttempts = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count();
        $result = $svc->createBooking(
            $snapshot,
            $svc->passengerDataFromBookingForCommand($booking),
            $booking->id,
            [
                'gds_pnr_strategy_code' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
                'gds_strategy_selection' => [
                    'selected_strategy' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
                    'selection_reason' => SabreGdsPnrCreateStrategySelector::REASON_CERTIFIED_ROUTE_MATRIX,
                    'eligible_strategies' => [SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1],
                    'blocked_strategies' => [],
                ],
            ],
        );
        $svc->finalizePublicCheckoutSabreStorage($booking, $result);
        $afterAttempts = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count();

        $this->assertFalse($pnrCalled);
        $this->assertSame($beforeAttempts + 1, $afterAttempts);
        $this->assertFalse((bool) ($result['live_call_attempted'] ?? true));
        $this->assertSame('sabre_gds_fare_validation_failed', $result['error_code'] ?? null);
        $this->assertFalse((bool) ($result['pnr_attempted'] ?? true));
        $this->assertTrue((bool) ($result['revalidation_attempted'] ?? false));
        $this->assertNull($result['http_status'] ?? null);
        $this->assertStringNotContainsString('Trip Orders', (string) ($result['message'] ?? ''));
        $this->assertStringContainsString('PNR/reservation was not attempted', (string) ($result['message'] ?? ''));

        $latest = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $safe = is_array($latest?->safe_summary) ? $latest->safe_summary : [];
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_TRADITIONAL_PNR_CREATE_V1,
            (string) ($safe['selected_payload_style'] ?? $safe['payload_schema'] ?? ''),
        );
        $this->assertNull($safe['http_status'] ?? null);
        $this->assertFalse((bool) ($safe['live_call_attempted'] ?? true));
        $this->assertStringNotContainsString('Trip Orders', (string) ($latest->error_message ?? ''));
    }

    public function test_enrich_gds_pre_pnr_revalidation_failure_maps_http_400(): void
    {
        config([
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
            'suppliers.sabre.booking_mode' => 'pnr_only',
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $svc = $this->app->make(SabreBookingService::class);
        $styleProperty = new \ReflectionProperty(SabreBookingService::class, 'attemptPassengerRecordsStyleDecision');
        $styleProperty->setAccessible(true);
        $styleProperty->setValue($svc, [
            'selected_payload_style' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
            'selected_strategy_code' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
            'selected_endpoint_path' => '/v2.5.0/passenger/records?mode=create',
            'iati_like_selected' => false,
        ]);

        $method = new \ReflectionMethod(SabreBookingService::class, 'enrichGdsPrePnrRevalidationFailureResult');
        $method->setAccessible(true);
        $enriched = $method->invoke($svc, [
            'success' => false,
            'status' => 'failed',
            'error_code' => 'sabre_revalidation_failed',
            'reason_code' => 'sabre_revalidation_failed',
            'revalidation_attempted' => true,
            'http_status' => 400,
        ], [
            'success' => false,
            'http_status' => 400,
            'reason_code' => 'sabre_revalidation_failed',
            'endpoint_path' => '/v4/shop/flights/revalidate',
        ]);

        $this->assertSame('sabre_gds_fare_validation_failed', $enriched['error_code'] ?? null);
        $this->assertSame(400, $enriched['revalidation_http_status'] ?? null);
        $this->assertNull($enriched['http_status'] ?? null);
        $this->assertFalse((bool) ($enriched['pnr_attempted'] ?? true));
        $this->assertStringNotContainsString('Trip Orders', (string) ($enriched['message'] ?? ''));
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
            $enriched['selected_payload_style'] ?? null,
        );
    }

    public function test_application_error_attempt_safe_summary_includes_format_host_classification(): void
    {
        $svc = app(SabreBookingService::class);
        $method = new \ReflectionMethod(SabreBookingService::class, 'sabreBookingApplicationErrorAttemptSafeSummary');
        $method->setAccessible(true);
        $safe = $method->invoke(
            $svc,
            [
                'error_code' => 'sabre_booking_application_error',
                'live_call_attempted' => true,
                'http_status' => 200,
                'response_error_messages' => ['EnhancedAirBookRQ: FORMAT'],
                'passenger_count' => 1,
                'segment_count' => 1,
                'booking_schema' => 'create_passenger_name_record',
                'payload_schema' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
                'passenger_records_application_digest' => [
                    'warnings' => [[
                        'type' => 'warning',
                        'code' => 'ERR.SP.PROVIDER_ERROR',
                        'message' => 'EnhancedAirBookRQ: FORMAT',
                    ]],
                ],
            ],
            ['endpoint_path' => '/v2.5.0/passenger/records?mode=create'],
            [],
            'sabre_booking_service',
        );

        $this->assertSame(
            SabreHostErrorClassifier::REASON_ENHANCED_AIRBOOK_FORMAT,
            $safe['safe_reason_code'] ?? null,
        );
        $this->assertSame(
            SabreHostErrorClassifier::HOST_ERROR_FAMILY_ENHANCED_AIRBOOK_FORMAT,
            $safe['host_error_family'] ?? null,
        );
        $this->assertSame('admin_confirmed_fallback_only', $safe['retry_policy'] ?? null);
        $encoded = json_encode($safe, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('EnhancedAirBookRQ: FORMAT', $encoded);
    }

    public function test_application_error_attempt_safe_summary_includes_completion_diagnostics(): void
    {
        $svc = app(SabreBookingService::class);
        $method = new \ReflectionMethod(SabreBookingService::class, 'sabreBookingApplicationErrorAttemptSafeSummary');
        $method->setAccessible(true);
        $safe = $method->invoke(
            $svc,
            [
                'error_code' => 'sabre_booking_application_error',
                'live_call_attempted' => true,
                'http_status' => 200,
                'response_error_messages' => ['EnhancedAirBookRQ: FORMAT'],
                'passenger_count' => 1,
                'segment_count' => 2,
                'booking_schema' => 'create_passenger_name_record',
                'payload_schema' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
                'auto_pnr_context_completion' => [
                    'auto_pnr_context_completion_attempted' => true,
                    'auto_pnr_context_completion_status' => SabreGdsAutoPnrContextCompletionService::STATUS_REPAIRED,
                    'completion_sources_used' => ['normalized_offer_pricing_index'],
                    'segment_count' => 2,
                    'booking_classes_by_segment_count' => 2,
                    'fare_basis_codes_by_segment_count' => 2,
                    'public_auto_pnr_attempt_ready' => true,
                ],
            ],
            ['endpoint_path' => '/v2.4.0/passenger/records?mode=create'],
            [],
            'sabre_booking_service',
        );

        $this->assertSame(SabreGdsAutoPnrContextCompletionService::STATUS_REPAIRED, $safe['auto_pnr_context_completion_status'] ?? null);
        $this->assertTrue((bool) ($safe['public_auto_pnr_attempt_ready'] ?? false));
        $this->assertSame(2, $safe['booking_classes_by_segment_count'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeBrandedCheckoutBooking(array $overrides = []): Booking
    {
        $brandCode = (string) ($overrides['brand_code'] ?? 'YDELUXE');
        $fareBasis = (string) ($overrides['fare_basis'] ?? 'EN900F6R/E');
        $total = (float) ($overrides['selected_fare_total'] ?? 84912);
        $breakdownTotal = (float) ($overrides['fare_breakdown_total'] ?? $total);
        unset($overrides['brand_code'], $overrides['fare_basis'], $overrides['fare_breakdown_total']);

        $meta = array_merge($this->freedomMeta(), [
            'selected_fare_family_option' => [
                'brand_code' => $brandCode,
                'name' => $brandCode,
                'displayed_price' => $total,
                'fare_basis_codes_by_segment' => [$fareBasis],
                'booking_classes_by_segment' => ['E'],
            ],
            'sabre_booking_context' => [
                'selected_brand_code' => $brandCode,
                'brand_code' => $brandCode,
                'fare_basis_codes_by_segment' => [$fareBasis],
                'booking_classes_by_segment' => ['E'],
            ],
        ], is_array($overrides['meta'] ?? null) ? $overrides['meta'] : []);
        unset($overrides['meta']);

        $snapshot = $this->directPkSnapshot();
        $snapshot['total'] = $total;
        $snapshot['fare_breakdown']['supplier_total'] = $total;
        if (isset($snapshot['segments'][0]) && is_array($snapshot['segments'][0])) {
            $snapshot['segments'][0]['booking_class'] = 'E';
            $snapshot['segments'][0]['fare_basis_code'] = $fareBasis;
        }
        $meta['normalized_offer_snapshot'] = $snapshot;

        $booking = $this->makeFreedomPkBooking(array_merge([
            'selected_fare_total' => $total,
            'revalidated_fare_total' => $total,
            'meta' => $meta,
        ], $overrides));

        $booking->fareBreakdown?->forceFill(['total' => $breakdownTotal])->save();

        return $booking->fresh(['passengers', 'contact', 'fareBreakdown']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeFreedomPkBooking(array $overrides = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::query()->create(array_merge([
            'agency_id' => $agency->id,
            'booking_reference' => 'BR-FREEDOM-'.uniqid(),
            'status' => 'pending',
            'supplier' => SupplierProvider::Sabre->value,
            'selected_fare_total' => 88602,
            'revalidated_fare_total' => 88602,
            'meta' => $this->freedomMeta(),
        ], $overrides));

        $booking->forceFill(['travel_date' => '2026-08-15'])->save();
        $this->seedPassengers($booking, 88602);

        return $booking->fresh(['passengers', 'contact', 'fareBreakdown']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function freedomMeta(): array
    {
        return [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => 2,
            'payment_mode' => 'pay_later_booking_request',
            'selected_fare_family_option' => [
                'brand_code' => 'FL',
                'name' => 'FREEDOM',
                'displayed_price' => 88602,
                'baggage_summary' => '30 kg',
                'fare_basis_codes_by_segment' => ['VOWFL/V'],
                'booking_classes_by_segment' => ['V'],
            ],
            'sabre_booking_context' => [
                'selected_brand_code' => 'FL',
                'brand_code' => 'FL',
                'fare_basis_codes_by_segment' => ['VOWFL/V'],
                'booking_classes_by_segment' => ['V'],
                'baggage' => '30 kg',
                'validating_carrier' => 'PK',
                'selected_price_total' => 88602,
            ],
            'normalized_offer_snapshot' => $this->directPkSnapshot(),
            'distribution_channel' => 'gds',
            'fare_option_key' => 'fl-key',
            'search_criteria' => ['trip_type' => 'one_way', 'origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => '2026-08-15'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function directPkSnapshot(): array
    {
        return [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => 2,
            'supplier_offer_id' => 'pk-lhe-dxb-freedom',
            'offer_id' => 'pk-lhe-dxb-freedom',
            'validating_carrier' => 'PK',
            'distribution_channel' => 'gds',
            'total' => 88602,
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'carrier' => 'PK',
                'marketing_carrier' => 'PK',
                'operating_carrier' => 'PK',
                'flight_number' => '233',
                'departure_at' => '2026-08-15T08:00:00',
                'arrival_at' => '2026-08-15T11:00:00',
                'booking_class' => 'V',
                'fare_basis_code' => 'VOWFL/V',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 88602,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
    }

    protected function seedPassengers(Booking $booking, float $total = 88602): void
    {
        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_type' => 'adult',
            'first_name' => 'Test',
            'last_name' => 'Traveler',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'gender' => 'male',
            'passport_number' => 'AB1234567',
            'passport_issuing_country' => 'PK',
            'passport_expiry_date' => now()->addYears(5)->toDateString(),
            'nationality' => 'PK',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'booker@example.com',
            'phone' => '3001234567',
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'total' => $total,
            'currency' => 'PKR',
        ]);
    }

    protected function configureSabreCheckout(): void
    {
        Config::set('suppliers.sabre.verified_multiseg_auto_pnr_enabled', true);
        Config::set('suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled', true);
        Config::set('suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled', true);
        Config::set('suppliers.sabre.cpnr_iati_style_certified_gds_enabled', true);
        Config::set('suppliers.sabre.traditional_cpnr_airprice_validating_carrier', true);
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.pnr_create_enabled', true);
        Config::set('suppliers.sabre.booking_enabled', true);
        Config::set('suppliers.sabre.booking_live_call_enabled', false);
        Config::set('suppliers.sabre.admin_manual_pnr_enabled', true);
        Config::set('suppliers.sabre.certified_route_selector_public_checkout_enabled', true);
    }

    protected function activateSabreConnectionForLiveHttp(): void
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
    }

    public function test_qr_connecting_collapsed_context_public_checkout_completes_and_attempts_live_pnr(): void
    {
        $this->activateSabreConnectionForLiveHttp();
        $this->stubSabreOAuthAndHttp(fn () => Http::response([
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => ['status' => 'Complete'],
                'ItineraryRef' => ['ID' => 'QRTEST1'],
            ],
        ], 200));

        Config::set('suppliers.sabre.booking_enabled', true);
        Config::set('suppliers.sabre.booking_live_call_enabled', true);
        Config::set('suppliers.sabre.pnr_create_enabled', true);
        Config::set('suppliers.sabre.revalidate_before_booking', false);
        Config::set('suppliers.sabre.refresh_offer_before_public_pnr', false);
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.certified_route_selector_public_checkout_enabled', false);

        $booking = $this->makeQrConnectingPkStyleBooking();
        $beforeAttempts = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count();
        $result = app(SabreBookingService::class)->runPublicReviewDryRun($booking);
        $afterAttempts = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count();

        $this->assertSame($beforeAttempts + 1, $afterAttempts);
        $this->assertTrue((bool) ($result['live_call_attempted'] ?? false));
        $this->assertTrue((bool) ($result['public_auto_pnr_attempted'] ?? false));
        $this->assertSame(
            SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            $result['pnr_strategy_used'] ?? $result['payload_schema'] ?? null,
        );
        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertSame('repaired', data_get($meta, 'auto_pnr_context_completion.auto_pnr_context_completion_status'));
        $this->assertSame(['H', 'H'], data_get($meta, 'sabre_booking_context.booking_classes_by_segment'));
        $this->assertSame('repaired', data_get($meta, 'sabre_checkout_outcome.auto_pnr_context_completion_status'));
        $this->assertTrue((bool) data_get($meta, 'sabre_checkout_outcome.public_auto_pnr_attempt_ready'));
        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $this->assertNotNull($attempt);
        $safe = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertSame('repaired', $safe['auto_pnr_context_completion_status'] ?? null);
        $this->assertTrue((bool) ($safe['public_auto_pnr_attempt_ready'] ?? false));
    }

    public function test_create_booking_blocks_connecting_without_completion_before_passenger_records(): void
    {
        Config::set('suppliers.sabre.booking_enabled', true);
        Config::set('suppliers.sabre.booking_live_call_enabled', true);

        $booking = $this->makeQrConnectingPkStyleBooking([
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => 2,
                'payment_mode' => 'pay_later_booking_request',
                'distribution_channel' => 'gds',
                'selected_fare_family_option' => [
                    'brand_code' => 'ECOMFORT',
                    'displayed_price' => 290800,
                    'booking_classes_by_segment' => ['H'],
                    'fare_basis_codes_by_segment' => ['HJR4R1FI/H'],
                ],
                'sabre_booking_context' => [
                    'selected_brand_code' => 'ECOMFORT',
                    'validating_carrier' => 'QR',
                    'booking_classes_by_segment' => ['H'],
                    'fare_basis_codes_by_segment' => ['HJR4R1FI/H'],
                    'segment_slice_count' => 2,
                ],
                'normalized_offer_snapshot' => [
                    'validating_carrier' => 'QR',
                    'distribution_channel' => 'gds',
                    'segments' => [
                        ['origin' => 'LHE', 'destination' => 'DOH', 'carrier' => 'QR', 'flight_number' => '615'],
                        ['origin' => 'DOH', 'destination' => 'DXB', 'carrier' => 'QR', 'flight_number' => '1002'],
                    ],
                ],
                'search_criteria' => ['trip_type' => 'one_way', 'origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => '2026-08-20'],
            ],
        ]);

        $svc = app(SabreBookingService::class);
        $method = new \ReflectionMethod(SabreBookingService::class, 'enforcePublicAutoPnrContextCompletionBeforeLiveCreate');
        $method->setAccessible(true);
        $block = $method->invoke($svc, $booking->id, 2, []);

        $this->assertIsArray($block);
        $this->assertFalse((bool) ($block['live_call_attempted'] ?? false));
        $this->assertSame(
            SabreGdsAutoPnrContextCompletionService::REASON_CONTEXT_COMPLETION_FAILED,
            $block['error_code'] ?? null,
        );
        $this->assertSame('needs_review', $block['status'] ?? null);
    }

    public function test_gf_connecting_format_failure_persists_completion_and_host_fingerprint(): void
    {
        $booking = $this->makeGfConnectingPkStyleBooking();
        $completionService = app(SabreGdsAutoPnrContextCompletionService::class);
        $completion = $completionService->completeForBooking($booking);
        $completionService->persistCompletedContext($booking->fresh(), $completion);
        $booking->refresh();

        app(SabreBookingService::class)->finalizePublicCheckoutSabreStorage($booking, [
            'success' => false,
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'live_call_attempted' => true,
            'http_status' => 200,
            'response_error_messages' => ['EnhancedAirBookRQ: FORMAT'],
            'passenger_records_application_digest' => [
                'warnings' => [[
                    'type' => 'warning',
                    'code' => 'ERR.SP.PROVIDER_ERROR',
                    'message' => 'EnhancedAirBookRQ: FORMAT',
                ]],
            ],
            'segment_count' => 2,
            'passenger_count' => 1,
            'booking_schema' => 'create_passenger_name_record',
            'payload_schema' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            'pnr_strategy_used' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_IATI_LIKE_CPNR_V2_4_GDS,
            'auto_pnr_context_completion' => $completion,
        ]);

        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertSame('repaired', data_get($meta, 'sabre_checkout_outcome.auto_pnr_context_completion_status'));
        $this->assertSame(
            SabreHostErrorClassifier::HOST_ERROR_FAMILY_ENHANCED_AIRBOOK_FORMAT,
            data_get($meta, 'sabre_checkout_outcome.sabre_host_classification.host_error_family'),
        );
        $fingerprint = data_get($meta, 'sabre_checkout_outcome.sabre_host_rejection_fingerprint');
        $this->assertIsArray($fingerprint);
        $this->assertSame(['GF'], $fingerprint['carrier_chain'] ?? null);
        $this->assertSame('GF', $fingerprint['validating_carrier'] ?? null);
        $this->assertSame(SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_SINGLE_CONNECTION_SAME_CARRIER, $fingerprint['trip_type'] ?? null);
        $this->assertSame(2, $fingerprint['segment_count'] ?? null);
        $this->assertSame(2, $fingerprint['booking_classes_by_segment_count'] ?? null);
        $this->assertSame(2, $fingerprint['fare_basis_codes_by_segment_count'] ?? null);
        $this->assertSame(
            SabreHostErrorClassifier::REASON_ENHANCED_AIRBOOK_FORMAT,
            $fingerprint['safe_reason_code'] ?? null,
        );
        $this->assertSame('admin_confirmed_fallback_only', $fingerprint['retry_policy'] ?? null);
        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $this->assertNotNull($attempt);
        $safe = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertSame('repaired', $safe['auto_pnr_context_completion_status'] ?? null);
        $this->assertSame(
            SabreHostErrorClassifier::HOST_ERROR_FAMILY_ENHANCED_AIRBOOK_FORMAT,
            $safe['host_error_family'] ?? null,
        );
    }

    public function test_qr_connecting_unrecoverable_context_public_checkout_manual_review_without_live_pnr(): void
    {
        $this->activateSabreConnectionForLiveHttp();
        Http::fake(function (Request $request, array $options) {
            $payload = $options['laravel_data'] ?? [];
            $tokenPath = strtolower((string) config('suppliers.sabre.token_path', '/v2/auth/token'));
            $isOAuth = str_contains(strtolower($request->url()), $tokenPath)
                || (is_array($payload) && array_key_exists('grant_type', $payload));
            if ($isOAuth) {
                return Http::response(['access_token' => 'tok-test-stub', 'expires_in' => 3600], 200);
            }

            return Http::response(['CreatePassengerNameRecordRS' => ['ItineraryRef' => ['ID' => 'FAIL']]], 500);
        });

        Config::set('suppliers.sabre.booking_enabled', true);
        Config::set('suppliers.sabre.booking_live_call_enabled', true);
        Config::set('suppliers.sabre.pnr_create_enabled', true);
        Config::set('suppliers.sabre.revalidate_before_booking', false);
        Config::set('suppliers.sabre.refresh_offer_before_public_pnr', false);
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.certified_route_selector_public_checkout_enabled', false);

        $booking = $this->makeQrConnectingPkStyleBooking([
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => 2,
                'payment_mode' => 'pay_later_booking_request',
                'distribution_channel' => 'gds',
                'selected_fare_family_option' => [
                    'brand_code' => 'ECOMFORT',
                    'displayed_price' => 290800,
                    'booking_classes_by_segment' => ['H'],
                    'fare_basis_codes_by_segment' => ['HJR4R1FI/H'],
                ],
                'sabre_booking_context' => [
                    'selected_brand_code' => 'ECOMFORT',
                    'validating_carrier' => 'QR',
                    'booking_classes_by_segment' => ['H'],
                    'fare_basis_codes_by_segment' => ['HJR4R1FI/H'],
                    'segment_slice_count' => 2,
                ],
                'normalized_offer_snapshot' => [
                    'validating_carrier' => 'QR',
                    'distribution_channel' => 'gds',
                    'segments' => [
                        ['origin' => 'LHE', 'destination' => 'DOH', 'carrier' => 'QR', 'flight_number' => '615'],
                        ['origin' => 'DOH', 'destination' => 'DXB', 'carrier' => 'QR', 'flight_number' => '1002'],
                    ],
                ],
                'search_criteria' => ['trip_type' => 'one_way', 'origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => '2026-08-20'],
            ],
        ]);
        $beforeAttempts = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count();
        $result = app(SabreBookingService::class)->runPublicReviewDryRun($booking);
        $afterAttempts = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count();

        $this->assertSame($beforeAttempts, $afterAttempts);
        $this->assertFalse((bool) ($result['live_call_attempted'] ?? false));
        $this->assertSame('needs_review', $result['status'] ?? null);
        $this->assertSame(
            SabreGdsAutoPnrContextCompletionService::REASON_CONTEXT_COMPLETION_FAILED,
            $result['error_code'] ?? null,
        );
        $booking->refresh();
        $this->assertSame('manual_review', $booking->supplier_booking_status);
        $this->assertNull($booking->pnr);
        $this->assertSame(
            SabreGdsAutoPnrContextCompletionService::REASON_CONTEXT_COMPLETION_FAILED,
            data_get($booking->meta, 'sabre_checkout_outcome.error_code'),
        );
        $this->assertSame(
            SabreGdsAutoPnrContextCompletionService::STATUS_FAILED,
            data_get($booking->meta, 'sabre_checkout_outcome.auto_pnr_context_completion_status'),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeQrConnectingPkStyleBooking(array $overrides = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $snapshot = [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => 2,
            'supplier_offer_id' => 'qr-lhe-dxb-connect-chk',
            'offer_id' => 'qr-lhe-dxb-connect-chk',
            'validating_carrier' => 'QR',
            'distribution_channel' => 'gds',
            'total' => 290800,
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DOH', 'carrier' => 'QR', 'flight_number' => '615', 'departure_at' => '2026-08-20T03:30:00', 'arrival_at' => '2026-08-20T05:45:00', 'booking_class' => 'H', 'fare_basis_code' => 'HJR4R1FI/H'],
                ['origin' => 'DOH', 'destination' => 'DXB', 'carrier' => 'QR', 'flight_number' => '1002', 'departure_at' => '2026-08-20T08:10:00', 'arrival_at' => '2026-08-20T10:05:00', 'booking_class' => 'H', 'fare_basis_code' => 'HJR4R1FI/H'],
            ],
            'fare_breakdown' => ['supplier_total' => 290800, 'currency' => 'PKR', 'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0]],
        ];
        $booking = Booking::query()->create(array_merge([
            'agency_id' => $agency->id,
            'booking_reference' => 'BR-QR-CHK-'.uniqid(),
            'status' => 'pending',
            'supplier' => SupplierProvider::Sabre->value,
            'selected_fare_total' => 290800,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => 2,
                'payment_mode' => 'pay_later_booking_request',
                'distribution_channel' => 'gds',
                'selected_fare_family_option' => [
                    'brand_code' => 'ECOMFORT',
                    'displayed_price' => 290800,
                    'booking_classes_by_segment' => ['H'],
                    'fare_basis_codes_by_segment' => ['HJR4R1FI/H'],
                ],
                'sabre_booking_context' => [
                    'selected_brand_code' => 'ECOMFORT',
                    'validating_carrier' => 'QR',
                    'booking_classes_by_segment' => ['H'],
                    'fare_basis_codes_by_segment' => ['HJR4R1FI/H'],
                    'segment_slice_count' => 2,
                ],
                'normalized_offer_snapshot' => $snapshot,
                'search_criteria' => ['trip_type' => 'one_way', 'origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => '2026-08-20'],
            ],
        ], $overrides));
        $booking->forceFill(['travel_date' => '2026-08-20'])->save();
        $this->seedPassengers($booking, 290800);

        return $booking->fresh(['passengers', 'contact', 'fareBreakdown']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeGfConnectingPkStyleBooking(array $overrides = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $snapshot = [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => 2,
            'supplier_offer_id' => 'gf-lhe-dxb-connect-chk',
            'offer_id' => 'gf-lhe-dxb-connect-chk',
            'validating_carrier' => 'GF',
            'distribution_channel' => 'gds',
            'total' => 185400,
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'BAH', 'carrier' => 'GF', 'flight_number' => '765', 'departure_at' => '2026-09-10T04:15:00', 'arrival_at' => '2026-09-10T06:35:00', 'booking_class' => 'W', 'fare_basis_code' => 'WLOWPK'],
                ['origin' => 'BAH', 'destination' => 'DXB', 'carrier' => 'GF', 'flight_number' => '512', 'departure_at' => '2026-09-10T09:20:00', 'arrival_at' => '2026-09-10T10:35:00', 'booking_class' => 'W', 'fare_basis_code' => 'WLOWPK'],
            ],
            'fare_breakdown' => ['supplier_total' => 185400, 'currency' => 'PKR', 'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0]],
        ];

        $booking = Booking::query()->create(array_merge([
            'agency_id' => $agency->id,
            'booking_reference' => 'BR-GF-CHK-'.uniqid(),
            'status' => 'pending',
            'supplier' => SupplierProvider::Sabre->value,
            'selected_fare_total' => 185400,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => 2,
                'payment_mode' => 'pay_later_booking_request',
                'distribution_channel' => 'gds',
                'selected_fare_family_option' => [
                    'brand_code' => 'ECONOMY',
                    'displayed_price' => 185400,
                    'booking_classes_by_segment' => ['W'],
                    'fare_basis_codes_by_segment' => ['WLOWPK'],
                ],
                'sabre_booking_context' => [
                    'selected_brand_code' => 'ECONOMY',
                    'validating_carrier' => 'GF',
                    'booking_classes_by_segment' => ['W'],
                    'fare_basis_codes_by_segment' => ['WLOWPK'],
                    'segment_slice_count' => 2,
                ],
                'normalized_offer_snapshot' => $snapshot,
                'search_criteria' => ['trip_type' => 'one_way', 'origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => '2026-09-10'],
            ],
        ], $overrides));
        $booking->forceFill(['travel_date' => '2026-09-10'])->save();
        $this->seedPassengers($booking, 185400);

        return $booking->fresh(['passengers', 'contact', 'fareBreakdown']);
    }

    /**
     * @param  callable(): mixed  $afterTokenResponder
     */
    protected function stubSabreOAuthAndHttp(callable $afterTokenResponder): void
    {
        Http::fake(function (Request $request, array $options) use ($afterTokenResponder) {
            $payload = $options['laravel_data'] ?? [];
            $tokenPath = strtolower((string) config('suppliers.sabre.token_path', '/v2/auth/token'));
            $isOAuth = str_contains(strtolower($request->url()), $tokenPath)
                || (is_array($payload) && array_key_exists('grant_type', $payload));

            if ($isOAuth) {
                return Http::response(['access_token' => 'tok-test-stub', 'expires_in' => 3600], 200);
            }

            return $afterTokenResponder();
        });
    }
}
