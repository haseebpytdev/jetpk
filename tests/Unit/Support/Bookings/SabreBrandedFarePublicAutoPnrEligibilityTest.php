<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Support\Bookings\SabreBrandedFarePublicAutoPnrEligibility;
use App\Support\Bookings\SabreSafeRefreshContext;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SabreBrandedFarePublicAutoPnrEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->activateSabreConnection();
        $this->configureEligibleBaseline();
        Cache::flush();
        Http::fake();
    }

    public function test_eligible_when_all_conditions_true_in_config_override(): void
    {
        $booking = $this->brandedFareConnectingBooking();

        $result = app(SabreBrandedFarePublicAutoPnrEligibility::class)->evaluate($booking);

        $this->assertTrue($result['eligible']);
        $this->assertSame(SabreBrandedFarePublicAutoPnrEligibility::REASON_ELIGIBLE, $result['reason_code']);
        $this->assertSame([], $result['failed_conditions']);
        $this->assertSame('FL', $result['selected_brand_code']);
        $this->assertSame(SabreBookingPayloadBuilder::DEFAULT_AIRPRICE_BRAND_SHAPE_SELECTOR, $result['brand_shape']);
        $this->assertSame('GF→GF', $result['carrier_chain']);
        $this->assertSame('pay_later_booking_request', $result['payment_mode']);
        $this->assertFalse($result['ticketing_enabled']);
        $this->assertTrue($result['public_flag_enabled']);
        $this->assertTrue($result['auto_pnr_flag_enabled']);
        $this->assertFalse($result['live_supplier_call_attempted']);

        Http::assertNothingSent();
    }

    public function test_blocked_when_public_flag_false(): void
    {
        config(['suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false]);
        $booking = $this->brandedFareConnectingBooking();

        $result = app(SabreBrandedFarePublicAutoPnrEligibility::class)->evaluate($booking);

        $this->assertFalse($result['eligible']);
        $this->assertContains('public_flag_enabled', $result['failed_conditions']);
        Http::assertNothingSent();
    }

    public function test_blocked_when_auto_pnr_flag_false(): void
    {
        config(['suppliers.sabre.verified_multiseg_auto_pnr_enabled' => false]);
        $booking = $this->brandedFareConnectingBooking();

        $result = app(SabreBrandedFarePublicAutoPnrEligibility::class)->evaluate($booking);

        $this->assertFalse($result['eligible']);
        $this->assertContains('auto_pnr_flag_enabled', $result['failed_conditions']);
        Http::assertNothingSent();
    }

    public function test_blocked_when_ticketing_true(): void
    {
        config(['suppliers.sabre.ticketing_enabled' => true]);
        $booking = $this->brandedFareConnectingBooking();

        $result = app(SabreBrandedFarePublicAutoPnrEligibility::class)->evaluate($booking);

        $this->assertFalse($result['eligible']);
        $this->assertContains('ticketing_disabled', $result['failed_conditions']);
        $this->assertTrue($result['ticketing_enabled']);
        Http::assertNothingSent();
    }

    public function test_blocked_when_no_selected_fare_family(): void
    {
        $booking = $this->brandedFareConnectingBooking();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        unset($meta['selected_fare_family_option'], $meta['fare_option_key']);
        $booking->forceFill(['meta' => $meta])->save();

        $result = app(SabreBrandedFarePublicAutoPnrEligibility::class)->evaluate($booking->fresh(['passengers', 'contact']));

        $this->assertFalse($result['eligible']);
        $this->assertContains('selected_fare_family_present', $result['failed_conditions']);
        Http::assertNothingSent();
    }

    public function test_blocked_when_brand_shape_is_not_object_content(): void
    {
        config([
            'suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled' => true,
            'suppliers.sabre.branded_fares_airprice_brand_shape_compare_variant' => 'string_array',
        ]);
        $booking = $this->brandedFareConnectingBooking();

        $result = app(SabreBrandedFarePublicAutoPnrEligibility::class)->evaluate($booking);

        $this->assertFalse($result['eligible']);
        $this->assertContains('brand_shape_object_content', $result['failed_conditions']);
        Http::assertNothingSent();
    }

    public function test_blocked_for_mixed_carrier(): void
    {
        $booking = $this->brandedFareConnectingBooking();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $snapshot['segments'][1]['carrier'] = 'SV';
        $meta['normalized_offer_snapshot'] = $snapshot;
        $meta[SabreSafeRefreshContext::META_KEY] = app(SabreSafeRefreshContext::class)->buildFromCheckout($snapshot, [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'JED',
            'depart_date' => '2026-07-29',
            'adults' => 1,
        ], [
            'checkout_search_id' => 'mixed-carrier-search',
            'checkout_offer_id' => 'mixed-carrier-offer',
            'supplier_total' => 100.0,
            'supplier_currency' => 'PKR',
        ]);
        $booking->forceFill(['meta' => $meta])->save();

        $result = app(SabreBrandedFarePublicAutoPnrEligibility::class)->evaluate($booking->fresh(['passengers', 'contact']));

        $this->assertFalse($result['eligible']);
        $this->assertContains('no_mixed_interline_carrier', $result['failed_conditions']);
        Http::assertNothingSent();
    }

    public function test_blocked_for_booking_43_or_46(): void
    {
        foreach ([43, 46] as $blockedId) {
            $booking = $this->brandedFareConnectingBooking();
            $booking->id = $blockedId;

            $result = app(SabreBrandedFarePublicAutoPnrEligibility::class)->evaluate($booking);

            $this->assertFalse($result['eligible']);
            $this->assertContains('not_blocked_booking_id', $result['failed_conditions']);
            $this->assertSame('blocked_booking_id_43_or_46', $result['reason_code']);
        }

        Http::assertNothingSent();
    }

    public function test_blocked_for_card_online_payment_mode(): void
    {
        $booking = $this->brandedFareConnectingBooking([
            'confirmation_method' => 'online_card',
            'meta' => array_merge(
                is_array($this->baseBrandedMeta()) ? $this->baseBrandedMeta() : [],
                ['confirmation_method' => 'online_card', 'booking_method' => 'online_card'],
            ),
        ]);

        $result = app(SabreBrandedFarePublicAutoPnrEligibility::class)->evaluate($booking);

        $this->assertFalse($result['eligible']);
        $this->assertContains('payment_mode_manual', $result['failed_conditions']);
        $this->assertSame('online_card', $result['payment_mode']);
        Http::assertNothingSent();
    }

    public function test_same_carrier_chain_passes_for_single_carrier_pk(): void
    {
        $booking = $this->brandedFareBookingWithCarriers(['PK'], 1);

        $result = app(SabreBrandedFarePublicAutoPnrEligibility::class)->evaluate($booking);

        $this->assertTrue($result['condition_results']['same_carrier_chain']);
        $this->assertSame('PK', $result['carrier_chain']);
        Http::assertNothingSent();
    }

    public function test_same_carrier_chain_passes_for_pk_pk_connecting(): void
    {
        $booking = $this->brandedFareBookingWithCarriers(['PK', 'PK'], 2);

        $result = app(SabreBrandedFarePublicAutoPnrEligibility::class)->evaluate($booking);

        $this->assertTrue($result['condition_results']['same_carrier_chain']);
        $this->assertSame('PK→PK', $result['carrier_chain']);
        Http::assertNothingSent();
    }

    public function test_same_carrier_chain_passes_for_gf_gf_connecting(): void
    {
        $booking = $this->brandedFareConnectingBooking();

        $result = app(SabreBrandedFarePublicAutoPnrEligibility::class)->evaluate($booking);

        $this->assertTrue($result['condition_results']['same_carrier_chain']);
        $this->assertSame('GF→GF', $result['carrier_chain']);
        Http::assertNothingSent();
    }

    public function test_same_carrier_chain_fails_for_pk_gf_mixed(): void
    {
        $booking = $this->brandedFareBookingWithCarriers(['PK', 'GF'], 2);

        $result = app(SabreBrandedFarePublicAutoPnrEligibility::class)->evaluate($booking);

        $this->assertFalse($result['condition_results']['same_carrier_chain']);
        $this->assertContains('same_carrier_chain', $result['failed_conditions']);
        $this->assertContains('no_mixed_interline_carrier', $result['failed_conditions']);
        Http::assertNothingSent();
    }

    public function test_booking_51_like_single_pk_fails_only_when_flags_off(): void
    {
        config([
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => false,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
        ]);
        $booking = $this->brandedFareBookingWithCarriers(['PK'], 1);

        $result = app(SabreBrandedFarePublicAutoPnrEligibility::class)->evaluate($booking);

        $this->assertFalse($result['eligible']);
        $this->assertSame('auto_pnr_flag_disabled', $result['reason_code']);
        $this->assertSame('PK', $result['carrier_chain']);
        $this->assertSame('FL', $result['selected_brand_code']);
        $this->assertSame(['auto_pnr_flag_enabled', 'public_flag_enabled'], $result['failed_conditions']);
        Http::assertNothingSent();
    }

    public function test_inspect_command_outputs_required_fields(): void
    {
        $booking = $this->brandedFareBookingWithCarriers(['PK'], 1);
        config([
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => false,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
        ]);

        $this->artisan('sabre:inspect-public-auto-pnr-eligibility', [
            '--booking' => (string) $booking->id,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('live_supplier_call_attempted=false')
            ->expectsOutputToContain('eligible=false')
            ->expectsOutputToContain('reason_code=auto_pnr_flag_disabled')
            ->expectsOutputToContain('selected_brand_code=FL')
            ->expectsOutputToContain('brand_shape=object_content')
            ->expectsOutputToContain('carrier_chain=PK')
            ->expectsOutputToContain('failed_conditions=["auto_pnr_flag_enabled","public_flag_enabled"]');

        Http::assertNothingSent();
    }

    public function test_to_safe_meta_summary_strips_forbidden_keys(): void
    {
        $service = app(SabreBrandedFarePublicAutoPnrEligibility::class);
        $summary = $service->toSafeMetaSummary([
            'eligible' => false,
            'reason_code' => 'auto_pnr_flag_disabled',
            'failed_conditions' => ['auto_pnr_flag_enabled'],
            'selected_brand_code' => 'FL',
            'brand_shape' => SabreBookingPayloadBuilder::DEFAULT_AIRPRICE_BRAND_SHAPE_SELECTOR,
            'carrier_chain' => 'PK',
            'payment_mode' => 'pay_later_booking_request',
            'ticketing_enabled' => false,
            'public_flag_enabled' => false,
            'auto_pnr_flag_enabled' => false,
            'booking_id' => 99,
            'live_supplier_call_attempted' => false,
            'condition_results' => ['auto_pnr_flag_enabled' => false],
        ]);

        $this->assertArrayNotHasKey('condition_results', $summary);
        $this->assertArrayNotHasKey('booking_id', $summary);
        $this->assertFalse($summary['live_supplier_call_attempted']);
        $this->assertArrayHasKey('evaluated_at', $summary);
    }

    public function test_eligible_summary_maps_reason_to_pending_enablement(): void
    {
        $service = app(SabreBrandedFarePublicAutoPnrEligibility::class);
        $summary = $service->toSafeMetaSummary([
            'eligible' => true,
            'reason_code' => SabreBrandedFarePublicAutoPnrEligibility::REASON_ELIGIBLE,
            'failed_conditions' => [],
            'selected_brand_code' => 'FL',
            'brand_shape' => SabreBookingPayloadBuilder::DEFAULT_AIRPRICE_BRAND_SHAPE_SELECTOR,
            'carrier_chain' => 'PK',
            'payment_mode' => 'pay_later_booking_request',
            'ticketing_enabled' => false,
            'public_flag_enabled' => true,
            'auto_pnr_flag_enabled' => true,
        ]);

        $this->assertTrue($summary['eligible']);
        $this->assertSame(SabreBrandedFarePublicAutoPnrEligibility::REASON_ELIGIBLE_PENDING, $summary['reason_code']);
    }

    public function test_persist_checkout_evaluation_writes_meta_and_logs(): void
    {
        Log::spy();
        config([
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => false,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
        ]);
        $booking = $this->brandedFareBookingWithCarriers(['PK'], 1);

        $summary = app(SabreBrandedFarePublicAutoPnrEligibility::class)->persistCheckoutEvaluation($booking);

        $meta = is_array($booking->fresh()->meta) ? $booking->fresh()->meta : [];
        $stored = is_array($meta[SabreBrandedFarePublicAutoPnrEligibility::META_KEY] ?? null)
            ? $meta[SabreBrandedFarePublicAutoPnrEligibility::META_KEY]
            : [];
        $this->assertSame($summary, $stored);
        $this->assertFalse($stored['eligible']);
        $this->assertSame('auto_pnr_flag_disabled', $stored['reason_code']);
        $this->assertArrayNotHasKey('condition_results', $stored);

        Log::shouldHaveReceived('info')
            ->with(
                SabreBrandedFarePublicAutoPnrEligibility::LOG_EVENT,
                \Mockery::on(function (array $context) use ($booking): bool {
                    return ($context['booking_id'] ?? null) === $booking->id
                        && ($context['eligible'] ?? null) === false
                        && ($context['live_supplier_call_attempted'] ?? null) === false;
                })
            );

        Http::assertNothingSent();
    }

    public function test_inspect_command_shows_stored_meta_when_present(): void
    {
        $booking = $this->brandedFareBookingWithCarriers(['PK'], 1);
        config([
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => false,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
        ]);
        app(SabreBrandedFarePublicAutoPnrEligibility::class)->persistCheckoutEvaluation($booking);

        $this->artisan('sabre:inspect-public-auto-pnr-eligibility', [
            '--booking' => (string) $booking->id,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('[stored_eligibility]')
            ->expectsOutputToContain('reason_code=auto_pnr_flag_disabled')
            ->expectsOutputToContain('selected_brand_code=FL');

        Http::assertNothingSent();
    }

    public function test_inspect_command_reevaluate_shows_live_section(): void
    {
        $booking = $this->brandedFareBookingWithCarriers(['PK'], 1);
        app(SabreBrandedFarePublicAutoPnrEligibility::class)->persistCheckoutEvaluation($booking);

        $this->artisan('sabre:inspect-public-auto-pnr-eligibility', [
            '--booking' => (string) $booking->id,
            '--reevaluate' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('[stored_eligibility]')
            ->expectsOutputToContain('[live_evaluation]');

        Http::assertNothingSent();
    }

    protected function configureEligibleBaseline(): void
    {
        config([
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
            'suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled' => false,
            'suppliers.sabre.cpnr_iati_style_certified_gds_enabled' => true,
            'suppliers.sabre.certified_route_selector_public_checkout_enabled' => true,
        ]);
    }

    protected function activateSabreConnection(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $conn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://api.cert.platform.sabre.com',
            'credentials' => [
                'client_id' => 'cid',
                'client_secret' => 'sec',
                'pcc' => 'TEST',
                'pseudo_city_code' => 'TEST',
                'target_city' => 'TEST',
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseBrandedMeta(): array
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();

        return [
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $conn->id,
            'offer_validation_status' => 'valid',
            'confirmation_method' => 'pay_later_booking_request',
            'booking_method' => 'pay_later_booking_request',
            'fare_option_key' => 'fl-pi3',
            'selected_fare_family_option' => [
                'brand_code' => 'FL',
                'brand_name' => 'FREEDOM',
                'fare_option_key' => 'fl-pi3',
                'baggage' => '30 KG',
                'cabin' => 'Economy',
                'booking_class' => 'V',
                'fare_basis' => 'VOWFL',
            ],
            'search_criteria' => ['trip_type' => 'one_way'],
            'normalized_offer_snapshot' => [
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => $conn->id,
                'validating_carrier' => 'GF',
                'segments' => [
                    [
                        'origin' => 'LHE',
                        'destination' => 'BAH',
                        'carrier' => 'GF',
                        'flight_number' => '767',
                        'booking_class' => 'V',
                        'fare_basis_code' => 'VOWFL',
                        'departure_at' => '2026-07-29T22:00:00',
                        'arrival_at' => '2026-07-30T01:55:00',
                    ],
                    [
                        'origin' => 'BAH',
                        'destination' => 'JED',
                        'carrier' => 'GF',
                        'flight_number' => '171',
                        'booking_class' => 'V',
                        'fare_basis_code' => 'VOWFL',
                        'departure_at' => '2026-07-30T10:05:00',
                        'arrival_at' => '2026-07-30T12:30:00',
                    ],
                ],
                'raw_payload' => [
                    'distribution_channel' => 'GDS',
                    'sabre_shop_context' => [
                        'pricing_information_ref' => 'pi-3',
                        'offer_ref' => 'offer-51',
                        'itinerary_ref' => 'itin-1',
                        'validating_carrier' => 'GF',
                        'fare_basis_codes' => ['VOWFL', 'VOWFL'],
                    ],
                    'sabre_booking_context' => [
                        'itinerary_reference' => '1',
                        'pricing_information_index' => 0,
                        'booking_classes_by_segment' => ['V', 'V'],
                        'fare_basis_codes_by_segment' => ['VOWFL', 'VOWFL'],
                        'segment_slice_count' => 2,
                        'brand_code' => 'FL',
                        'selected_brand_code' => 'FL',
                    ],
                ],
                'fare_breakdown' => [
                    'supplier_total' => 100.0,
                    'currency' => 'PKR',
                    'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function brandedFareConnectingBooking(array $overrides = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        $metaOverride = is_array($overrides['meta'] ?? null) ? $overrides['meta'] : [];
        unset($overrides['meta']);

        $booking = Booking::factory()->create(array_merge([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'confirmation_method' => 'pay_later_booking_request',
            'meta' => array_merge($this->baseBrandedMeta(), $metaOverride),
        ], $overrides));

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $meta[SabreSafeRefreshContext::META_KEY] = app(SabreSafeRefreshContext::class)->buildFromCheckout($snapshot, [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'JED',
            'depart_date' => '2026-07-29',
            'adults' => 1,
        ], [
            'checkout_search_id' => 'bf7h-branded-search',
            'checkout_offer_id' => 'bf7h-branded-offer',
            'supplier_total' => 100.0,
            'supplier_currency' => 'PKR',
        ]);
        $booking->forceFill(['meta' => $meta])->save();

        BookingPassenger::factory()->for($booking)->create([
            'passenger_index' => 0,
            'is_lead_passenger' => true,
            'first_name' => 'Test',
            'last_name' => 'Passenger',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'gender' => 'male',
            'passenger_type' => 'adult',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'guest@example.test',
            'phone' => '+923001234567',
        ]);

        return $booking->fresh(['passengers', 'contact']);
    }

    /**
     * @param  list<string>  $carriers  Marketing carrier per segment
     * @param  array<string, mixed>  $overrides
     */
    protected function brandedFareBookingWithCarriers(array $carriers, int $segmentCount, array $overrides = []): Booking
    {
        $meta = $this->baseBrandedMeta();
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $template = is_array($snapshot['segments'][0] ?? null) ? $snapshot['segments'][0] : [];
        $segments = [];
        $origins = ['LHE', 'BAH'];
        $destinations = ['BAH', 'JED'];

        for ($i = 0; $i < $segmentCount; $i++) {
            $segments[] = array_merge($template, [
                'origin' => $origins[$i] ?? 'LHE',
                'destination' => $destinations[$i] ?? 'JED',
                'carrier' => $carriers[$i] ?? $carriers[0] ?? 'PK',
                'flight_number' => (string) (700 + $i),
            ]);
        }

        $snapshot['segments'] = $segments;
        $snapshot['validating_carrier'] = $carriers[0] ?? 'PK';
        $meta['normalized_offer_snapshot'] = $snapshot;

        return $this->brandedFareConnectingBooking(array_merge(['meta' => $meta], $overrides));
    }
}
