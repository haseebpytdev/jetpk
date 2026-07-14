<?php

namespace Tests\Unit\Support\Homepage;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageAsset;
use App\Models\ClientPageSetting;
use App\Services\Homepage\JetpkHeroImageOptimizer;
use App\Support\Client\ClientPageKeys;
use App\Support\Homepage\JetpkHeroLcpPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class JetpkHeroLcpPresenterTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['client_route_parity.enabled' => false]);
        Storage::fake('public');
        $this->seedJetpkAirports();
        $this->seedJetpkAgency();
    }

    public function test_presenter_uses_current_asset_manifest_only(): void
    {
        $profile = $this->makeJetpkProfile();
        $source = $this->photographicSource();
        $this->assertFileExists($source);
        $relative = 'client-assets/jetpk-assets/pages/home/hero_background-test.jpg';
        Storage::disk('public')->put($relative, (string) file_get_contents($source));
        $this->assertTrue(Storage::disk('public')->exists($relative));
        $heroUrl = '/storage/'.$relative;

        $optimizer = app(JetpkHeroImageOptimizer::class);
        $result = $optimizer->optimize(Storage::disk('public')->path($relative), 'jetpk-assets', 'home', $relative);
        if (! $result['activated']) {
            $this->fail((string) ($result['warning'] ?? 'optimizer did not activate'));
        }

        ClientPageAsset::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'asset_key' => 'hero_background',
            'disk' => 'public',
            'path' => $relative,
            'public_url' => $heroUrl,
            'meta_json' => ['hero_lcp' => $result['manifest']],
        ]);

        $presented = app(JetpkHeroLcpPresenter::class)->present($heroUrl, $result['manifest']);

        $this->assertNotNull($presented);
        $this->assertTrue($presented['has_responsive_variants']);
        $this->assertStringContainsString('/lcp/'.$result['fingerprint'].'/', $presented['sources'][0]['srcset']);
    }

    public function test_presenter_ignores_stale_manifest_for_different_source(): void
    {
        $profile = $this->makeJetpkProfile();
        $relative = 'client-assets/jetpk-assets/pages/home/hero_background-current.jpg';
        Storage::disk('public')->put($relative, file_get_contents($this->photographicSource()));
        $heroUrl = Storage::disk('public')->url($relative);

        $staleManifest = [
            'fingerprint' => 'deadbeefdeadbeef',
            'source_path' => 'client-assets/jetpk-assets/pages/home/old-hero.jpg',
            'variants' => ['desktop' => ['webp' => ['url' => 'http://example.test/old.webp', 'path' => 'missing']]],
        ];

        $presented = app(JetpkHeroLcpPresenter::class)->present($heroUrl, $staleManifest);

        $this->assertNotNull($presented);
        $this->assertFalse($presented['has_responsive_variants']);
        $this->assertSame($heroUrl, $presented['fallback_url']);
    }

    public function test_jetpk_homepage_renders_semantic_hero_picture_with_priority_hints(): void
    {
        $profile = $this->makeJetpkProfile();
        $relative = 'client-assets/jetpk-assets/pages/home/hero_background-page.jpg';
        Storage::disk('public')->put($relative, file_get_contents($this->photographicSource()));

        $result = app(JetpkHeroImageOptimizer::class)->optimize(
            Storage::disk('public')->path($relative),
            'jetpk-assets',
            'home',
        );
        $this->assertTrue($result['activated']);

        ClientPageAsset::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'asset_key' => 'hero_background',
            'disk' => 'public',
            'path' => $relative,
            'public_url' => Storage::disk('public')->url($relative),
            'meta_json' => ['hero_lcp' => $result['manifest']],
        ]);

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => app(\App\Services\Client\ClientPageContentResolver::class)->defaultHomeContent(),
            'published_at' => now(),
        ]);

        $homeRoute = \Illuminate\Support\Facades\Route::has('client.preview.home')
            ? 'client.preview.home'
            : 'client.parity.home.alias';

        $response = $this->get(route($homeRoute, ['clientSlug' => 'jetpk']));
        if ($response->isRedirect()) {
            $response = $this->followRedirects($response);
        }
        $response->assertOk();
        $html = $response->getContent();
        $this->assertIsString($html);

        $this->assertStringContainsString('<picture>', $html);
        $this->assertStringContainsString('class="hero-img"', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
        $this->assertStringContainsString('loading="eager"', $html);
        $this->assertStringContainsString('rel="preload"', $html);
        $this->assertStringContainsString('jp-loader done', $html);
        $this->assertStringContainsString('name="trip_type"', $html);
    }

    private function photographicSource(): string
    {
        $reference = 'c:/Users/khadi/Downloads/jetpakistan.png';
        if (is_file($reference)) {
            $dir = storage_path('app/testing/hero-fixtures');
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $target = $dir.'/presenter-source.jpg';
            $png = imagecreatefrompng($reference);
            imagejpeg($png, $target, 90);
            imagedestroy($png);

            return $target;
        }

        $dir = storage_path('app/testing/hero-fixtures');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $path = $dir.'/presenter-gradient.jpg';
        $img = imagecreatetruecolor(900, 500);
        for ($x = 0; $x < 900; $x++) {
            for ($y = 0; $y < 500; $y++) {
                $color = imagecolorallocate($img, ($x + 15) % 255, ($y * 2) % 255, 110);
                imagesetpixel($img, $x, $y, $color);
            }
        }
        imagejpeg($img, $path, 90);
        imagedestroy($img);

        return $path;
    }
}
