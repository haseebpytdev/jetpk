<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabrePreCheckoutKnownFailureSoftBlock;
use App\Support\Bookings\SabrePreCheckoutSellabilityDryRun;
use App\Support\Bookings\SabreSafeRefreshContext;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabrePreCheckoutKnownFailureSoftBlockTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    protected array $forbiddenCustomerTokens = [
        'fare_rbd_carrier_not_sellable',
        'host_noop_blocked',
        'exact_failed_evidence',
        'exact_success_evidence',
        'insufficient_flight_date_sellability_evidence',
        'Sabre',
        'NO FARES',
        'RBD/CARRIER',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->configureControlledConnecting();
        Http::fake();
    }

    public function test_config_false_never_would_soft_block_even_for_failed_evidence(): void
    {
        config(['suppliers.sabre.precheckout_known_failure_soft_block_enabled' => false]);

        $dryRun = [
            'dry_run_status' => SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_FAILED,
            'recommended_checkout_action' => SabrePreCheckoutSellabilityDryRun::ACTION_BLOCKED_SAME_OFFER,
        ];

        $this->assertTrue(SabrePreCheckoutKnownFailureSoftBlock::isEligibleDryRun($dryRun));
        $this->assertFalse(SabrePreCheckoutKnownFailureSoftBlock::wouldSoftBlock($dryRun));
        $this->assertFalse(SabrePreCheckoutKnownFailureSoftBlock::configEnabled());
    }

    public function test_config_true_blocks_exact_failed_and_host_noop_only(): void
    {
        config(['suppliers.sabre.precheckout_known_failure_soft_block_enabled' => true]);

        $failed = [
            'dry_run_status' => SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_FAILED,
            'recommended_checkout_action' => SabrePreCheckoutSellabilityDryRun::ACTION_BLOCKED_SAME_OFFER,
        ];
        $hostNoop = [
            'dry_run_status' => SabreCertifiedRouteSelector::EVIDENCE_STATUS_HOST_NOOP_BLOCKED,
            'recommended_checkout_action' => SabrePreCheckoutSellabilityDryRun::ACTION_BLOCKED_SAME_OFFER,
        ];
        $success = [
            'dry_run_status' => SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_SUCCESS,
            'recommended_checkout_action' => SabrePreCheckoutSellabilityDryRun::ACTION_CANDIDATE_AUTO_PNR_LATER,
        ];
        $insufficient = [
            'dry_run_status' => SabreCertifiedRouteSelector::EVIDENCE_STATUS_INSUFFICIENT_FLIGHT_DATE,
            'recommended_checkout_action' => SabrePreCheckoutSellabilityDryRun::ACTION_FRESH_SEARCH_RECOMMENDED,
        ];

        $this->assertTrue(SabrePreCheckoutKnownFailureSoftBlock::wouldSoftBlock($failed));
        $this->assertSame(SabreCertifiedRouteSelector::EVIDENCE_STATUS_EXACT_FAILED, SabrePreCheckoutKnownFailureSoftBlock::softBlockReason($failed));
        $this->assertTrue(SabrePreCheckoutKnownFailureSoftBlock::wouldSoftBlock($hostNoop));
        $this->assertSame(SabreCertifiedRouteSelector::EVIDENCE_STATUS_HOST_NOOP_BLOCKED, SabrePreCheckoutKnownFailureSoftBlock::softBlockReason($hostNoop));
        $this->assertFalse(SabrePreCheckoutKnownFailureSoftBlock::wouldSoftBlock($success));
        $this->assertFalse(SabrePreCheckoutKnownFailureSoftBlock::wouldSoftBlock($insufficient));
        $this->assertNull(SabrePreCheckoutKnownFailureSoftBlock::softBlockReason($insufficient));
    }

    public function test_would_soft_block_from_meta_uses_persisted_dry_run(): void
    {
        config(['suppliers.sabre.precheckout_known_failure_soft_block_enabled' => true]);

        $booking = $this->gfBooking46LikeConnectingBooking();
        app(SabrePreCheckoutSellabilityDryRun::class)->evaluateAndPersist($booking);

        $this->assertTrue(SabrePreCheckoutKnownFailureSoftBlock::wouldSoftBlockFromMeta($booking->fresh()));
    }

    public function test_customer_redirect_message_hides_internal_codes(): void
    {
        $message = SabrePreCheckoutKnownFailureSoftBlock::customerRedirectMessage();

        $this->assertSame(
            'This fare may no longer be available from the airline. Please select another available option.',
            $message
        );

        foreach ($this->forbiddenCustomerTokens as $token) {
            $this->assertStringNotContainsStringIgnoringCase($token, $message);
        }
    }

    protected function configureControlledConnecting(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => false,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.precheckout_known_failure_soft_block_enabled' => false,
        ]);
    }

    protected function gfBooking46LikeConnectingBooking(): Booking
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::Draft,
            'meta' => [
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => 1,
                'create_payload_strategy_version' => 'E5A_SAFE_STRUCTURE_V1',
                'offer_validation_status' => 'valid',
                'search_criteria' => [
                    'trip_type' => 'one_way',
                    'origin' => 'LHE',
                    'destination' => 'JED',
                    'depart_date' => '2026-07-31',
                ],
                'normalized_offer_snapshot' => [
                    'supplier_provider' => 'sabre',
                    'validating_carrier' => 'GF',
                    'segments' => [
                        [
                            'origin' => 'LHE',
                            'destination' => 'BAH',
                            'carrier' => 'GF',
                            'flight_number' => '765',
                            'booking_class' => 'W',
                            'fare_basis_code' => 'WDLIT3PK',
                            'departure_at' => '2026-07-31T15:10:00',
                        ],
                        [
                            'origin' => 'BAH',
                            'destination' => 'JED',
                            'carrier' => 'GF',
                            'flight_number' => '173',
                            'booking_class' => 'W',
                            'fare_basis_code' => 'WDLIT3PK',
                            'departure_at' => '2026-08-01T18:05:00',
                        ],
                    ],
                ],
            ],
        ]);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $meta[SabreSafeRefreshContext::META_KEY] = app(SabreSafeRefreshContext::class)->buildFromCheckout($snapshot, [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'JED',
            'depart_date' => '2026-07-31',
            'adults' => 1,
        ], [
            'checkout_search_id' => 'gf-booking-46-soft-block-search',
            'checkout_offer_id' => 'gf-booking-46-soft-block-offer',
            'supplier_total' => 100.0,
            'supplier_currency' => 'PKR',
        ]);
        $booking->forceFill(['meta' => $meta])->save();

        BookingPassenger::factory()->for($booking)->create([
            'passenger_index' => 0,
            'is_lead_passenger' => true,
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'guest@example.test',
            'phone' => '+923001234567',
        ]);

        return $booking->fresh(['passengers', 'contact']);
    }
}
