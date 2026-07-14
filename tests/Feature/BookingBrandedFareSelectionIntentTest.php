<?php

namespace Tests\Feature;

use App\Data\OfferValidationResultData;
use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Http\Controllers\Frontend\BookingController;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPassenger;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Services\Bookings\FareHoldService;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Suppliers\OfferValidationService;
use App\Support\Bookings\SabreBookingValidationManualRequestPolicy;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use App\Support\PublicBooking;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\Support\PublicBookingPassengersPayload;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class BookingBrandedFareSelectionIntentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function offerWithBrandedFares(string $departDate): array
    {
        $base = PublicCheckoutTestDoubles::searchOfferPayload($departDate);
        $base['supplier_total_source'] = 500;
        $base['final_customer_price'] = 150000;
        $base['fare_family'] = 'ECO LT';
        $base['branded_fares'] = [
            [
                'name' => 'Value',
                'brand_code' => 'VAL',
                'price_total' => 400,
                'currency' => 'USD',
                'pricing_information_index' => 0,
                'baggage_summary' => '20kg',
                'cabin' => 'Economy',
                'booking_classes_by_segment' => ['V'],
                'fare_basis_codes' => ['VLWOPPK1'],
            ],
            [
                'name' => 'Freedom',
                'brand_code' => 'FRD',
                'price_total' => 550,
                'currency' => 'USD',
                'pricing_information_index' => 1,
                'baggage_summary' => '30kg',
                'cabin' => 'Economy',
                'booking_classes_by_segment' => ['F'],
                'fare_basis_codes' => ['FLWOPPK1'],
            ],
            [
                'name' => 'Flexi',
                'brand_code' => 'FLX',
                'price_total' => 500,
                'currency' => 'USD',
                'pricing_information_index' => 2,
            ],
        ];

        return $base;
    }

    protected function bindCheckoutForSabreOffer(array $offer, string $departDate): void
    {
        $validated = PublicCheckoutTestDoubles::validatedNormalizedOffer($departDate);
        $result = new OfferValidationResultData(
            is_valid: true,
            status: 'valid',
            original_offer_id: PublicCheckoutTestDoubles::OFFER_ID,
            validated_offer: $validated,
            currency: 'PKR',
            meta: ['pricing_snapshot' => PublicCheckoutTestDoubles::pricingSnapshot()],
        );

        $ovs = Mockery::mock(OfferValidationService::class);
        $ovs->shouldReceive('validateSelectedOffer')->andReturn($result);
        $ovs->shouldReceive('pricingSnapshotForCachedOffer')->andReturn(PublicCheckoutTestDoubles::pricingSnapshot());
        App::instance(OfferValidationService::class, $ovs);

        $flightSearch = Mockery::mock(FlightSearchService::class);
        $flightSearch->shouldReceive('search')->andReturn([$offer]);
        $flightSearch->shouldReceive('searchWithMeta')->andReturn(['offers' => [$offer], 'warnings' => []]);
        App::instance(FlightSearchService::class, $flightSearch);
    }

    /**
     * @return array<string, mixed>
     */
    protected function freedomPassengersPostPayload(string $depart): array
    {
        return array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'search_id' => 'test-search-store',
                'fare_option_key' => 'frd-pi1',
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'trip_type' => 'one_way',
                'cabin' => 'economy',
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
                'email' => 'freedom.checkout@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        );
    }

    protected function submitPassengersWithFreedomFare(string $depart, array $offerOverrides = []): void
    {
        $offer = array_merge($this->offerWithBrandedFares($depart), $offerOverrides);
        $this->bindCheckoutForSabreOffer($offer, $depart);

        $this->get('/booking/passengers?flight_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&offer_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&search_id=test-search-store'
            .'&fare_option_key=frd-pi1'
            .'&from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0')
            ->assertOk();

        $this->post('/booking/passengers', $this->freedomPassengersPostPayload($depart))
            ->assertRedirect(route('booking.review'));
    }

    public function test_passengers_page_shows_selected_fare_family_when_selection_enabled(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.branded_fares_display_enabled', true);
        Config::set('suppliers.sabre.branded_fares_selection_enabled', true);

        $depart = now()->addWeek()->format('Y-m-d');
        $offer = $this->offerWithBrandedFares($depart);
        $this->bindCheckoutForSabreOffer($offer, $depart);

        $response = $this->get('/booking/passengers?flight_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&offer_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&search_id=test-search-store'
            .'&fare_option_key=val-pi0'
            .'&from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0');

        $response->assertOk();
        $response->assertSee('Selected fare family', false);
        $response->assertSee('Value', false);
        $response->assertSee('VAL', false);
        $response->assertSee('20 kg', false);
        $response->assertSee('VLWOPPK1', false);
        $response->assertSee('Approx.', false);
        $response->assertSee('PKR 120,000', false);
        $response->assertSee('Estimated selected fare', false);
        $response->assertSee('Final fare family and price will be confirmed during airline price validation.', false);
        $response->assertDontSee('Rs 116,199', false);
        $response->assertDontSee('ECO LT', false);
    }

    public function test_freedom_selection_shows_freedom_not_base_fare_family_on_checkout(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.branded_fares_display_enabled', true);
        Config::set('suppliers.sabre.branded_fares_selection_enabled', true);

        $depart = now()->addWeek()->format('Y-m-d');
        $offer = $this->offerWithBrandedFares($depart);
        $this->bindCheckoutForSabreOffer($offer, $depart);

        $response = $this->get('/booking/passengers?flight_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&offer_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&search_id=test-search-store'
            .'&fare_option_key=frd-pi1'
            .'&from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0');

        $response->assertOk();
        $response->assertSee('Freedom', false);
        $response->assertSee('FRD', false);
        $response->assertSee('30 kg', false);
        $response->assertSee('FLWOPPK1', false);
        $response->assertDontSee('ECO LT', false);
        $response->assertDontSee('VLWOPPK1', false);
        $response->assertSee('Estimated selected fare', false);
        $response->assertSee('Approx.', false);
        $response->assertSee('PKR 165,000', false);
        $response->assertDontSee('Rs 116,199', false);
    }

    public function test_invalid_fare_option_key_redirects_to_results_with_safe_message(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.branded_fares_display_enabled', true);
        Config::set('suppliers.sabre.branded_fares_selection_enabled', true);

        $depart = now()->addWeek()->format('Y-m-d');
        $offer = $this->offerWithBrandedFares($depart);
        $this->bindCheckoutForSabreOffer($offer, $depart);

        $response = $this->get('/booking/passengers?flight_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&offer_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&search_id=test-search-store'
            .'&fare_option_key=unknown-key'
            .'&from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0');

        $response->assertRedirect();
        $response->assertSessionHasErrors('fare_option_key');
    }

    public function test_base_booking_without_fare_option_key_still_renders_passengers(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.branded_fares_display_enabled', true);
        Config::set('suppliers.sabre.branded_fares_selection_enabled', true);

        $depart = now()->addWeek()->format('Y-m-d');
        $offer = $this->offerWithBrandedFares($depart);
        $this->bindCheckoutForSabreOffer($offer, $depart);

        $response = $this->get('/booking/passengers?flight_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&offer_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&search_id=test-search-store'
            .'&from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0');

        $response->assertOk();
        $response->assertSee('Passenger', false);
    }

    public function test_display_only_gate_does_not_persist_fare_option_key_intent(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.branded_fares_display_enabled', true);
        Config::set('suppliers.sabre.branded_fares_selection_enabled', false);

        $depart = now()->addWeek()->format('Y-m-d');
        $offer = $this->offerWithBrandedFares($depart);
        $this->bindCheckoutForSabreOffer($offer, $depart);

        $response = $this->get('/booking/passengers?flight_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&offer_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&search_id=test-search-store'
            .'&fare_option_key=val-pi0'
            .'&from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0');

        $response->assertOk();
        $response->assertDontSee('Selected fare family', false);
        $this->assertFalse(FlightOfferDisplayPresenter::brandedFaresSelectionActive());
    }

    public function test_checkout_logs_request_received_and_resolved(): void
    {
        Log::spy();
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.branded_fares_display_enabled', true);
        Config::set('suppliers.sabre.branded_fares_selection_enabled', true);

        $depart = now()->addWeek()->format('Y-m-d');
        $offer = $this->offerWithBrandedFares($depart);
        $this->bindCheckoutForSabreOffer($offer, $depart);

        $response = $this->get('/booking/passengers?flight_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&offer_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&search_id=test-search-store'
            .'&fare_option_key=val-pi0'
            .'&from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0');

        $response->assertOk();

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'branded_fare_checkout_request_received'
                    && ($context['fare_option_key_present'] ?? false) === true
                    && ($context['fare_option_key'] ?? '') === 'val-pi0'
                    && ($context['selection_enabled'] ?? false) === true;
            });

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'branded_fare_option_key_resolved'
                    && ($context['fare_option_key'] ?? '') === 'val-pi0'
                    && ($context['brand_name'] ?? '') === 'Value';
            });
    }

    public function test_invalid_fare_option_key_logs_ignored_with_reason(): void
    {
        Log::spy();
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.branded_fares_display_enabled', true);
        Config::set('suppliers.sabre.branded_fares_selection_enabled', true);

        $depart = now()->addWeek()->format('Y-m-d');
        $offer = $this->offerWithBrandedFares($depart);
        $this->bindCheckoutForSabreOffer($offer, $depart);

        $response = $this->get('/booking/passengers?flight_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&offer_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&search_id=test-search-store'
            .'&fare_option_key=unknown-key'
            .'&from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0');

        $response->assertRedirect();

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'branded_fare_option_key_ignored'
                    && ($context['reason'] ?? '') === 'key_not_found_on_offer'
                    && is_array($context['available_option_keys_sample'] ?? null)
                    && in_array('val-pi0', $context['available_option_keys_sample'], true);
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function secondOfferWithBrandedFares(string $departDate): array
    {
        $base = $this->offerWithBrandedFares($departDate);
        $base['id'] = 'fixture-offer-2';
        $base['offer_id'] = 'fixture-offer-2';
        $base['branded_fares'][1]['baggage_summary'] = '40kg';
        $base['branded_fares'][1]['fare_basis_codes'] = ['BFRD9999'];
        $base['supplier_total_source'] = 600;
        $base['final_customer_price'] = 160000;

        return $base;
    }

    protected function bindCheckoutForTwoSabreOffers(array $offerA, array $offerB, string $departDate): string
    {
        $criteria = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => $departDate,
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ];
        $searchId = app(FlightSearchResultStore::class)->store($criteria, [$offerA, $offerB], []);

        $validated = PublicCheckoutTestDoubles::validatedNormalizedOffer($departDate);
        $result = new OfferValidationResultData(
            is_valid: true,
            status: 'valid',
            original_offer_id: PublicCheckoutTestDoubles::OFFER_ID,
            validated_offer: $validated,
            currency: 'PKR',
            meta: ['pricing_snapshot' => PublicCheckoutTestDoubles::pricingSnapshot()],
        );

        $ovs = Mockery::mock(OfferValidationService::class);
        $ovs->shouldReceive('validateSelectedOffer')->andReturn($result);
        $ovs->shouldReceive('pricingSnapshotForCachedOffer')->andReturn(PublicCheckoutTestDoubles::pricingSnapshot());
        App::instance(OfferValidationService::class, $ovs);

        $flightSearch = Mockery::mock(FlightSearchService::class);
        $flightSearch->shouldReceive('search')->andReturn([$offerA, $offerB]);
        $flightSearch->shouldReceive('searchWithMeta')->andReturn(['offers' => [$offerA, $offerB], 'warnings' => []]);
        App::instance(FlightSearchService::class, $flightSearch);

        return $searchId;
    }

    public function test_cross_offer_same_brand_key_resolves_within_selected_offer_only(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.branded_fares_display_enabled', true);
        Config::set('suppliers.sabre.branded_fares_selection_enabled', true);

        $depart = now()->addWeek()->format('Y-m-d');
        $offerA = $this->offerWithBrandedFares($depart);
        $offerB = $this->secondOfferWithBrandedFares($depart);
        $searchId = $this->bindCheckoutForTwoSabreOffers($offerA, $offerB, $depart);

        $responseA = $this->get('/booking/passengers?flight_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&offer_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&search_id='.$searchId
            .'&fare_option_key=frd-pi1'
            .'&from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0');
        $responseA->assertOk();
        $responseA->assertSee('30 kg', false);
        $responseA->assertSee('FLWOPPK1', false);
        $responseA->assertDontSee('40 kg', false);
        $responseA->assertDontSee('BFRD9999', false);

        $responseB = $this->get('/booking/passengers?flight_id=fixture-offer-2'
            .'&offer_id=fixture-offer-2'
            .'&search_id='.$searchId
            .'&fare_option_key=frd-pi1'
            .'&from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0');
        $responseB->assertOk();
        $responseB->assertSee('40 kg', false);
        $responseB->assertSee('BFRD9999', false);
        $responseB->assertDontSee('30 kg', false);
        $responseB->assertDontSee('FLWOPPK1', false);
    }

    public function test_freedom_checkout_fare_rules_sidebar_shows_selected_baggage_not_base(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.branded_fares_display_enabled', true);
        Config::set('suppliers.sabre.branded_fares_selection_enabled', true);

        $depart = now()->addWeek()->format('Y-m-d');
        $offer = $this->offerWithBrandedFares($depart);
        $offer['baggage'] = '0 KG';
        $this->bindCheckoutForSabreOffer($offer, $depart);

        $response = $this->get('/booking/passengers?flight_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&offer_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&search_id=test-search-store'
            .'&fare_option_key=frd-pi1'
            .'&from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0');

        $response->assertOk();
        $response->assertSee('30 kg', false);
        $response->assertSee('Airline &amp; fare rules', false);
        $response->assertDontSee('0 KG', false);
    }

    public function test_freedom_checkout_estimate_matches_selected_option_price_display(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.branded_fares_display_enabled', true);
        Config::set('suppliers.sabre.branded_fares_selection_enabled', true);

        $depart = now()->addWeek()->format('Y-m-d');
        $offer = $this->offerWithBrandedFares($depart);
        $this->bindCheckoutForSabreOffer($offer, $depart);

        $response = $this->get('/booking/passengers?flight_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&offer_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&search_id=test-search-store'
            .'&fare_option_key=frd-pi1'
            .'&from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0');

        $response->assertOk();
        $response->assertSee('Estimated selected fare', false);
        $response->assertSee('PKR 165,000', false);
        $content = $response->getContent();
        $this->assertNotFalse($content);
        $this->assertSame(2, substr_count($content, 'PKR 165,000'));
    }

    public function test_freedom_selection_preserved_in_booking_meta_after_passengers_post(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Config::set('suppliers.sabre.branded_fares_display_enabled', true);
        Config::set('suppliers.sabre.branded_fares_selection_enabled', true);

        $depart = now()->addWeek()->format('Y-m-d');
        $this->submitPassengersWithFreedomFare($depart);

        $booking = Booking::query()->first();
        $this->assertNotNull($booking);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $intent = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : [];
        $this->assertSame('Freedom', (string) ($intent['name'] ?? ''));
        $this->assertSame('frd-pi1', (string) ($meta['fare_option_key'] ?? ''));
    }

    public function test_review_shows_freedom_selected_fare_not_base_baggage_or_price(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Config::set('suppliers.sabre.branded_fares_display_enabled', true);
        Config::set('suppliers.sabre.branded_fares_selection_enabled', true);

        $depart = now()->addWeek()->format('Y-m-d');
        $this->submitPassengersWithFreedomFare($depart, ['baggage' => '0 KG']);

        $response = $this->get(route('booking.review'));
        $response->assertOk();
        $response->assertSee('Selected fare family', false);
        $response->assertSee('Freedom', false);
        $response->assertSee('FRD', false);
        $response->assertSee('30 kg', false);
        $response->assertSee('FLWOPPK1', false);
        $response->assertSee('Estimated selected fare', false);
        $response->assertSee('Approx.', false);
        $response->assertSee('PKR 165,000', false);
        $response->assertSee('Final fare family and price will be confirmed during airline price validation.', false);
        $response->assertDontSee('0 KG', false);
        $response->assertDontSee('Rs 116,199', false);
    }

    public function test_revalidate_checkout_before_confirmation_resolves_pricing_before_branded_total(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.branded_fares_display_enabled', true);
        Config::set('suppliers.sabre.branded_fares_selection_enabled', true);

        $depart = now()->addWeek()->format('Y-m-d');
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $validated = PublicCheckoutTestDoubles::validatedNormalizedOffer($depart);
        $validation = new OfferValidationResultData(
            is_valid: true,
            status: 'valid',
            original_offer_id: PublicCheckoutTestDoubles::OFFER_ID,
            validated_offer: $validated,
            currency: 'PKR',
            meta: ['pricing_snapshot' => PublicCheckoutTestDoubles::pricingSnapshot()],
        );

        $fareHold = Mockery::mock(FareHoldService::class);
        $fareHold->shouldReceive('requiresFinalRevalidation')->andReturn(true);
        $fareHold->shouldReceive('revalidateBeforeConfirmation')->andReturn($validation);
        App::instance(FareHoldService::class, $fareHold);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Duffel->value,
            'selected_fare_total' => 88602.00,
            'revalidated_fare_total' => 88602.00,
            'meta' => [
                'supplier_provider' => SupplierProvider::Duffel->value,
                'protection_mode' => 'instant_payment_required',
                'supplier_total' => 114999,
                'fare_option_key' => 'fl-pi3',
                'selected_fare_family_option' => [
                    'name' => 'FREEDOM',
                    'brand_code' => 'FL',
                    'displayed_price' => 88602.0,
                    'price_display' => 'Approx. PKR 88,602',
                    'baggage_summary' => '30 kg',
                    'booking_classes_by_segment' => ['V'],
                    'fare_basis_codes' => ['VOWFL/V'],
                ],
                'validated_offer_snapshot' => $validated->toArray(),
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 5000,
            'fees' => 0,
            'markup' => 3602,
            'discount' => 0,
            'total' => 88602,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        $attemptCountBefore = SupplierBookingAttempt::query()->count();

        $controller = app(BookingController::class);
        $method = new \ReflectionMethod($controller, 'revalidateCheckoutBeforeConfirmation');
        $method->setAccessible(true);
        $result = $method->invoke($controller, $booking->fresh());

        $this->assertSame('ok', $result['status']);
        $booking->refresh();
        $this->assertSame('88602.00', (string) $booking->selected_fare_total);
        $this->assertSame('88602.00', (string) $booking->revalidated_fare_total);
        $this->assertSame($attemptCountBefore, SupplierBookingAttempt::query()->count());
    }

    public function test_revalidate_checkout_before_confirmation_survives_missing_validation_pricing_snapshot(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $depart = now()->addWeek()->format('Y-m-d');
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $validated = PublicCheckoutTestDoubles::validatedNormalizedOffer($depart);
        $validation = new OfferValidationResultData(
            is_valid: true,
            status: 'valid',
            original_offer_id: PublicCheckoutTestDoubles::OFFER_ID,
            validated_offer: $validated,
            currency: 'PKR',
            meta: [],
        );

        $fareHold = Mockery::mock(FareHoldService::class);
        $fareHold->shouldReceive('requiresFinalRevalidation')->andReturn(true);
        $fareHold->shouldReceive('revalidateBeforeConfirmation')->andReturn($validation);
        App::instance(FareHoldService::class, $fareHold);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Duffel->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Duffel->value,
                'protection_mode' => 'instant_payment_required',
                'supplier_total' => 114999,
                'pricing_snapshot' => PublicCheckoutTestDoubles::pricingSnapshot(),
                'validated_offer_snapshot' => $validated->toArray(),
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 100000,
            'taxes' => 10000,
            'fees' => 4999,
            'markup' => 0,
            'discount' => 0,
            'total' => 114999,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        $controller = app(BookingController::class);
        $method = new \ReflectionMethod($controller, 'revalidateCheckoutBeforeConfirmation');
        $method->setAccessible(true);
        $result = $method->invoke($controller, $booking->fresh());

        $this->assertSame('ok', $result['status']);
        $booking->refresh();
        $this->assertNotNull($booking->fareBreakdown);
        $this->assertGreaterThan(0, (float) $booking->fareBreakdown->total);
    }

    public function test_confirmation_shows_freedom_selected_fare_details(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Mail::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Config::set('suppliers.sabre.branded_fares_display_enabled', true);
        Config::set('suppliers.sabre.branded_fares_selection_enabled', true);

        $depart = now()->addWeek()->format('Y-m-d');
        $this->submitPassengersWithFreedomFare($depart);

        $fareHold = Mockery::mock(FareHoldService::class);
        $fareHold->shouldReceive('requiresFinalRevalidation')->andReturn(false);
        App::instance(FareHoldService::class, $fareHold);

        $this->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'));

        $response = $this->get(route('booking.confirmation'));
        $response->assertOk();
        $response->assertSee('Selected fare family', false);
        $response->assertSee('Freedom', false);
        $response->assertSee('FRD', false);
        $response->assertSee('30 kg', false);
        $response->assertSee('FLWOPPK1', false);
        $response->assertSee('Estimated selected fare', false);
        $response->assertSee('PKR 165,000', false);
        $response->assertSee('Final fare family and price will be confirmed during airline price validation.', false);
    }

    public function test_base_booking_review_unchanged_without_fare_option_key(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Config::set('suppliers.sabre.branded_fares_display_enabled', true);
        Config::set('suppliers.sabre.branded_fares_selection_enabled', true);

        $depart = now()->addWeek()->format('Y-m-d');
        $offer = $this->offerWithBrandedFares($depart);
        $this->bindCheckoutForSabreOffer($offer, $depart);

        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'search_id' => 'test-search-store',
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'trip_type' => 'one_way',
                'cabin' => 'economy',
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertRedirect(route('booking.review'));

        $response = $this->get(route('booking.review'));
        $response->assertOk();
        $response->assertSee('Review your booking', false);
        $response->assertDontSee('Selected fare family', false);
        $response->assertSee('Rs 116,199', false);
    }

    public function test_review_falls_back_safely_when_meta_intent_missing(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');

        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertRedirect(route('booking.review'));

        $booking = Booking::query()->first();
        $this->assertNotNull($booking);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertNull($meta['selected_fare_family_option'] ?? null);

        $response = $this->get(route('booking.review'));
        $response->assertOk();
        $response->assertDontSee('Selected fare family', false);
        $expectedTotal = number_format((float) $booking->fareBreakdown?->total, 0);
        $response->assertSee('Rs '.$expectedTotal, false);
    }

    public function test_reaffirm_preserves_sticky_estimate_when_validated_offer_ratio_differs(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.branded_fares_display_enabled', true);
        Config::set('suppliers.sabre.branded_fares_selection_enabled', true);

        $depart = now()->addWeek()->format('Y-m-d');
        $offer = $this->offerWithBrandedFares($depart);
        $offer['supplier_total_source'] = 500;
        $offer['final_customer_price'] = 150000;

        $result = new OfferValidationResultData(
            is_valid: true,
            status: 'valid',
            original_offer_id: PublicCheckoutTestDoubles::OFFER_ID,
            validated_offer: PublicCheckoutTestDoubles::validatedNormalizedOffer($depart),
            currency: 'PKR',
            meta: ['pricing_snapshot' => PublicCheckoutTestDoubles::pricingSnapshot()],
        );

        $ovs = Mockery::mock(OfferValidationService::class);
        $ovs->shouldReceive('validateSelectedOffer')->andReturn($result);
        $ovs->shouldReceive('pricingSnapshotForCachedOffer')->andReturn(PublicCheckoutTestDoubles::pricingSnapshot());
        App::instance(OfferValidationService::class, $ovs);

        $presentedOffer = array_merge($offer, [
            'supplier_total_source' => 600,
            'final_customer_price' => 140000,
        ]);
        $flightSearch = Mockery::mock(FlightSearchService::class);
        $flightSearch->shouldReceive('search')->andReturn([$offer]);
        $flightSearch->shouldReceive('searchWithMeta')->andReturn(['offers' => [$presentedOffer], 'warnings' => []]);
        App::instance(FlightSearchService::class, $flightSearch);

        $response = $this->get('/booking/passengers?flight_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&offer_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&search_id=test-search-store'
            .'&fare_option_key=frd-pi1'
            .'&from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0');

        $response->assertOk();
        $response->assertSee('PKR 165,000', false);
        $response->assertDontSee('PKR 128,333', false);
    }

    public function test_review_shows_freedom_fare_when_validation_fails_without_raw_sabre_pointer(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Mail::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->configureSabreValidationFailureReviewSubmit();

        Config::set('suppliers.sabre.verified_multiseg_auto_pnr_enabled', true);
        $this->stubSabreValidationFailureHttp();

        $booking = $this->createSabreFreedomDraftBookingForValidationReview();

        $postResponse = $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later']);
        $postResponse->assertRedirect(route('booking.review'));
        $postResponse->assertSessionHasErrors('booking');

        $response = $this->withSession($postResponse->getSession()->all())
            ->get(route('booking.review'));
        $response->assertOk();
        $response->assertSee('Freedom', false);
        $response->assertSee('FRD', false);
        $response->assertSee('30kg', false);
        $response->assertDontSee('CreatePassengerNameRecordRQ', false);
        $response->assertDontSee('/AirPrice/0/message', false);
        $response->assertDontSee('object instance has properties', false);
    }

    public function test_confirm_booking_request_succeeds_when_sabre_validation_fails_and_auto_pnr_off(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Mail::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->configureSabreValidationFailureReviewSubmit();

        Log::spy();
        $this->stubSabreValidationFailureHttp();

        $booking = $this->createSabreFreedomDraftBookingForValidationReview();

        $postResponse = $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later']);
        $postResponse->assertRedirect(route('booking.confirmation'));
        $this->assertFalse($postResponse->getSession()->has('errors'));

        $booking->refresh();
        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertNull($booking->pnr);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertTrue((bool) ($meta['defer_supplier_booking_to_manual_review'] ?? false));

        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $this->assertNotNull($attempt);
        $this->assertSame('sabre_booking_validation_failed', $attempt->error_code);

        Log::shouldHaveReceived('warning')
            ->with(
                SabreBookingValidationManualRequestPolicy::LOG_EVENT,
                Mockery::on(function (array $context) use ($booking): bool {
                    return ($context['booking_id'] ?? null) === $booking->id
                        && ($context['brand_name'] ?? '') === 'Freedom'
                        && ($context['brand_code'] ?? '') === 'FRD'
                        && ($context['public_auto_pnr_enabled'] ?? null) === false
                        && ($context['ticketing_enabled'] ?? null) === false;
                })
            );

        $confirmation = $this->get(route('booking.confirmation'));
        $confirmation->assertOk();
        $confirmation->assertSee('Freedom', false);
        $confirmation->assertSee('FRD', false);
        $confirmation->assertSee('This fare will be reviewed and confirmed by our team before ticketing.', false);
    }

    protected function configureSabreValidationFailureReviewSubmit(): void
    {
        Config::set('suppliers.sabre.branded_fares_display_enabled', true);
        Config::set('suppliers.sabre.branded_fares_selection_enabled', true);
        Config::set('suppliers.sabre.booking_enabled', true);
        Config::set('suppliers.sabre.booking_live_call_enabled', true);
        Config::set('suppliers.sabre.verified_multiseg_auto_pnr_enabled', false);
        Config::set('suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled', false);
        Config::set('suppliers.sabre.ticketing_enabled', false);
        Config::set('suppliers.sabre.revalidate_before_booking', false);
        Config::set('suppliers.sabre.allow_createbooking_without_revalidation', false);
        Config::set('suppliers.sabre.refresh_offer_before_public_pnr', false);
        Config::set('suppliers.sabre.certified_route_selector_public_checkout_enabled', false);
        Config::set('suppliers.sabre.passenger_records_allow_verified_multi_segment', false);
        Config::set('suppliers.sabre.passenger_records_fresh_shop_guard_before_live', false);
        Config::set('suppliers.sabre.createbooking_payload_style', 'trip_orders_create_booking_v1_current');
        Config::set('suppliers.sabre.booking_path', '/v1/trip/orders/createBooking');
        Config::set('suppliers.sabre.booking_schema', null);
    }

    protected function createSabreFreedomDraftBookingForValidationReview(): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-freedom-offer-422',
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T14:00:00Z',
            'total' => 165000,
            'currency' => 'PKR',
            'fare_family' => 'FREEDOM',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'departure_at' => $depart.'T08:00:00Z',
                    'arrival_at' => $depart.'T14:00:00Z',
                    'carrier' => 'EK',
                    'flight_number' => '601',
                    'booking_class' => 'Y',
                    'fare_basis_code' => 'FLWOPPK1',
                ],
            ],
            'fare_breakdown' => [
                'supplier_total' => 165000,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $sabreConn->id,
                'requires_price_change_confirmation' => false,
                'protection_mode' => 'hold_price_guaranteed',
                'fare_option_key' => 'frd-pi1',
                'selected_fare_family_option' => [
                    'name' => 'Freedom',
                    'brand_code' => 'FRD',
                    'baggage_summary' => '30kg',
                    'fare_basis_codes' => ['FLWOPPK1'],
                    'booking_classes_by_segment' => ['V'],
                    'price_display' => 'Approx. PKR 165,000',
                ],
                'flight_offer_snapshot' => $offer,
                'normalized_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create(array_merge([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ], [
            'passport_number' => 'AB9999999',
            'passport_issuing_country' => 'PK',
            'passport_expiry_date' => '2035-12-31',
            'nationality' => 'PK',
            'document_type' => 'passport',
        ]));

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'freedom.validation@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 140000,
            'taxes' => 15000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 165000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        return $booking;
    }

    protected function stubSabreValidationFailureHttp(): void
    {
        Http::fake(function (Request $request, array $options) {
            $payload = $options['laravel_data'] ?? [];
            $url = strtolower($request->url());
            $isOAuthRequest = str_contains($url, strtolower((string) config('suppliers.sabre.token_path', '/v2/auth/token')))
                || (is_array($payload) && array_key_exists('grant_type', $payload));

            if ($isOAuthRequest) {
                return Http::response(['access_token' => 'tok-test', 'expires_in' => 3600], 200);
            }

            return Http::response([
                'errors' => [
                    [
                        'title' => 'Schema validation failed',
                        'detail' => 'object instance has properties',
                        'source' => ['pointer' => '/CreatePassengerNameRecordRQ/AirPrice/0/message'],
                    ],
                ],
            ], 422);
        });
    }
}
