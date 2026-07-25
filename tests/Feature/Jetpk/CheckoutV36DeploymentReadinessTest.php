<?php

namespace Tests\Feature\Jetpk;

use App\Support\Audits\JetpkCheckoutShellAudit;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use Tests\Support\JetpkHomepageFixture;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class CheckoutV36DeploymentReadinessTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    /** @var list<string> */
    private const APPROVED_RUNTIME_FILES = [
        'resources/views/themes/frontend/jetpakistan/frontend/booking/passenger-details.blade.php',
        'resources/views/themes/frontend/jetpakistan/frontend/booking/review.blade.php',
        'resources/views/themes/frontend/jetpakistan/frontend/booking/card-payment.blade.php',
        'resources/views/themes/frontend/jetpakistan/frontend/booking/confirmation.blade.php',
        'resources/views/themes/frontend/jetpakistan/frontend/booking/partials/passenger-details-body.blade.php',
        'resources/views/themes/frontend/jetpakistan/components/checkout/progress-bar.blade.php',
        'public/themes/frontend/jetpakistan/css/booking.css',
        'public/themes/frontend/jetpakistan/js/booking.js',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    private function bootJetpkClient(): void
    {
        $this->makeJetpkProfile();
    }

    public function test_approved_checkout_v36_runtime_files_exist(): void
    {
        $this->bootJetpkClient();
        foreach (self::APPROVED_RUNTIME_FILES as $path) {
            $this->assertFileExists(base_path($path), "Missing runtime file: {$path}");
        }

        $this->assertTrue(View::exists('themes.frontend.jetpakistan.components.checkout.progress-bar'));
    }

    public function test_checkout_shells_reference_booking_assets_version_36(): void
    {
        $this->bootJetpkClient();
        foreach ([
            'resources/views/themes/frontend/jetpakistan/frontend/booking/passenger-details.blade.php',
            'resources/views/themes/frontend/jetpakistan/frontend/booking/review.blade.php',
            'resources/views/themes/frontend/jetpakistan/frontend/booking/card-payment.blade.php',
            'resources/views/themes/frontend/jetpakistan/frontend/booking/confirmation.blade.php',
        ] as $path) {
            $source = (string) file_get_contents(base_path($path));
            $this->assertStringContainsString('$jpCheckoutAssetVersion = 36', $source, $path);
            $this->assertStringContainsString('/css/booking.css?v={{ $jpCheckoutAssetVersion }}', $source, $path);
        }

        $partial = (string) file_get_contents(base_path('resources/views/themes/frontend/jetpakistan/frontend/booking/partials/passenger-details-body.blade.php'));
        $this->assertStringContainsString('$jpCheckoutAssetVersion ?? 36', $partial);
        $this->assertStringNotContainsString('$jpCheckoutAssetVersion ?? 35', $partial);
    }

    public function test_checkout_shell_audit_passes(): void
    {
        $this->bootJetpkClient();
        $audit = app(JetpkCheckoutShellAudit::class)->run();
        $this->assertSame(0, $audit['fail_count'], implode('; ', $audit['issues']));
        $this->assertTrue($audit['progress_partial_exists']);
        $this->assertTrue($audit['progress_renders']);
    }

    public function test_checkout_blades_have_no_cross_client_branding_literals(): void
    {
        $this->bootJetpkClient();
        foreach (self::APPROVED_RUNTIME_FILES as $path) {
            if (! str_ends_with($path, '.blade.php')) {
                continue;
            }
            $source = (string) file_get_contents(base_path($path));
            foreach (['Parwaaz', 'haseeb-master', 'ota.haseebasif.com', 'YoursDomain'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $source, "{$path} contains {$forbidden}");
            }
        }
    }

    public function test_passenger_page_renders_checkout_assets_with_valid_fixture(): void
    {
        $this->bootJetpkClient();
        $this->seed(OtaFoundationSeeder::class);
        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');

        $html = $this->get('/booking/passengers?flight_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&offer_id='.PublicCheckoutTestDoubles::OFFER_ID
            .'&search_id=test-search-store'
            .'&from=LHE&to=DXB&depart='.$depart.'&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0')
            ->assertOk()
            ->getContent();

        $this->assertIsString($html);
        $this->assertStringContainsString('jp-checkout-progress', $html);
        if (str_contains($html, 'themes/frontend/jetpakistan')) {
            $this->assertStringContainsString('booking.css?v=36', $html);
        }
        $this->assertStringNotContainsString('Parwaaz', $html);
        Http::assertNothingSent();
    }

    public function test_progress_bar_partial_renders_checkout_stages(): void
    {
        $this->bootJetpkClient();
        $html = view('themes.frontend.jetpakistan.components.checkout.progress-bar', ['activeStep' => 2])->render();
        $this->assertStringContainsString('Passenger details', $html);
        $this->assertStringContainsString('Review &amp; payment', $html);
        $this->assertStringContainsString('Confirmation', $html);
    }
}
