<?php

namespace Tests\Feature;

use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PublicBookingPassengersPayload;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class PublicBookingLayoutSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_pages_render_single_main_nav_and_main_landmark(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');
        $passengersUrl = '/booking/passengers?flight_id='.PublicCheckoutTestDoubles::OFFER_ID.'&from=LHE&to=DXB&depart='.$depart;

        $html = $this->get($passengersUrl)->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'class="ota-main-nav'), 'Expected exactly one primary nav on passenger checkout');
        $this->assertSame(1, substr_count($html, 'class="ota-site-header'), 'Expected fixed site header wrapper once');
        $this->assertSame(1, substr_count($html, 'id="ota-main"'), 'Expected single main content landmark');

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => 'test@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ));

        $reviewHtml = $this->get(route('booking.review'))->assertOk()->assertSee('Review your booking', false)->getContent();
        $this->assertSame(1, substr_count($reviewHtml, 'class="ota-main-nav'));
        $this->assertSame(1, substr_count($reviewHtml, 'class="ota-site-header'));

        $confirmHtml = $this->get(route('booking.confirmation'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($confirmHtml, 'class="ota-main-nav'));
        $this->assertSame(1, substr_count($confirmHtml, 'class="ota-site-header'));
    }

    public function test_home_and_results_have_single_site_header(): void
    {
        $depart = now()->addWeek()->format('Y-m-d');
        foreach ([
            $this->get('/'),
            $this->get('/flights/results?from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0'),
        ] as $response) {
            $response->assertOk();
            $h = $response->getContent();
            $this->assertSame(1, substr_count($h, 'class="ota-site-header'));
            $this->assertSame(1, substr_count($h, 'class="ota-main-nav'));
        }
    }
}
