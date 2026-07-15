<?php

namespace Tests\Feature;

use App\Support\Audits\JetpkHomepageContentAuditService;
use App\Support\Client\JetpkHomepageSectionData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class JetpkHomepageAuditFixtureTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedJetpkAirports();
    }

    public function test_content_audit_passes_for_representative_valid_homepage(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, $this->representativeValidFourCardHomeContent());

        $result = app(JetpkHomepageContentAuditService::class)->auditProfile($profile);
        $this->assertSame(0, $result['fail_count'], json_encode($result['checks'], JSON_PRETTY_PRINT));

        Artisan::call('jetpk:homepage-content-audit', ['--profile' => 'jetpk']);
        $this->assertStringContainsString('fail_count=0', Artisan::output());
    }

    public function test_media_audit_passes_with_no_uploaded_assets(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, $this->representativeValidFourCardHomeContent());

        $result = app(JetpkHomepageContentAuditService::class)->auditMedia($profile);
        $this->assertSame(0, $result['fail_count']);

        Artisan::call('jetpk:homepage-media-audit', ['--profile' => 'jetpk']);
        $this->assertStringContainsString('fail_count=0', Artisan::output());
    }

    public function test_public_homepage_renders_four_cards_without_pkr_zero_or_blank_images(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, $this->representativeValidFourCardHomeContent());

        $response = $this->get('/');
        $response->assertOk();
        $html = $response->getContent();
        $this->assertNotFalse($html);
        $this->assertStringNotContainsString('PKR 0', $html);
        $this->assertStringContainsString('Custom Dubai', $html);
        $this->assertStringContainsString('homepage-destination-fallback.svg', $html);

        $routes = app(JetpkHomepageSectionData::class)->routesForDisplay();
        $destinations = app(JetpkHomepageSectionData::class)->destinationsForDisplay();
        $this->assertGreaterThanOrEqual(4, count($routes));
        $this->assertGreaterThanOrEqual(4, count($destinations));

        foreach ($routes as $route) {
            $this->assertStringNotContainsString('PKR 0', (string) ($route['price_label'] ?? ''));
        }
        foreach ($destinations as $dest) {
            $this->assertNotNull($dest['image']);
            $this->assertNotSame('', (string) $dest['image']);
            if ($dest['price'] !== null) {
                $this->assertGreaterThan(0, (int) $dest['price']);
            }
        }
    }

    public function test_support_cta_hides_buttons_when_actions_unconfigured(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, $this->representativeThreeCardHomeContent());

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertNotFalse($html);
        $this->assertStringContainsString('data-jp-support-cta', $html);
        $this->assertStringNotContainsString('data-jp-support-call', $html);
        $this->assertStringNotContainsString('data-jp-support-chat', $html);
        $this->assertStringNotContainsString('href="#"', $html);
        $this->assertStringNotContainsString('javascript:void(0)', $html);
        $this->assertStringNotContainsString('tel:', $html);
    }

    public function test_support_cta_renders_configured_actions(): void
    {
        $profile = $this->makeJetpkProfile();
        $content = $this->representativeValidFourCardHomeContent();
        $this->seedPublishedHome($profile, $content);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertNotFalse($html);
        $this->assertStringContainsString('data-jp-support-cta', $html);
        $this->assertStringContainsString('data-jp-support-call', $html);
        $this->assertStringContainsString('data-jp-support-chat', $html);
        $this->assertStringContainsString('tel:+92', $html);
        $this->assertStringContainsString('Call support', $html);
        $this->assertStringContainsString('Live chat', $html);
    }

    public function test_fallback_image_resolves_from_local_public_path(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, $this->representativeValidFourCardHomeContent());

        $destinations = app(JetpkHomepageSectionData::class)->destinationsForDisplay();
        $this->assertNotEmpty($destinations);
        $firstImage = $destinations[0]['image'] ?? '';
        $this->assertStringContainsString('homepage-destination-fallback.svg', $firstImage);
        $this->assertFileExists(public_path('themes/frontend/jetpakistan/images/homepage-destination-fallback.svg'));
    }
}
