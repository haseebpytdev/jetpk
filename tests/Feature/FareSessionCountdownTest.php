<?php

namespace Tests\Feature;

use App\Models\Booking;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PublicBookingPassengersPayload;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class FareSessionCountdownTest extends TestCase
{
    use RefreshDatabase;

    protected function seedCheckout(string $from = 'LHE', string $to = 'DXB'): string
    {
        $depart = now()->addWeek()->format('Y-m-d');
        $this->seed(OtaFoundationSeeder::class);
        config([
            'services.turnstile.enabled' => false,
            'services.turnstile.site_key' => null,
            'services.turnstile.secret_key' => null,
        ]);
        PublicCheckoutTestDoubles::bind($this, $depart, $from, $to);

        return $depart;
    }

    public function test_checkout_blades_include_fare_session_countdown_component(): void
    {
        $files = [
            resource_path('views/frontend/booking/passenger-details.blade.php'),
            resource_path('views/frontend/booking/review.blade.php'),
            resource_path('views/mobile/bookings/passengers.blade.php'),
            resource_path('views/mobile/bookings/review.blade.php'),
        ];

        foreach ($files as $file) {
            $contents = file_get_contents($file);
            $this->assertIsString($contents);
            $this->assertStringContainsString('x-bookings.fare-session-countdown', $contents);
        }

        $component = file_get_contents(resource_path('views/components/bookings/fare-session-countdown.blade.php'));
        $this->assertIsString($component);
        $this->assertStringContainsString('data-ota-fare-session-timer', $component);
        $this->assertStringContainsString('data-ota-fare-session-display', $component);
        $this->assertStringContainsString('data-remaining-seconds', $component);
        $this->assertStringContainsString('This fare session may have expired. Please refresh or search again before payment.', $component);
        $this->assertStringContainsString('Your checkout session has expired.', $component);
        $this->assertStringContainsString('Flight fares can change quickly. Please refresh the results and choose a flight again to continue.', $component);
        $this->assertStringContainsString('Refresh flight results', $component);
        $this->assertStringContainsString('Go to Home', $component);
        $this->assertStringContainsString('data-search-url="{{ $searchRefreshUrl }}"', $component);
        $this->assertStringNotContainsString('data-search-url="{{ e($searchRefreshUrl) }}"', $component);
        $this->assertStringContainsString('data-ota-fare-session-expired-modal', $component);
        $this->assertStringContainsString('role="dialog"', $component);
        $this->assertStringContainsString('aria-modal="true"', $component);
        $this->assertStringContainsString('$initialDisplay', $component);
        $this->assertStringContainsString('data-expires-at', $component);
        $this->assertStringContainsString('sessionStorage', $component);
        $this->assertStringContainsString('Math.min(serverExpiresAt, storedExpiresAt)', $component);
        $this->assertStringNotContainsString('fetch(', $component);
        $this->assertStringNotContainsString('axios', $component);
    }

    public function test_passengers_page_renders_fare_session_timer(): void
    {
        $depart = $this->seedCheckout();
        $oid = PublicCheckoutTestDoubles::OFFER_ID;
        $url = '/booking/passengers?flight_id='.$oid.'&offer_id='.$oid.'&search_id=sid-g1c&from=LHE&to=DXB&depart='.$depart;

        $response = $this->get($url)->assertOk()
            ->assertSee('data-ota-fare-session-timer', false)
            ->assertSee('data-ota-fare-session-display', false)
            ->assertSee('data-remaining-seconds', false)
            ->assertSee('data-search-url', false)
            ->assertSee('/flights/results', false)
            ->assertSee('from=LHE', false)
            ->assertSee('to=DXB', false)
            ->assertSee('Fare held for', false)
            ->assertSee('passenger:sid-g1c:', false)
            ->assertDontSee('Residence details', false);

        $content = (string) $response->getContent();
        $this->assertMatchesRegularExpression(
            '/data-remaining-seconds="([1-9]\d*)"/',
            $content,
        );
        $this->assertMatchesRegularExpression(
            '/data-expires-at="([1-9]\d*)"/',
            $content,
        );
        preg_match('/data-remaining-seconds="(\d+)"/', $content, $matches);
        $remainingSeconds = (int) ($matches[1] ?? 0);
        $this->assertGreaterThanOrEqual(350, $remainingSeconds);
        $this->assertLessThanOrEqual(420, $remainingSeconds);

        $this->assertPassengersRefreshSearchUrl($content, $depart);
    }

    public function test_passengers_refresh_search_url_is_not_double_escaped(): void
    {
        $depart = $this->seedCheckout('LHE', 'JED');
        $oid = PublicCheckoutTestDoubles::OFFER_ID;
        $url = '/booking/passengers?flight_id='.$oid.'&offer_id='.$oid.'&search_id=sid-refresh&from=LHE&to=JED&depart='.$depart;

        $content = (string) $this->get($url)->assertOk()->getContent();

        $this->assertPassengersRefreshSearchUrl($content, $depart, 'JED');
        $this->assertStringNotContainsString('&amp;amp;', $content);
        $this->assertStringContainsString('data-ota-fare-session-refresh', $content);
        $this->assertStringContainsString('showFareSessionExpiredModal', $content);
    }

    /**
     * @param  non-empty-string  $content
     */
    private function assertPassengersRefreshSearchUrl(string $content, string $depart, string $to = 'DXB'): void
    {
        $this->assertMatchesRegularExpression(
            '/data-search-url="([^"]+)"/',
            $content,
            'Expected fare session timer to expose data-search-url.',
        );

        preg_match('/data-search-url="([^"]+)"/', $content, $matches);
        $searchUrl = html_entity_decode((string) ($matches[1] ?? ''), ENT_QUOTES);

        $this->assertStringContainsString('/flights/results', $searchUrl);
        $this->assertStringContainsString('from=LHE', $searchUrl);
        $this->assertStringContainsString('to='.$to, $searchUrl);
        $this->assertStringContainsString('depart='.$depart, $searchUrl);
        $this->assertStringContainsString('trip_type=', $searchUrl);
        $this->assertStringContainsString('cabin=', $searchUrl);
        $this->assertStringContainsString('adults=', $searchUrl);
        $this->assertStringNotContainsString('&amp;amp;', $searchUrl);
        $this->assertStringNotContainsString('&amp;', $searchUrl);
    }

    public function test_passengers_checkout_lock_persists_on_hard_refresh(): void
    {
        $depart = $this->seedCheckout();
        $oid = PublicCheckoutTestDoubles::OFFER_ID;
        $url = '/booking/passengers?flight_id='.$oid.'&offer_id='.$oid.'&search_id=sid-g1c-lock&from=LHE&to=DXB&depart='.$depart;

        $first = $this->get($url)->assertOk();
        preg_match('/data-remaining-seconds="(\d+)"/', (string) $first->getContent(), $firstMatch);
        $firstRemaining = (int) ($firstMatch[1] ?? 0);

        $this->travel(90)->seconds();

        $second = $this->get($url)->assertOk();
        preg_match('/data-remaining-seconds="(\d+)"/', (string) $second->getContent(), $secondMatch);
        $secondRemaining = (int) ($secondMatch[1] ?? 0);

        $this->assertLessThan($firstRemaining, $secondRemaining);
        $this->assertGreaterThanOrEqual(260, $secondRemaining);
        $this->assertLessThanOrEqual(330, $secondRemaining);
    }

    public function test_review_page_renders_fare_session_timer_after_checkout(): void
    {
        $depart = $this->seedCheckout();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'search_id' => 'sid-g1c-review',
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => 'g1c-timer@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertRedirect(route('booking.review'));

        $booking = Booking::query()->firstOrFail();

        $reviewResponse = $this->get(route('booking.review'))->assertOk();
        $reviewContent = (string) $reviewResponse->getContent();

        $this->assertStringContainsString('data-ota-fare-session-timer', $reviewContent);
        $this->assertStringContainsString('data-search-url', $reviewContent);
        $this->assertStringContainsString('/flights/results', $reviewContent);
        $this->assertMatchesRegularExpression('/0[6-7]:\\d{2}/', $reviewContent);
        $this->assertStringContainsString('review:'.$booking->booking_reference, $reviewContent);
        $this->assertStringContainsString('This fare session may have expired. Please refresh or search again before payment.', $reviewContent);
        $this->assertStringContainsString('Your checkout session has expired.', $reviewContent);
    }
}
