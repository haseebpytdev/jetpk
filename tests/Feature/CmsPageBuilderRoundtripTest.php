<?php

namespace Tests\Feature;

use App\Models\CmsPage;
use App\Services\Cms\CmsPageContentSanitizer;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CmsPageBuilderRoundtripTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_builder_blocks_survive_save_reload_preview_and_public_render(): void
    {
        $admin = $this->platformAdmin();
        $html = $this->builderFixture();

        $this->actingAs($admin)
            ->post(route('admin.cms-pages.store'), [
                'title' => 'QA CMS Builder Roundtrip',
                'slug' => 'qa-cms-builder-roundtrip',
                'robots' => CmsPage::ROBOTS_INDEX,
                'status' => CmsPage::STATUS_DRAFT,
                'content' => $html,
            ])
            ->assertRedirect();

        $page = CmsPage::query()->where('slug', 'qa-cms-builder-roundtrip')->firstOrFail();
        $stored = (string) $page->content;

        foreach ([
            'heading', 'paragraph', 'image', 'image_text', 'cta', 'features', 'stats',
            'offers', 'faq', 'gallery', 'video', 'testimonials', 'steps', 'callout',
        ] as $block) {
            $this->assertStringContainsString('data-jp-block="'.$block.'"', $stored, $block.' must survive storage');
        }

        $this->assertStringContainsString('<img', $stored);
        $this->assertStringContainsString('src="/storage/cms-qa/hero.jpg"', $stored);
        $this->assertStringContainsString('alt="Hero aircraft"', $stored);
        $this->assertStringContainsString('Runway caption', $stored);
        $this->assertStringContainsString('alt="Gallery one"', $stored);
        $this->assertStringContainsString('alt="Gallery two"', $stored);
        $this->assertTrue(
            strpos($stored, 'alt="Gallery one"') < strpos($stored, 'alt="Gallery two"'),
            'gallery order must survive'
        );
        $this->assertTrue(
            strpos($stored, 'Benefit one') < strpos($stored, 'Benefit two'),
            'features order must survive'
        );
        $this->assertTrue(
            strpos($stored, 'Question one') < strpos($stored, 'Question two'),
            'faq order must survive'
        );
        $this->assertStringContainsString('data-jp-hidden="true"', $stored);
        $this->assertStringNotContainsString('<script', strtolower($stored));
        $this->assertStringNotContainsString('javascript:', strtolower($stored));
        $this->assertStringNotContainsString('onclick=', strtolower($stored));

        $this->actingAs($admin)
            ->post(route('admin.cms-pages.update', $page), [
                '_method' => 'PATCH',
                'title' => $page->title,
                'slug' => $page->slug,
                'robots' => CmsPage::ROBOTS_INDEX,
                'status' => CmsPage::STATUS_DRAFT,
                'content' => $stored,
            ])
            ->assertRedirect();

        $reloaded = (string) $page->fresh()->content;
        $this->assertStringContainsString('data-jp-block="gallery"', $reloaded);
        $this->assertStringContainsString('src="/storage/cms-qa/hero.jpg"', $reloaded);

        $this->actingAs($admin)
            ->postJson(route('admin.cms-pages.preview-draft', $page), [
                'title' => 'Unsaved preview title',
                'content' => $reloaded,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        foreach (['desktop', 'tablet', 'mobile'] as $viewport) {
            $preview = $this->actingAs($admin)
                ->get(route('admin.cms-pages.preview', [
                    'cmsPage' => $page,
                    'draft' => 1,
                    'viewport' => $viewport,
                    'theme' => $viewport === 'mobile' ? 'night' : 'day',
                ]));
            $preview->assertOk();
            $preview->assertSee('data-jp-block="heading"', false);
            $preview->assertSee('data-jp-block="image"', false);
            $preview->assertSee('src="/storage/cms-qa/hero.jpg"', false);
            $preview->assertSee('Unsaved preview title', false);
            $preview->assertDontSee('SECRET_HIDDEN_BLOCK', false);
        }

        $this->assertSame($reloaded, (string) $page->fresh()->content);
        $this->get(route('pages.show', $page->slug))->assertNotFound();

        $page->update([
            'status' => CmsPage::STATUS_ACTIVE,
            'published_at' => now(),
        ]);

        $public = $this->get(route('pages.show', $page->slug));
        $public->assertOk();
        $public->assertSee('data-jp-block="heading"', false);
        $public->assertSee('Visible heading', false);
        $public->assertSee('src="/storage/cms-qa/hero.jpg"', false);
        $public->assertDontSee('SECRET_HIDDEN_BLOCK', false);
        $public->assertDontSee('data-jp-hidden="true"', false);
    }

    public function test_sanitizer_rejects_scripts_and_keeps_builder_vocabulary(): void
    {
        $sanitizer = app(CmsPageContentSanitizer::class);
        $out = $sanitizer->sanitizeForStorage(
            '<section data-jp-block="heading" onclick="alert(1)"><h2>Hi</h2><script>alert(1)</script><a href="javascript:alert(1)">x</a></section>'
        );

        $this->assertStringContainsString('data-jp-block="heading"', $out);
        $this->assertStringNotContainsString('script', strtolower($out));
        $this->assertStringNotContainsString('onclick', strtolower($out));
        $this->assertStringNotContainsString('javascript:', strtolower($out));
    }

    private function builderFixture(): string
    {
        return <<<'HTML'
<section data-jp-block="heading"><h2>Visible heading</h2></section>
<section data-jp-block="paragraph"><p>Body copy.</p></section>
<section data-jp-block="image"><figure><img src="/storage/cms-qa/hero.jpg" alt="Hero aircraft" /><figcaption>Runway caption</figcaption></figure></section>
<section data-jp-block="image_text" data-layout="left"><figure><img src="/storage/cms-qa/hero.jpg" alt="Side image" /></figure><div><h3>Title</h3><p>Supporting copy.</p></div></section>
<section data-jp-block="cta" data-variant="primary"><p><a href="/flights">Book now</a></p></section>
<section data-jp-block="features"><ul><li><strong>Benefit one</strong><p>One</p></li><li><strong>Benefit two</strong><p>Two</p></li></ul></section>
<section data-jp-block="stats"><ul><li><strong>10k+</strong><span>travelers</span></li></ul></section>
<section data-jp-block="offers"><article><img src="/storage/cms-qa/hero.jpg" alt="Offer" /><h3>Lahore → Jeddah</h3><a href="/">Open</a></article></section>
<section data-jp-block="faq"><article><h3>Question one</h3><p>Answer one</p></article><article><h3>Question two</h3><p>Answer two</p></article></section>
<section data-jp-block="gallery"><figure><img src="/storage/cms-qa/g1.jpg" alt="Gallery one" /><figcaption>One</figcaption></figure><figure><img src="/storage/cms-qa/g2.jpg" alt="Gallery two" /><figcaption>Two</figcaption></figure></section>
<section data-jp-block="video"><a href="https://www.youtube.com/watch?v=dQw4w9wg" data-jp-video>Watch video</a></section>
<section data-jp-block="testimonials"><blockquote><p>Traveler quote</p><cite>Customer name</cite></blockquote></section>
<section data-jp-block="steps"><ol><li><strong>Search</strong><p>Find flights</p></li><li><strong>Hold</strong><p>Reserve</p></li></ol></section>
<section data-jp-block="callout"><h3>Need help?</h3><p>Contact JetPakistan support.</p></section>
<section data-jp-block="paragraph" data-jp-hidden="true"><p>SECRET_HIDDEN_BLOCK</p></section>
<script>alert('xss')</script>
HTML;
    }
}
