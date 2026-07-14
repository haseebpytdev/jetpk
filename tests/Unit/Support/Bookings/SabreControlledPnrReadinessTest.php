<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierConnection;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabreControlledPnrManualReviewApproval;
use App\Support\Bookings\SabreControlledPnrReadiness;
use App\Support\Bookings\SabreSafeRefreshContext;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Bookings\ControlledPnrContextTestFixtures;
use Tests\TestCase;

class SabreControlledPnrReadinessTest extends TestCase
{
    use ControlledPnrContextTestFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        config([
            'suppliers.sabre.booking_live_call_enabled' => false,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.cancel_enabled' => false,
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => false,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
        ]);
    }

    public function test_blocks_non_sabre_booking(): void
    {
        $booking = Booking::factory()->create([
            'supplier' => 'duffel',
            'meta' => ['supplier_provider' => 'duffel'],
        ]);

        $result = app(SabreControlledPnrReadiness::class)->evaluate($booking);

        $this->assertFalse($result['eligible']);
        $this->assertFalse($result['is_sabre_booking']);
        $this->assertContains('not_sabre_booking', $result['blockers']);
        $this->assertSame('This booking is not a Sabre booking.', $result['human_message']);
    }

    public function test_blocks_missing_supplier_connection(): void
    {
        $booking = $this->sabreBookingBase(['meta' => array_merge($this->baseMeta(), [
            'supplier_connection_id' => 0,
            'normalized_offer_snapshot' => ['segments' => [['origin' => 'LHE', 'destination' => 'DXB', 'carrier' => 'GF']]],
        ])]);

        $result = app(SabreControlledPnrReadiness::class)->evaluate($booking);

        $this->assertContains('missing_supplier_connection', $result['blockers']);
        $this->assertFalse($result['supplier_connection_present']);
    }

    public function test_blocks_existing_pnr_duplicate(): void
    {
        $booking = $this->sabreBookingBase(['pnr' => 'ABC123']);

        $result = app(SabreControlledPnrReadiness::class)->evaluate($booking);

        $this->assertTrue($result['has_existing_pnr']);
        $this->assertContains('existing_pnr_present', $result['blockers']);
        $this->assertTrue(app(SabreControlledPnrReadiness::class)->detectExistingPnr($booking));
    }

    public function test_blocks_cancelled_booking(): void
    {
        $booking = $this->sabreBookingBase(['status' => BookingStatus::Cancelled]);

        $result = app(SabreControlledPnrReadiness::class)->evaluate($booking);

        $this->assertTrue($result['is_cancelled']);
        $this->assertContains('cancelled_booking_blocked', $result['blockers']);
    }

    public function test_blocks_ticketed_booking(): void
    {
        $booking = $this->sabreBookingBase(['status' => BookingStatus::Ticketed]);

        $result = app(SabreControlledPnrReadiness::class)->evaluate($booking);

        $this->assertTrue($result['is_ticketed']);
        $this->assertContains('ticketed_booking_blocked', $result['blockers']);
    }

    public function test_blocks_missing_passenger_and_contact_and_pricing_context(): void
    {
        $booking = $this->sabreBookingBase();
        $booking->passengers()->delete();
        $booking->contact?->delete();

        $result = app(SabreControlledPnrReadiness::class)->evaluate($booking->fresh(['passengers', 'contact']));

        $this->assertFalse($result['has_required_passengers']);
        $this->assertFalse($result['has_required_contact']);
        $this->assertContains('missing_passengers', $result['blockers']);
        $this->assertContains('missing_contact', $result['blockers']);
        $this->assertContains('missing_pricing_context', $result['blockers']);
    }

    public function test_returns_safe_labels_and_mutation_snapshot(): void
    {
        $booking = $this->sabreBookingBase();

        $result = app(SabreControlledPnrReadiness::class)->evaluate($booking);

        $this->assertIsString($result['human_message']);
        $this->assertIsArray($result['mutation_flags_snapshot']);
        $this->assertFalse($result['mutation_flags_snapshot']['booking_live_call_enabled']);
        $this->assertTrue($result['ticketing_disabled']);
        $this->assertTrue($result['cancellation_disabled']);
        $this->assertFalse($result['live_supplier_call_allowed']);
        $this->assertContains('public_auto_pnr_disabled', $result['warnings']);
    }

    public function test_admin_confirmation_makes_eligible_false_but_can_attempt_when_structurally_ready(): void
    {
        config([
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
        ]);

        $booking = $this->booking53Style($this->approvalMetaOverrides());

        $result = app(SabreControlledPnrReadiness::class)->evaluate($booking, [
            'require_admin_confirmation' => true,
            'admin_confirmation_provided' => false,
        ]);

        $this->assertFalse($result['eligible']);
        $this->assertContains('admin_confirmation_required', $result['blockers']);
        $this->assertSame('admin_confirmation_required', $result['reason_code']);
        $this->assertFalse($result['live_supplier_call_allowed']);
    }

    public function test_booking53_style_blocks_manual_review_until_approval(): void
    {
        config([
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
        ]);

        $booking = $this->booking53Style([
            'defer_supplier_booking_to_manual_review' => true,
            'supplier_pnr_deferred_reason' => SabreCertifiedRouteSelector::DEFER_REASON,
        ]);

        $before = app(SabreControlledPnrReadiness::class)->evaluate($booking);

        $this->assertTrue($before['has_usable_controlled_pnr_context']);
        $this->assertFalse($before['controlled_pnr_manual_review_approved']);
        $this->assertFalse($before['can_attempt_supplier_pnr']);
        $this->assertContains('manual_review_required', $before['blockers']);
        $this->assertSame('manual_review_required', $before['reason_code']);

        $booking->forceFill([
            'meta' => array_merge(is_array($booking->meta) ? $booking->meta : [], $this->approvalMetaOverrides()),
        ])->save();

        $after = app(SabreControlledPnrReadiness::class)->evaluate($booking->fresh(['passengers', 'contact', 'supplierBookings', 'tickets']));

        $this->assertTrue($after['controlled_pnr_manual_review_approved']);
        $this->assertNotContains('manual_review_required', $after['blockers']);
        $this->assertContains('manual_review_approved', $after['warnings']);
        $this->assertTrue($after['has_revalidation_context']);
        $this->assertTrue($after['can_attempt_supplier_pnr']);
    }

    public function test_booking53_style_with_fare_gate_blocks_until_f9e_acceptance(): void
    {
        config([
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
        ]);

        $booking = $this->booking53StyleWithFareChangeGate(array_merge(
            $this->approvalMetaOverrides(),
            ['defer_supplier_booking_to_manual_review' => true],
        ));

        $before = app(SabreControlledPnrReadiness::class)->evaluate($booking);

        $this->assertFalse($before['controlled_pnr_fare_change_accepted']);
        $this->assertContains('offer_refresh_customer_confirmation_required', $before['blockers']);
        $this->assertFalse($before['can_attempt_supplier_pnr']);

        $booking->forceFill([
            'meta' => array_merge(
                is_array($booking->meta) ? $booking->meta : [],
                $this->fareChangeAcceptanceMetaForBooking($booking),
            ),
        ])->save();

        $after = app(SabreControlledPnrReadiness::class)->evaluate($booking->fresh(['passengers', 'contact', 'supplierBookings', 'tickets']));

        $this->assertTrue($after['controlled_pnr_fare_change_accepted']);
        $this->assertNotContains('offer_refresh_customer_confirmation_required', $after['blockers']);
        $this->assertTrue($after['can_attempt_supplier_pnr']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function approvalMetaOverrides(): array
    {
        $booking = Booking::factory()->make(['reference_code' => 'PAR-TESTREF']);

        return [
            SabreControlledPnrManualReviewApproval::META_KEY => app(SabreControlledPnrManualReviewApproval::class)
                ->buildApprovalRecord($booking, 'controlled_burn_in', 'platform_ops'),
        ];
    }

    public function test_revalidation_expired_blocks(): void
    {
        $booking = $this->sabreBookingBase([
            'meta' => array_merge($this->baseMeta(), [
                'offer_freshness' => [
                    'revalidation_status' => 'expired',
                ],
            ]),
        ]);

        $result = app(SabreControlledPnrReadiness::class)->evaluate($booking);

        $this->assertContains('revalidation_expired', $result['blockers']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseMeta(): array
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();

        return [
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $conn->id,
            'booking_method' => 'pay_later_booking_request',
            'confirmation_method' => 'pay_later_booking_request',
            'search_criteria' => ['trip_type' => 'one_way', 'origin' => 'LHE', 'destination' => 'JED'],
            'normalized_offer_snapshot' => [
                'validating_carrier' => 'GF',
                'segments' => [
                    [
                        'origin' => 'LHE',
                        'destination' => 'BAH',
                        'carrier' => 'GF',
                        'booking_class' => 'W',
                        'fare_basis_code' => 'WDLIT3PK',
                    ],
                    [
                        'origin' => 'BAH',
                        'destination' => 'JED',
                        'carrier' => 'GF',
                        'booking_class' => 'W',
                        'fare_basis_code' => 'WDLIT3PK',
                    ],
                ],
            ],
        ];
    }

    public function test_retrieve_after_create_available_when_pnr_present(): void
    {
        $booking = $this->sabreBookingBase(['pnr' => 'UNGKWK']);

        $this->assertTrue(app(SabreControlledPnrReadiness::class)->canRetrieveAfterCreate($booking));
        $result = app(SabreControlledPnrReadiness::class)->evaluate($booking);
        $this->assertTrue($result['retrieve_after_create_available']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function sabreBookingBase(array $overrides = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();

        $booking = Booking::factory()->create(array_merge([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'unpaid',
            'confirmation_method' => 'pay_later_booking_request',
            'meta' => array_merge($this->baseMeta(), is_array($overrides['meta'] ?? null) ? $overrides['meta'] : []),
        ], array_diff_key($overrides, ['meta' => true])));

        if (! $booking->passengers()->exists()) {
            BookingPassenger::factory()->for($booking)->create([
                'passenger_index' => 0,
                'is_lead_passenger' => true,
                'first_name' => 'Test',
                'last_name' => 'Passenger',
                'date_of_birth' => now()->subYears(30)->toDateString(),
                'gender' => 'male',
                'passenger_type' => 'adult',
                'passport_number' => 'AB1234567',
                'passport_expiry_date' => now()->addYears(2)->toDateString(),
            ]);
        }

        if ($booking->contact === null) {
            BookingContact::query()->create([
                'booking_id' => $booking->id,
                'email' => 'guest@example.test',
                'phone' => '+923001234567',
            ]);
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $meta[SabreSafeRefreshContext::META_KEY] = app(SabreSafeRefreshContext::class)->buildFromCheckout($snapshot, [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'JED',
            'depart_date' => '2026-07-29',
            'adults' => 1,
        ], [
            'checkout_search_id' => 'controlled-readiness-search',
            'checkout_offer_id' => 'controlled-readiness-offer',
            'supplier_total' => 100.0,
            'supplier_currency' => 'PKR',
        ]);
        $booking->forceFill(['meta' => $meta])->save();

        return $booking->fresh(['passengers', 'contact', 'supplierBookings', 'tickets', 'fareBreakdown']);
    }
}
