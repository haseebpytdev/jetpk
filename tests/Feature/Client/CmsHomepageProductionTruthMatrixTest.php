<?php

namespace Tests\Feature\Client;

use App\Services\Client\ClientPageAssetService;
use App\Services\Client\ClientPageContentResolver;
use App\Services\Homepage\JetpkHomepageAssetService;
use App\Services\PublicContent\HomepagePublicContentPresenter;
use App\Support\Client\ClientPageKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

/**
 * Complete CMS production-truth matrix for JetPakistan homepage controls
 * that have a current public frontend consumer via HomepagePublicContentPresenter.
 */
class CmsHomepageProductionTruthMatrixTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    /**
     * Dot paths under published homepage content that map 1:1 to public presenter output.
     *
     * @return list<array{path: string, public_path: string, qa: string}>
     */
    public static function supportedTextFields(): array
    {
        return [
            ['path' => 'hero.eyebrow', 'public_path' => 'hero.eyebrow', 'qa' => 'QA_HERO_EYEBROW_CLOSEOUT'],
            ['path' => 'hero.headline', 'public_path' => 'hero.headline', 'qa' => 'QA_HERO_HEADLINE_CLOSEOUT'],
            ['path' => 'hero.headline_highlight', 'public_path' => 'hero.headline_highlight', 'qa' => 'QA_HERO_HIGHLIGHT_CLOSEOUT'],
            ['path' => 'hero.subtitle', 'public_path' => 'hero.subtitle', 'qa' => 'QA_HERO_SUBTITLE_CLOSEOUT'],
            ['path' => 'hero.image_alt', 'public_path' => 'hero.image.alt', 'qa' => 'QA_HERO_ALT_CLOSEOUT'],
            ['path' => 'routes.eyebrow', 'public_path' => 'routes.eyebrow', 'qa' => 'QA_ROUTES_EYEBROW_CLOSEOUT'],
            ['path' => 'routes.title', 'public_path' => 'routes.title', 'qa' => 'QA_ROUTES_TITLE_CLOSEOUT'],
            ['path' => 'routes.subtitle', 'public_path' => 'routes.subtitle', 'qa' => 'QA_ROUTES_SUBTITLE_CLOSEOUT'],
            ['path' => 'routes.cta_text', 'public_path' => 'routes.cta_text', 'qa' => 'QA_ROUTES_CTA_TEXT_CLOSEOUT'],
            ['path' => 'routes.cta_url', 'public_path' => 'routes.cta_url', 'qa' => '/qa-routes-cta-closeout'],
            ['path' => 'destinations.eyebrow', 'public_path' => 'destinations.eyebrow', 'qa' => 'QA_DEST_EYEBROW_CLOSEOUT'],
            ['path' => 'destinations.title', 'public_path' => 'destinations.title', 'qa' => 'QA_DEST_TITLE_CLOSEOUT'],
            ['path' => 'destinations.subtitle', 'public_path' => 'destinations.subtitle', 'qa' => 'QA_DEST_SUBTITLE_CLOSEOUT'],
            ['path' => 'destinations.cta_text', 'public_path' => 'destinations.cta_text', 'qa' => 'QA_DEST_CTA_TEXT_CLOSEOUT'],
            ['path' => 'destinations.cta_url', 'public_path' => 'destinations.cta_url', 'qa' => '/qa-dest-cta-closeout'],
            ['path' => 'featured_deals.eyebrow', 'public_path' => 'featured_deals.eyebrow', 'qa' => 'QA_DEALS_EYEBROW_CLOSEOUT'],
            ['path' => 'featured_deals.title', 'public_path' => 'featured_deals.title', 'qa' => 'QA_DEALS_TITLE_CLOSEOUT'],
            ['path' => 'featured_deals.subtitle', 'public_path' => 'featured_deals.subtitle', 'qa' => 'QA_DEALS_SUBTITLE_CLOSEOUT'],
            ['path' => 'featured_deals.cta_text', 'public_path' => 'featured_deals.cta_text', 'qa' => 'QA_DEALS_CTA_TEXT_CLOSEOUT'],
            ['path' => 'featured_deals.cta_url', 'public_path' => 'featured_deals.cta_url', 'qa' => '/qa-deals-cta-closeout'],
            ['path' => 'why_book.eyebrow', 'public_path' => 'why_book.eyebrow', 'qa' => 'QA_WHY_EYEBROW_CLOSEOUT'],
            ['path' => 'why_book.title', 'public_path' => 'why_book.title', 'qa' => 'QA_WHY_TITLE_CLOSEOUT'],
            ['path' => 'why_book.subtitle', 'public_path' => 'why_book.subtitle', 'qa' => 'QA_WHY_SUBTITLE_CLOSEOUT'],
            ['path' => 'support_cta.eyebrow', 'public_path' => 'support_cta.eyebrow', 'qa' => 'QA_SUPPORT_EYEBROW_CLOSEOUT'],
            ['path' => 'support_cta.title', 'public_path' => 'support_cta.title', 'qa' => 'QA_SUPPORT_TITLE_CLOSEOUT'],
            ['path' => 'support_cta.subtitle', 'public_path' => 'support_cta.subtitle', 'qa' => 'QA_SUPPORT_SUBTITLE_CLOSEOUT'],
            ['path' => 'support_cta.call_label', 'public_path' => 'support_cta.call_label', 'qa' => 'QA_SUPPORT_CALL_LABEL'],
            ['path' => 'support_cta.chat_label', 'public_path' => 'support_cta.chat_label', 'qa' => 'QA_SUPPORT_CHAT_LABEL'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function supportedMediaKeys(): array
    {
        return [
            'hero_background',
            'hero_background_mobile',
            'support_cta_background',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['client_route_parity.enabled' => false]);
        Storage::fake('public');
        $this->seedJetpkAirports();
        $this->seedJetpkAgency();
        $this->seedCmsTestUsers(1, 5, 7, 42, 111, 222);
    }

    public function test_all_supported_text_fields_save_render_and_restore(): void
    {
        $fields = self::supportedTextFields();
        $this->assertGreaterThanOrEqual(28, count($fields));

        $profile = $this->makeJetpkProfile();
        $baselineContent = $this->baselineContent();
        $this->seedPublishedHome($profile, $baselineContent);

        $resolver = app(ClientPageContentResolver::class);
        $presenter = app(HomepagePublicContentPresenter::class);

        $savePass = 0;
        $renderPass = 0;
        $restorePass = 0;

        foreach ($fields as $field) {
            $baseline = (string) data_get($baselineContent, $field['path'], '');
            $qa = $field['qa'];

            $mutated = $baselineContent;
            data_set($mutated, $field['path'], $qa);
            // Keep sections enabled so public presenter returns the text.
            data_set($mutated, 'routes.enabled', '1');
            data_set($mutated, 'destinations.enabled', '1');
            data_set($mutated, 'featured_deals.enabled', '1');
            data_set($mutated, 'why_book.enabled', '1');
            data_set($mutated, 'support_cta.enabled', '1');
            data_set($mutated, 'support_cta.title', data_get($mutated, 'support_cta.title') ?: 'Support');
            data_set($mutated, 'support_cta.subtitle', data_get($mutated, 'support_cta.subtitle') ?: 'Help');

            $resolver->saveDraft($profile, ClientPageKeys::HOME, $mutated, 1);
            $resolver->publish($profile, ClientPageKeys::HOME, 1);
            $stored = $resolver->contentFor(ClientPageKeys::HOME);
            $this->assertSame($qa, (string) data_get($stored, $field['path']));
            $savePass++;

            // For hero.image.alt, public path requires an image object — attach fake when needed.
            if ($field['path'] === 'hero.image_alt') {
                $file = UploadedFile::fake()->image('qa-hero.png', 640, 360);
                app(ClientPageAssetService::class)->store($profile, ClientPageKeys::HOME, 'hero_background', $file, 1);
            }

            $public = $presenter->present();
            $rendered = data_get($public, $field['public_path']);
            if ($field['path'] === 'hero.image_alt') {
                $this->assertSame($qa, (string) data_get($public, 'hero.image.alt'));
            } else {
                $this->assertSame($qa, (string) $rendered, 'Public render mismatch for '.$field['path']);
            }
            $renderPass++;

            $resolver->saveDraft($profile, ClientPageKeys::HOME, $baselineContent, 1);
            $resolver->publish($profile, ClientPageKeys::HOME, 1);
            $restored = $resolver->contentFor(ClientPageKeys::HOME);
            $this->assertSame($baseline, (string) data_get($restored, $field['path']));
            $restorePass++;
        }

        $this->assertSame(count($fields), $savePass);
        $this->assertSame(count($fields), $renderPass);
        $this->assertSame(count($fields), $restorePass);
    }

    public function test_supported_media_keys_assign_render_and_restore(): void
    {
        $keys = self::supportedMediaKeys();
        $this->assertCount(3, $keys);

        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, $this->baselineContent());
        $assets = app(JetpkHomepageAssetService::class);
        $pageAssets = app(ClientPageAssetService::class);

        $assignPass = 0;
        $renderPass = 0;
        $restorePass = 0;

        foreach ($keys as $key) {
            $file = UploadedFile::fake()->image('qa-'.$key.'.png', 400, 240);
            if ($key === 'hero_background' || $key === 'hero_background_mobile') {
                $asset = $pageAssets->store($profile, ClientPageKeys::HOME, $key, $file, 1);
            } else {
                $asset = $assets->storeSupportCtaImage($profile, 'desktop', $file, 1, 'QA support');
            }

            $this->assertNotNull($asset);
            $assignPass++;

            $public = app(HomepagePublicContentPresenter::class)->present();
            if ($key === 'hero_background') {
                $this->assertNotEmpty(data_get($public, 'hero.image.url'));
            } elseif ($key === 'hero_background_mobile') {
                $this->assertNotEmpty(data_get($public, 'hero.image_mobile.url'));
            } else {
                $this->assertNotEmpty(data_get($public, 'support_cta.image'));
            }
            $renderPass++;

            $fresh = $asset->fresh();
            if ($fresh === null) {
                $fresh = $asset;
            }
            if ($key === 'hero_background' || $key === 'hero_background_mobile') {
                $pageAssets->destroy($fresh);
            } else {
                $assets->destroyAsset($fresh);
            }
            $restorePass++;
        }

        $this->assertSame(count($keys), $assignPass);
        $this->assertSame(count($keys), $renderPass);
        $this->assertSame(count($keys), $restorePass);
    }

    /**
     * @return array<string, mixed>
     */
    private function baselineContent(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'BASE_EYEBROW',
                'headline' => 'BASE_HEADLINE',
                'headline_highlight' => 'BASE_HIGHLIGHT',
                'subtitle' => 'BASE_SUBTITLE',
                'image_alt' => 'BASE_ALT',
                'search_visible' => '1',
                'focal_point' => 'center',
                'overlay_strength' => 'medium',
            ],
            'routes' => [
                'enabled' => '1',
                'eyebrow' => 'BASE_ROUTES_EYEBROW',
                'title' => 'BASE_ROUTES_TITLE',
                'subtitle' => 'BASE_ROUTES_SUBTITLE',
                'cta_text' => 'BASE_ROUTES_CTA',
                'cta_url' => '/base-routes',
                'items' => [],
            ],
            'destinations' => [
                'enabled' => '1',
                'eyebrow' => 'BASE_DEST_EYEBROW',
                'title' => 'BASE_DEST_TITLE',
                'subtitle' => 'BASE_DEST_SUBTITLE',
                'cta_text' => 'BASE_DEST_CTA',
                'cta_url' => '/base-dest',
                'items' => [],
            ],
            'featured_deals' => [
                'enabled' => '1',
                'eyebrow' => 'BASE_DEALS_EYEBROW',
                'title' => 'BASE_DEALS_TITLE',
                'subtitle' => 'BASE_DEALS_SUBTITLE',
                'cta_text' => 'BASE_DEALS_CTA',
                'cta_url' => '/base-deals',
                'items' => [],
            ],
            'why_book' => [
                'enabled' => '1',
                'eyebrow' => 'BASE_WHY_EYEBROW',
                'title' => 'BASE_WHY_TITLE',
                'subtitle' => 'BASE_WHY_SUBTITLE',
                'cards' => [
                    ['id' => 'why-1', 'enabled' => '1', 'title' => 'Card', 'text' => 'Text', 'num' => '01'],
                ],
            ],
            'support_cta' => [
                'enabled' => '1',
                'eyebrow' => 'BASE_SUPPORT_EYEBROW',
                'title' => 'BASE_SUPPORT_TITLE',
                'subtitle' => 'BASE_SUPPORT_SUBTITLE',
                'call_label' => 'BASE_CALL',
                'chat_label' => 'BASE_CHAT',
                'call_enabled' => '1',
                'chat_enabled' => '1',
                'phone_value' => '+920000000000',
                'call_url' => 'tel:+920000000000',
                'chat_url' => '/support',
            ],
            'feature_board' => [
                'enabled' => '1',
                'items' => [
                    ['value' => '1', 'label' => 'BASE_STAT'],
                ],
            ],
        ];
    }
}
