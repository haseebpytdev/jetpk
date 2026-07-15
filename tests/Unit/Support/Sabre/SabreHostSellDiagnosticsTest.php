<?php

namespace Tests\Unit\Support\Sabre;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Http\Controllers\Admin\BookingManagementController;
use App\Models\Agency;
use App\Models\Booking;
use App\Support\Bookings\AdminSabreDiagnosticPanelsPresenter;
use App\Support\Bookings\SabreOperationalPnrReadiness;
use App\Support\Bookings\SupplierLifecycleContextResolver;
use App\Support\Sabre\SabreHostSellClassifier;
use App\Support\Sabre\SabreHostSellFingerprint;
use App\Support\Sabre\SabreHostSellReshopComparator;
use App\Support\Sabre\SabreHostSellRetryGuard;
use App\Support\Sabre\SabrePnrLaneDiagnostics;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SabreHostSellDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_uc_classification(): void
    {
        $result = SabreHostSellClassifier::classify([
            'airline_segment_status' => 'UC',
            'halt_on_status_received' => true,
        ]);

        $this->assertSame(SabreHostSellClassifier::OUTCOME_HOST_SELL_REJECTED_UC, $result['safe_reason_code']);
        $this->assertSame(SabreHostSellClassifier::RETRY_NO_RETRY_SAME_OFFER, $result['retry_policy']);
    }

    public function test_nn_classification(): void
    {
        $result = SabreHostSellClassifier::classify([
            'airline_segment_status' => 'NN',
            'halt_on_status_received' => true,
        ]);

        $this->assertSame(SabreHostSellClassifier::OUTCOME_HOST_NEED_NEED_STATUS, $result['safe_reason_code']);
    }

    public function test_hl_classification(): void
    {
        $result = SabreHostSellClassifier::classify([
            'airline_segment_status' => 'HL',
        ]);

        $this->assertSame(SabreHostSellClassifier::OUTCOME_HOST_WAITLIST_OR_HOLD, $result['safe_reason_code']);
        $this->assertSame(SabreHostSellClassifier::RETRY_MANUAL_REVIEW, $result['retry_policy']);
    }

    public function test_no_classification(): void
    {
        $result = SabreHostSellClassifier::classify([
            'airline_segment_status' => 'NO',
        ]);

        $this->assertSame(SabreHostSellClassifier::OUTCOME_HOST_NO_ACTION_OR_REJECTED, $result['safe_reason_code']);
        $this->assertSame(SabreHostSellClassifier::RETRY_NO_RETRY_SAME_OFFER, $result['retry_policy']);
    }

    public function test_halt_on_status_classification_with_uc(): void
    {
        $result = SabreHostSellClassifier::classify([
            'halt_on_status_received' => true,
            'airline_segment_status' => 'UC',
        ]);

        $this->assertSame(SabreHostSellClassifier::OUTCOME_HOST_SELL_REJECTED_UC, $result['safe_reason_code']);
    }

    public function test_fingerprint_deterministic_hash(): void
    {
        $fields = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'validating_carrier' => 'EK',
            'segment_fingerprints' => ['LHE-DXB|2026-08-01|EK'],
            'booking_classes' => ['Y'],
            'fare_basis_codes' => ['YLOW'],
            'brand_code' => 'ECO',
            'travel_date' => '2026-08-01',
            'host_error_family' => 'UC_SEGMENT_STATUS',
            'segment_status' => 'UC',
        ];

        $hashA = SabreHostSellFingerprint::computeHash($fields);
        $hashB = SabreHostSellFingerprint::computeHash($fields);

        $this->assertSame($hashA, $hashB);
        $this->assertSame(32, strlen($hashA));
    }

    public function test_repeated_uc_same_fingerprint_blocks_same_offer_retry(): void
    {
        $booking = $this->sabreGdsBooking();
        $offer = $this->sampleOffer();

        $diagnostics = [
            'safe_reason_code' => SabreHostSellClassifier::OUTCOME_HOST_SELL_REJECTED_UC,
            'retry_policy' => SabreHostSellClassifier::RETRY_NO_RETRY_SAME_OFFER,
            'airline_segment_statuses' => ['UC'],
            'route' => 'LHE → DXB',
            'departure_dates' => ['2026-08-01'],
            'validating_carrier' => 'EK',
            'booking_classes' => ['Y'],
            'fare_basis_codes' => ['YLOW'],
            'brand_code' => 'ECO',
        ];

        SabreHostSellFingerprint::buildAndRegister($booking, $diagnostics);
        $booking->refresh();

        $guard = SabreHostSellRetryGuard::evaluateSameOfferRetry($booking, $offer);

        $this->assertTrue($guard['blocked']);
        $this->assertStringContainsString('another fare', $guard['message']);
    }

    public function test_public_checkout_lane_does_not_show_operational_auto_pnr_enabled_as_blocker(): void
    {
        $booking = $this->sabreGdsBooking([
            'sabre_checkout_outcome' => [
                'live_call_attempted' => true,
                'status' => 'failed',
                'error_code' => 'sabre_booking_application_error',
                'airline_segment_status' => 'UC',
            ],
        ]);

        $blocking = ['operational_auto_pnr_enabled', 'public_checkout_pnr_enabled'];
        $filtered = SabrePnrLaneDiagnostics::filterBlockingConditionsForLane(
            $blocking,
            SabrePnrLaneDiagnostics::LANE_PUBLIC_CHECKOUT_PNR,
            $booking,
        );

        $this->assertNotContains('operational_auto_pnr_enabled', $filtered);
        $this->assertContains('public_checkout_pnr_enabled', $filtered);
    }

    public function test_operational_lane_excludes_public_checkout_flag_from_blocking_evaluation(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
        ]);

        $booking = $this->sabreGdsBooking();
        $result = app(SabreOperationalPnrReadiness::class)->evaluate($booking);

        $this->assertFalse($result['public_checkout_pnr_enabled']);
        $this->assertNotContains('public_checkout_pnr_enabled', $result['blocking_conditions']);
    }

    public function test_admin_panel_displays_host_sell_diagnostics_only_for_sabre_gds_bookings(): void
    {
        $booking = $this->sabreGdsBooking([
            'sabre_host_sell_diagnostics' => [
                'safe_reason_code' => SabreHostSellClassifier::OUTCOME_HOST_SELL_REJECTED_UC,
                'airline_segment_statuses' => ['UC'],
                'flight_numbers' => ['EK623'],
                'retry_policy' => SabreHostSellClassifier::RETRY_NO_RETRY_SAME_OFFER,
                'recommended_admin_action' => 'Re-shop itinerary.',
                'pnr_lane' => SabrePnrLaneDiagnostics::LANE_PUBLIC_CHECKOUT_PNR,
            ],
            'sabre_checkout_outcome' => [
                'live_call_attempted' => true,
                'status' => 'failed',
            ],
        ]);

        $panel = app(AdminSabreDiagnosticPanelsPresenter::class)->hostSellDiagnosticsPanel($booking);

        $this->assertTrue($panel['show']);
        $this->assertSame('Sabre Host Sell Diagnostics', $panel['title']);
        $this->assertNotEmpty($panel['rows']);
    }

    public function test_non_sabre_suppliers_do_not_receive_sabre_host_sell_diagnostics(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'supplier' => SupplierProvider::Duffel->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Duffel->value,
                'sabre_host_sell_diagnostics' => ['safe_reason_code' => 'should_not_show'],
            ],
        ]);

        $panel = BookingManagementController::buildSabreHostSellDiagnosticsPanel($booking);

        $this->assertFalse($panel['show']);
        $this->assertSame(SupplierLifecycleContextResolver::HANDLER_DUFFEL, app(SupplierLifecycleContextResolver::class)->handlerKey($booking));
    }

    public function test_reshop_comparator_detects_changed_booking_class(): void
    {
        $failed = $this->sampleOffer(['raw_payload' => ['sabre_shop_context' => ['booking_classes_by_segment' => ['Y']]]]);
        $fresh = $this->sampleOffer(['raw_payload' => ['sabre_shop_context' => ['booking_classes_by_segment' => ['M']]]]);

        $compare = SabreHostSellReshopComparator::compare($failed, $fresh);

        $this->assertTrue($compare['changed_booking_class']);
        $this->assertTrue($compare['retry_meaningful']);
    }

    public function test_customer_notice_for_host_sell_rejection(): void
    {
        $notice = SabreHostSellClassifier::customerNoticeForOutcome([
            'live_call_attempted' => true,
            'status' => 'failed',
            'airline_segment_status' => 'UC',
            'error_code' => 'sabre_booking_application_error',
        ]);

        $this->assertSame(SabreHostSellClassifier::CUSTOMER_HOST_REJECTION_MESSAGE, $notice);
        $this->assertStringNotContainsString('UC', (string) $notice);
    }

    /**
     * @param  array<string, mixed>  $metaOverrides
     */
    protected function sabreGdsBooking(array $metaOverrides = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        return Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'supplier' => SupplierProvider::Sabre->value,
            'route' => 'LHE → DXB',
            'meta' => array_merge([
                'supplier_provider' => SupplierProvider::Sabre->value,
                'distribution_channel' => 'gds',
                'flight_offer_snapshot' => $this->sampleOffer(),
            ], $metaOverrides),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function sampleOffer(array $overrides = []): array
    {
        return array_merge([
            'supplier_provider' => SupplierProvider::Sabre->value,
            'origin' => 'LHE',
            'destination' => 'DXB',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-08-01T10:00:00',
                'carrier' => 'EK',
                'flight_number' => '623',
                'booking_class' => 'Y',
                'fare_basis_code' => 'YLOW',
            ]],
            'raw_payload' => [
                'sabre_shop_context' => [
                    'booking_classes_by_segment' => ['Y'],
                    'fare_basis_codes_by_segment' => ['YLOW'],
                ],
            ],
        ], $overrides);
    }
}
