<?php

namespace Tests\Feature\Jetpk;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageAsset;
use App\Models\ClientPageSetting;
use App\Models\User;
use App\Services\Client\ClientPageContentResolver;
use App\Services\Homepage\JetpkHomepageContentValidator;
use App\Support\Client\ClientPageKeys;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class JetpkSupportCtaUrlValidationTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['client_route_parity.enabled' => false]);
        $this->seedJetpkAirports();
        $this->seedJetpkAgency();
    }

    /**
     * @return array<string, mixed>
     */
    private function baseSupportPayload(string $callUrl = ''): array
    {
        return [
            'destinations' => ['enabled' => '1', 'items' => []],
            'routes' => ['enabled' => '1', 'items' => []],
            'support_cta' => [
                'enabled' => '1',
                'title' => 'Need help?',
                'call_enabled' => '1',
                'call_url' => $callUrl,
                'chat_enabled' => '1',
                'chat_url' => '/support',
                'background_mode' => 'uploaded_overlay',
            ],
        ];
    }

    public function test_valid_call_support_urls_pass_validation(): void
    {
        $validator = app(JetpkHomepageContentValidator::class);

        foreach (['/support', 'https://jetpakistan.pk/support', 'tel:+923111222427', 'tel:03111222427'] as $url) {
            $result = $validator->validateAndNormalize(ClientPageKeys::HOME, $this->baseSupportPayload($url));
            $this->assertSame($url, data_get($result, 'support_cta.call_url'), "Expected {$url} to pass");
        }
    }

    public function test_invalid_call_support_urls_are_rejected(): void
    {
        $validator = app(JetpkHomepageContentValidator::class);

        foreach (['javascript:alert(1)', 'data:text/html,abc', 'tel:', 'tel:abc123', '//evil.example', 'http://insecure.example'] as $url) {
            try {
                $validator->validateAndNormalize(ClientPageKeys::HOME, $this->baseSupportPayload($url));
                $this->fail("Expected {$url} to be rejected");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('content.support_cta.call_url', $e->errors());
            }
        }
    }

    public function test_chat_url_rejects_tel_and_protocol_relative_urls(): void
    {
        $validator = app(JetpkHomepageContentValidator::class);

        foreach (['tel:+923111222427', '//evil.example'] as $chatUrl) {
            $payload = $this->baseSupportPayload();
            $payload['support_cta']['chat_url'] = $chatUrl;

            try {
                $validator->validateAndNormalize(ClientPageKeys::HOME, $payload);
                $this->fail("Expected chat URL {$chatUrl} to be rejected");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('content.support_cta.chat_url', $e->errors());
            }
        }
    }

    public function test_save_draft_persists_support_cta_with_tel_url(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $profile = $this->makeJetpkProfile();
        $admin = User::factory()->create([
            'account_type' => \App\Enums\AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $before = now()->subSecond();
        $this->actingAs($admin)->patch('/admin/page-settings/home', [
            'content' => $this->baseSupportPayload('tel:+923111222427'),
        ])->assertRedirect()->assertSessionHas('status', 'Draft saved.');

        $draft = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('status', ClientPageSettingStatus::Draft)
            ->first();

        $this->assertNotNull($draft);
        $this->assertSame('tel:+923111222427', data_get($draft->content_json, 'support_cta.call_url'));
        $this->assertSame('Need help?', data_get($draft->content_json, 'support_cta.title'));
        $this->assertSame('uploaded_overlay', data_get($draft->content_json, 'support_cta.background_mode'));
        $this->assertNotNull($draft->updated_at);
        $this->assertTrue($draft->updated_at->greaterThanOrEqualTo($before));
    }

    public function test_save_draft_retains_support_cta_media_and_tel_url(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $profile = $this->makeJetpkProfile();
        $admin = User::factory()->create([
            'account_type' => \App\Enums\AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $this->actingAs($admin)->patch('/admin/page-settings/home', [
            'content' => $this->baseSupportPayload('tel:+923111222427'),
            'support_cta_background_file' => UploadedFile::fake()->image('banner.jpg'),
        ])->assertRedirect();

        $asset = ClientPageAsset::query()->where('asset_key', 'support_cta_background')->first();
        $this->assertNotNull($asset);
        $assetId = $asset->id;

        $this->actingAs($admin)->patch('/admin/page-settings/home', [
            'content' => array_merge($this->baseSupportPayload('tel:+923111222427'), [
                'support_cta' => array_merge($this->baseSupportPayload()['support_cta'], [
                    'title' => 'Updated support title',
                ]),
            ]),
        ])->assertRedirect()->assertSessionHas('status', 'Draft saved.');

        $this->assertSame($assetId, ClientPageAsset::query()->where('asset_key', 'support_cta_background')->value('id'));
    }

    public function test_failed_validation_does_not_save_invalid_call_url(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $profile = $this->makeJetpkProfile();
        $admin = User::factory()->create([
            'account_type' => \App\Enums\AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $resolver = app(ClientPageContentResolver::class);
        $resolver->saveDraft($profile, ClientPageKeys::HOME, $this->baseSupportPayload('tel:+923111222427'), $admin->id);

        $this->actingAs($admin)->from('/admin/page-settings/home')
            ->patch('/admin/page-settings/home', [
                'content' => $this->baseSupportPayload('javascript:alert(1)'),
            ])
            ->assertRedirect('/admin/page-settings/home')
            ->assertSessionHasErrors('content.support_cta.call_url');

        $draft = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('status', ClientPageSettingStatus::Draft)
            ->first();

        $this->assertSame('tel:+923111222427', data_get($draft->content_json, 'support_cta.call_url'));
    }

    public function test_publish_support_cta_with_tel_url_renders_on_homepage(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $profile = $this->makeJetpkProfile();
        $admin = User::factory()->create([
            'account_type' => \App\Enums\AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);
        $resolver = app(ClientPageContentResolver::class);

        $this->actingAs($admin)->patch('/admin/page-settings/home', [
            'content' => $this->baseSupportPayload('tel:+923111222427'),
            'support_cta_background_file' => UploadedFile::fake()->image('banner.jpg'),
        ])->assertRedirect();

        $resolver->publish($profile, ClientPageKeys::HOME, $admin->id);

        $published = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('status', ClientPageSettingStatus::Published)
            ->first();

        $this->assertSame('tel:+923111222427', data_get($published->content_json, 'support_cta.call_url'));
        $this->assertSame('uploaded_overlay', data_get($published->content_json, 'support_cta.background_mode'));

        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('href="tel:+923111222427"', false);
        $response->assertSee('--jp-support-bg: url(', false);
    }

    public function test_draft_tel_url_not_visible_until_publish(): void
    {
        $profile = $this->makeJetpkProfile();
        $user = User::factory()->create();
        $resolver = app(ClientPageContentResolver::class);

        $this->seedPublishedHome($profile, [
            'support_cta' => [
                'enabled' => '1',
                'call_enabled' => '1',
                'call_url' => 'tel:+920000000000',
                'chat_url' => '/support',
                'background_mode' => 'gradient',
            ],
        ]);

        $resolver->saveDraft($profile, ClientPageKeys::HOME, $this->baseSupportPayload('tel:+923111222427'), $user->id);

        $this->get('/')->assertOk()->assertSee('href="tel:+920000000000"', false)->assertDontSee('href="tel:+923111222427"', false);
    }

    public function test_published_media_url_retains_version_token_after_tel_url_publish(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $profile = $this->makeJetpkProfile();
        $admin = User::factory()->create([
            'account_type' => \App\Enums\AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $this->actingAs($admin)->patch('/admin/page-settings/home', [
            'content' => $this->baseSupportPayload('tel:+923111222427'),
            'support_cta_background_file' => UploadedFile::fake()->image('banner.jpg'),
        ])->assertRedirect();

        app(ClientPageContentResolver::class)->publish($profile, ClientPageKeys::HOME, $admin->id);

        $url = app(\App\Support\Client\JetpkHomepageSectionData::class)->assetUrl('support_cta_background');
        $this->assertNotNull($url);
        $this->assertStringContainsString('v=', $url);
    }
}
