<?php

namespace Tests\Feature;

use App\Data\OfferValidationResultData;
use App\Http\Controllers\Frontend\BookingController;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Suppliers\OfferValidationService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Mockery;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class BookingPassengersRedirectLoopTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_now_with_valid_offer_renders_passenger_page_without_redirect_loop(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');

        $response = $this->get('/booking/passengers?flight_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&offer_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&search_id=test-search-store'
            .'&from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0');

        $response->assertOk();
        $response->assertSee('Checkout', false);
    }

    public function test_second_request_after_stale_recovery_flag_skips_repeat_recovery(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');

        $unavailable = new OfferValidationResultData(
            is_valid: false,
            status: 'unavailable',
            original_offer_id: PublicCheckoutTestDoubles::OFFER_ID,
            warnings: ['Stale'],
        );

        $ovs = Mockery::mock(OfferValidationService::class);
        $ovs->shouldReceive('validateSelectedOffer')->once()->andReturn($unavailable);
        $ovs->shouldReceive('pricingSnapshotForCachedOffer')->andReturn(PublicCheckoutTestDoubles::pricingSnapshot());
        App::instance(OfferValidationService::class, $ovs);

        $flightSearch = Mockery::mock(FlightSearchService::class);
        $flightSearch->shouldReceive('search')->andReturn([PublicCheckoutTestDoubles::searchOfferPayload($depart)]);
        $flightSearch->shouldReceive('searchWithMeta')->never();
        App::instance(FlightSearchService::class, $flightSearch);

        $response = $this->withSession([
            BookingController::SESSION_BOOKING_AFTER_STALE_RECOVERY => true,
        ])->get('/booking/passengers?flight_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&offer_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&search_id=test-search-store'
            .'&from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0');

        $response->assertRedirect(route('flights.results', $this->resultsQuery($depart)));
        $response->assertSessionHasErrors('flight_id');
    }

    public function test_guest_book_now_does_not_redirect_to_login(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');

        $response = $this->get('/booking/passengers?flight_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&from=LHE&to=DXB&depart='.$depart);

        $this->assertFalse($response->isRedirect(route('login')));
        $response->assertOk();
    }

    public function test_provider_error_redirects_once_to_search_with_validation_message(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $depart = now()->addWeek()->format('Y-m-d');

        $offer = PublicCheckoutTestDoubles::searchOfferPayload($depart);

        $ovs = Mockery::mock(OfferValidationService::class);
        $ovs->shouldReceive('validateSelectedOffer')->once()->andReturn(new OfferValidationResultData(
            is_valid: false,
            status: 'provider_error',
            original_offer_id: PublicCheckoutTestDoubles::OFFER_ID,
            warnings: ['Fare validation is temporarily unavailable. Please try again.'],
        ));
        $ovs->shouldReceive('pricingSnapshotForCachedOffer')->andReturn(PublicCheckoutTestDoubles::pricingSnapshot());
        App::instance(OfferValidationService::class, $ovs);

        $flightSearch = Mockery::mock(FlightSearchService::class);
        $flightSearch->shouldReceive('search')->andReturn([$offer]);
        App::instance(FlightSearchService::class, $flightSearch);

        $response = $this->get('/booking/passengers?flight_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&offer_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0');

        $response->assertRedirect(route('flights.results', $this->resultsQuery($depart)));
        $response->assertSessionHasErrors('flight_id');
    }

    public function test_missing_book_now_offer_redirects_to_results_with_visible_warning(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $depart = now()->addWeek()->format('Y-m-d');
        $warning = 'This fare is no longer available. Please refresh results and select again.';

        $flightSearch = Mockery::mock(FlightSearchService::class);
        $flightSearch->shouldReceive('search')->once()->andReturn([]);
        $flightSearch->shouldReceive('searchWithMeta')->once()->andReturn([
            'offers' => [PublicCheckoutTestDoubles::searchOfferPayload($depart)],
            'warnings' => [],
        ]);
        App::instance(FlightSearchService::class, $flightSearch);

        $html = $this->followingRedirects()
            ->get('/booking/passengers?flight_id=missing-offer&offer_id=missing-offer'
                .'&from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($warning, $html);
        $this->assertStringContainsString('data-results-root', $html);
    }

    public function test_missing_book_now_without_search_context_redirects_home_with_warning(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $response = $this->get('/booking/passengers?flight_id=missing-offer&offer_id=missing-offer');

        $this->assertStringContainsString('#jp-flight-search', (string) $response->headers->get('Location'));
        $response->assertSessionHas('offer_warning');
    }

    /**
     * @return array<string, mixed>
     */
    private function resultsQuery(string $depart): array
    {
        return [
            'from' => 'LHE',
            'to' => 'DXB',
            'depart' => $depart,
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ];
    }
}
