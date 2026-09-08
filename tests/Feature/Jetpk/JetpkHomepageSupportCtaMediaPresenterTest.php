<?php

namespace Tests\Feature\Jetpk;

use App\Models\ClientPageAsset;
use App\Services\PublicContent\HomepagePublicContentPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class JetpkHomepageSupportCtaMediaPresenterTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedJetpkAirports();
        $this->seedJetpkAgency();
    }

    public function test_support_cta_api_includes_background_image_url(): void
    {
        $profile = $this->makeJetpkProfile();

        ClientPageAsset::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => 'home',
            'asset_key' => 'support_cta_background',
            'disk' => 'public',
            'path' => 'jetpk/homepage/support-cta/support.jpg',
            'public_url' => '/storage/jetpk/homepage/support-cta/support.jpg',
            'alt_text' => 'Support team',
        ]);

        $this->seedPublishedHome($profile, [
            'support_cta' => [
                'enabled' => '1',
                'title' => 'Need help?',
                'subtitle' => 'We are here',
                'call_enabled' => '1',
                'chat_enabled' => '1',
                'chat_url' => '/support',
            ],
        ]);

        $payload = app(HomepagePublicContentPresenter::class)->present();

        $this->assertTrue($payload['support_cta']['enabled']);
        $this->assertStringStartsWith('/storage/jetpk/homepage/support-cta/support.jpg', (string) $payload['support_cta']['image']);
    }
}
