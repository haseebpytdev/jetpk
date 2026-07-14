<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPassenger;
use App\Services\Suppliers\Sabre\Gds\SabreSelectedOfferRevalidationGate;
use App\Support\FlightSearch\SabreOfferFreshness;
use App\Support\PublicBooking;
use Carbon\CarbonImmutable;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sprint 11K-F — offer freshness, stale guard, selected-offer revalidation gate.
 */
class SabreOfferFreshnessPhase11KFTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ota.offer_freshness.refresh_due_seconds' => 300,
            'ota.offer_freshness.stale_after_seconds' => 600,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => false,
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.refresh_offer_before_public_pnr' => false,
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_initial_search_results_data_does_not_include_revalidation_calls(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();

        $searchId = $this->storeSabreSearchPayload(now()->toIso8601String());
        $response = $this->getJson('/flights/results/data?search_id='.$searchId);

        $response->assertOk();
        $response->assertJsonPath('search_freshness.offer_freshness_status', 'fresh');
        $this->assertArrayHasKey('search_created_at', $response->json('search_freshness'));
        Http::assertNothingSent();
    }

    public function test_sabre_offer_in_results_has_freshness_metadata(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $searchId = $this->storeSabreSearchPayload(now()->subMinutes(6)->toIso8601String());
        $response = $this->getJson('/flights/results/data?search_id='.$searchId);

        $response->assertOk();
        $offers = $response->json('offers');
        $this->assertNotEmpty($offers);
        $sabre = collect($offers)->first(fn (array $o) => ($o['supplier_provider'] ?? '') === 'sabre');
        $this->assertIsArray($sabre);
        $this->assertArrayHasKey('offer_freshness', $sabre);
        $this->assertSame('refresh_due', $sabre['offer_freshness']['offer_freshness_status'] ?? null);
        $this->assertArrayNotHasKey('raw_payload', $sabre);
    }

    public function test_five_minute_threshold_marks_refresh_due(): void
    {
        $freshness = app(SabreOfferFreshness::class);
        $meta = $freshness->buildSearchFreshnessMeta([
            'created_at' => now()->subSeconds(301)->toIso8601String(),
        ]);

        $this->assertSame(SabreOfferFreshness::STATUS_REFRESH_DUE, $meta['offer_freshness_status']);
    }

    public function test_ten_minute_threshold_blocks_checkout_transition(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $offer = $this->sabreOfferFixture();
        $searchId = $this->storeSabreSearchPayload(now()->subMinutes(11)->toIso8601String(), $offer);

        $gate = app(SabreSelectedOfferRevalidationGate::class)->evaluateCheckoutTransition(
            $agency,
            $offer,
            $this->searchCriteria(),
            $searchId,
            Cache::get('flight_search:'.$searchId),
        );

        $this->assertFalse($gate['allowed']);
        $this->assertSame('offer_stale_before_checkout', $gate['block_code']);
    }

    public function test_stale_offer_blocks_passengers_get(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $offer = $this->sabreOfferFixture();
        $searchId = $this->storeSabreSearchPayload(now()->subMinutes(11)->toIso8601String(), $offer);

        $response = $this->get(route('booking.passengers', [
            'flight_id' => $offer['id'],
            'offer_id' => $offer['id'],
            'search_id' => $searchId,
            'from' => 'LHE',
            'to' => 'DXB',
            'depart' => '2026-05-30',
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
        ]));

        $response->assertRedirect();
        $response->assertSessionHasErrors('flight_id');
        $message = (string) $response->getSession()->get('errors')->first('flight_id');
        $this->assertStringNotContainsStringIgnoringCase('UC', $message);
        $this->assertStringNotContainsStringIgnoringCase('NO FARES', $message);
        $this->assertStringContainsString('refreshed', strtolower($message));
    }

    public function test_high_risk_cached_offer_without_live_revalidation_blocks_checkout(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $offer = $this->sabreOfferFixture();
        $offer['segments'][0]['booking_class'] = '';
        $searchId = $this->storeSabreSearchPayload(now()->subMinutes(2)->toIso8601String(), $offer);

        $gate = app(SabreSelectedOfferRevalidationGate::class)->evaluateCheckoutTransition(
            $agency,
            $offer,
            $this->searchCriteria(),
            $searchId,
            Cache::get('flight_search:'.$searchId),
        );

        $this->assertFalse($gate['allowed']);
        $this->assertSame('selected_offer_revalidation_required', $gate['block_code']);
    }

    public function test_booking_submit_blocks_stale_offer_before_pnr(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();

        $booking = $this->sabreDraftBooking();
        $searchId = $this->storeSabreSearchPayload(now()->subMinutes(12)->toIso8601String(), $booking->meta['flight_offer_snapshot']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['checkout_search_id'] = $searchId;
        $booking->forceFill(['meta' => $meta])->save();

        $response = $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later']);

        $response->assertRedirect(route('booking.review'));
        $response->assertSessionHasErrors('booking');
        Http::assertNothingSent();

        $booking->refresh();
        $this->assertSame(BookingStatus::Draft, $booking->status);
        $this->assertSame(
            SabreOfferFreshness::DIAG_OFFER_STALE_BEFORE_CHECKOUT,
            data_get($booking->meta, 'sabre_checkout_freshness_block.classification'),
        );
    }

    public function test_fresh_offer_allows_checkout_without_live_revalidation(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $offer = $this->sabreOfferFixture();
        $searchId = $this->storeSabreSearchPayload(now()->subMinutes(1)->toIso8601String(), $offer);

        $gate = app(SabreSelectedOfferRevalidationGate::class)->evaluateCheckoutTransition(
            $agency,
            $offer,
            $this->searchCriteria(),
            $searchId,
            Cache::get('flight_search:'.$searchId),
        );

        $this->assertTrue($gate['allowed']);
    }

    public function test_missing_rbd_remains_blocked_at_checkout(): void
    {
        $freshness = app(SabreOfferFreshness::class);
        $offer = $this->sabreOfferFixture();
        $offer['segments'][0]['booking_class'] = '';

        $reasons = $freshness->assessHighRiskReasons($offer, 120);
        $this->assertContains('missing_rbd', $reasons);
    }

    public function test_stamp_booking_meta_after_successful_offer_refresh_accepts_carbon_immutable(): void
    {
        $freshness = app(SabreOfferFreshness::class);
        $immutable = CarbonImmutable::parse('2026-06-12T10:15:30+00:00');
        $expectedIso = $immutable->toIso8601String();

        $stamped = $freshness->stampBookingMetaAfterSuccessfulOfferRefresh([], $immutable);

        $this->assertSame($expectedIso, $stamped['offer_validated_at']);
        $this->assertSame($expectedIso, $stamped['validated_at']);
        $this->assertSame($expectedIso, $stamped['selected_offer_last_revalidated_at']);
        $this->assertSame($expectedIso, $stamped['last_revalidated_at']);
        $this->assertSame('success', $stamped['selected_offer_revalidation_status']);
        $this->assertSame('refreshed', $stamped['offer_refresh_status']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function sabreOfferFixture(): array
    {
        return [
            'id' => '11kf-offer-1',
            'offer_id' => '11kf-offer-1',
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => 1,
            'validating_carrier' => 'EK',
            'airline_code' => 'EK',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_at' => '2026-05-30T08:00:00Z',
            'arrive_at' => '2026-05-30T14:00:00Z',
            'final_customer_price' => 150000,
            'currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'fare_breakdown' => [
                'base_fare' => 120000,
                'taxes' => 20000,
                'supplier_total' => 140000,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'carrier' => 'EK',
                    'airline_code' => 'EK',
                    'flight_number' => '601',
                    'booking_class' => 'Y',
                    'departure_at' => '2026-05-30T08:00:00Z',
                    'arrival_at' => '2026-05-30T14:00:00Z',
                ],
            ],
            'raw_payload' => [
                'sabre_shop_context' => [
                    'leg_refs' => ['0'],
                    'schedule_refs' => ['0'],
                    'fare_basis_codes' => ['YLEOPK1'],
                    'validating_carrier' => 'EK',
                ],
                'sabre_booking_context' => [
                    'has_revalidation_linkage' => true,
                    'leg_refs' => ['0'],
                    'schedule_refs' => ['0'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function searchCriteria(): array
    {
        return [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-05-30',
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'source_channel' => 'public_guest',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $offer
     */
    protected function storeSabreSearchPayload(string $createdAt, ?array $offer = null): string
    {
        $searchId = (string) Str::uuid();
        $offer = $offer ?? $this->sabreOfferFixture();

        Cache::put('flight_search:'.$searchId, [
            'search_id' => $searchId,
            'criteria' => $this->searchCriteria(),
            'offers' => [$offer],
            'warnings' => [],
            'created_at' => $createdAt,
            'search_created_at' => $createdAt,
        ], 1800);

        return $searchId;
    }

    protected function sabreDraftBooking(): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $offer = $this->sabreOfferFixture();
        $offer['search_criteria'] = $this->searchCriteria();

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'flight_offer_snapshot' => $offer,
                'normalized_offer_snapshot' => $offer,
                'validated_offer_snapshot' => $offer,
                'search_criteria' => $this->searchCriteria(),
                'checkout_search_id' => '',
                'protection_mode' => 'instant_payment_required',
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
            'email' => '11kf@test.example',
            'phone' => '+923001234567',
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 120000,
            'taxes' => 20000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 150000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        return $booking;
    }
}
