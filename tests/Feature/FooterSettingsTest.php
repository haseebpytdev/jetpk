<?php

namespace Tests\Feature;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Models\User;
use App\Support\Client\ClientPageKeys;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JetpkHomepageFixture;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

/**
 * JetPakistan public footer is CMS-owned (ClientPageKeys::FOOTER).
 * Legacy AgencyFooterSettingsController remains for compatibility/validation.
 */
class FooterSettingsTest extends TestCase
{
    use JetpkHomepageFixture;
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->makeJetpkProfile();
    }

    public function test_public_footer_renders_default_structured_sections(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('class="footer"', false)
            ->assertSee('Company', false)
            ->assertSee('Support', false)
            ->assertSee('Policies', false)
            ->assertSee('B2B', false)
            ->assertSee('agents', false)
            ->assertSee('IATA', false)
            ->assertSee('PCI-DSS', false);
    }

    public function test_public_footer_shows_jetpk_trust_badges(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringContainsString('IATA', $html);
        $this->assertStringContainsString('PCAA', $html);
        $this->assertStringContainsString('PCI-DSS', $html);
    }

    public function test_platform_admin_footer_settings_redirect_to_cms(): void
    {
        $admin = $this->platformAdmin();

        $location = (string) $this->actingAs($admin)
            ->get(route('admin.settings.branding.footer.edit'))
            ->assertRedirect()
            ->headers
            ->get('Location');

        $this->assertTrue(
            str_contains($location, '/admin/dashboard/settings')
            || str_contains($location, 'cms')
            || str_contains($location, 'page-settings'),
            'Expected footer settings GET to redirect into dashboard settings/CMS. Got: '.$location
        );
    }

    public function test_staff_customer_agent_cannot_edit_footer_settings(): void
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $customer = User::factory()->create();

        $this->actingAs($staff)->patch(route('admin.settings.branding.footer.update'))->assertForbidden();
        $this->actingAs($agent)->patch(route('admin.settings.branding.footer.update'))->assertForbidden();
        $this->actingAs($customer)->patch(route('admin.settings.branding.footer.update'))->assertForbidden();
        $this->patch(route('admin.settings.branding.footer.update'))->assertForbidden();
    }

    public function test_cms_footer_custom_intro_and_columns_render_on_homepage(): void
    {
        $profile = $this->makeJetpkProfile();
        ClientPageSetting::query()->updateOrCreate(
            [
                'client_profile_id' => $profile->id,
                'page_key' => ClientPageKeys::FOOTER,
                'status' => ClientPageSettingStatus::Published,
            ],
            [
                'content_json' => [
                    'description' => [
                        'text' => 'Custom footer about text for tests.',
                    ],
                    'columns' => [
                        [
                            'id' => 'foot-company',
                            'title' => 'Our Company',
                            'enabled' => '1',
                            'sort_order' => 0,
                            'links' => [
                                [
                                    'id' => 'about',
                                    'label' => 'About us',
                                    'url' => '/about-us',
                                    'enabled' => '1',
                                    'sort_order' => 0,
                                ],
                                [
                                    'id' => 'hidden',
                                    'label' => 'Hidden link',
                                    'url' => '/support',
                                    'enabled' => '0',
                                    'sort_order' => 1,
                                ],
                            ],
                        ],
                    ],
                    'legal' => [
                        'copyright' => '© {year} JetPakistan test.',
                    ],
                    'social' => [],
                ],
            ],
        );

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Custom footer about text for tests.', false)
            ->assertSee('Our Company', false)
            ->assertSee('About us', false)
            ->assertDontSee('Hidden link', false);
    }

    public function test_sort_order_is_respected_for_footer_column_links(): void
    {
        $profile = $this->makeJetpkProfile();
        ClientPageSetting::query()->updateOrCreate(
            [
                'client_profile_id' => $profile->id,
                'page_key' => ClientPageKeys::FOOTER,
                'status' => ClientPageSettingStatus::Published,
            ],
            [
                'content_json' => [
                    'description' => ['text' => 'Sort footer'],
                    'columns' => [
                        [
                            'id' => 'foot-support',
                            'title' => 'Support',
                            'enabled' => '1',
                            'sort_order' => 0,
                            'links' => [
                                [
                                    'id' => 'z',
                                    'label' => 'Zebra link',
                                    'url' => '/support',
                                    'enabled' => '1',
                                    'sort_order' => 30,
                                ],
                                [
                                    'id' => 'a',
                                    'label' => 'Alpha link',
                                    'url' => '/about-us',
                                    'enabled' => '1',
                                    'sort_order' => 10,
                                ],
                            ],
                        ],
                    ],
                    'legal' => ['copyright' => '© {year}'],
                    'social' => [],
                ],
            ],
        );

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertLessThan(
            strpos($html, 'Zebra link'),
            strpos($html, 'Alpha link'),
        );
    }

    public function test_cms_footer_legal_copyright_renders(): void
    {
        $profile = $this->makeJetpkProfile();
        ClientPageSetting::query()->updateOrCreate(
            [
                'client_profile_id' => $profile->id,
                'page_key' => ClientPageKeys::FOOTER,
                'status' => ClientPageSettingStatus::Published,
            ],
            [
                'content_json' => [
                    'description' => ['text' => 'Legal footer'],
                    'columns' => [],
                    'legal' => [
                        'copyright' => '© {year} Privacy Policy notice.',
                    ],
                    'social' => [],
                ],
            ],
        );

        $year = date('Y');
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('© '.$year.' Privacy Policy notice.', false);
    }

    public function test_legacy_footer_update_accepts_valid_hex_for_platform_admin(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->patch(route('admin.settings.branding.footer.update'), $this->minimalLegacyFooterPayload([
                'style' => [
                    'background_color' => '#E2E8F0',
                    'bottom_bar_background_color' => '#F1F5F9',
                    'text_color' => '#334155',
                    'heading_color' => '#0F172A',
                    'link_color' => '#1E3A5F',
                    'link_hover_color' => '#0C4A6E',
                    'accent_color' => '#0284C7',
                    'spacing' => 'normal',
                    'columns' => '5',
                ],
            ]))
            ->assertRedirect();
    }

    public function test_legacy_footer_update_rejects_invalid_hex(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->patch(route('admin.settings.branding.footer.update'), $this->minimalLegacyFooterPayload([
                'style' => [
                    'background_color' => 'not-a-color',
                    'bottom_bar_background_color' => '#F1F5F9',
                    'text_color' => '#334155',
                    'heading_color' => '#0F172A',
                    'link_color' => '#1E3A5F',
                    'link_hover_color' => '#0C4A6E',
                    'accent_color' => '#0284C7',
                    'spacing' => 'normal',
                    'columns' => '5',
                ],
            ]))
            ->assertSessionHasErrors('style.background_color');
    }

    public function test_legacy_footer_update_rejects_javascript_url(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->patch(route('admin.settings.branding.footer.update'), $this->minimalLegacyFooterPayload([
                'menu_sections' => [
                    'company' => [
                        'heading' => 'Company',
                        'is_enabled' => '1',
                        'sort_order' => '20',
                        'items' => [
                            0 => [
                                'label' => 'Bad',
                                'url' => 'javascript:alert(1)',
                                'is_enabled' => '1',
                                'sort_order' => '10',
                            ],
                        ],
                    ],
                ],
            ]))
            ->assertSessionHasErrors();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function minimalLegacyFooterPayload(array $overrides = []): array
    {
        $base = [
            'brand' => [
                'name' => 'JetPakistan',
                'description' => 'About',
                'use_brand_logo' => '1',
                'show_logo' => '1',
            ],
            'support_card' => [
                'is_enabled' => '1',
                'title' => '24/7 Support',
                'subtitle' => 'Help',
                'icon' => 'headphones',
            ],
            'contact' => [
                'heading' => 'Get In Touch',
                'phone' => '+92 300 0000000',
                'email' => 'support@example.com',
                'whatsapp' => '923000000000',
                'city' => 'Lahore',
                'address' => 'Main Boulevard',
                'is_enabled' => '1',
            ],
            'bottom_bar' => [
                'copyright' => '© Test',
                'disclaimer' => 'Subject to airline confirmation.',
                'legal_links' => [],
                'trust_badges' => [
                    0 => ['label' => 'SSL Secure', 'is_enabled' => '1', 'sort_order' => '10'],
                ],
            ],
            'style' => [
                'background_color' => '#0F172A',
                'bottom_bar_background_color' => '#020617',
                'text_color' => '#E2E8F0',
                'heading_color' => '#FFFFFF',
                'link_color' => '#93C5FD',
                'link_hover_color' => '#BFDBFE',
                'accent_color' => '#38BDF8',
                'spacing' => 'normal',
                'columns' => '5',
            ],
            'menu_sections' => [
                'company' => [
                    'heading' => 'Company',
                    'is_enabled' => '1',
                    'sort_order' => '10',
                    'items' => [],
                ],
            ],
        ];

        return array_replace_recursive($base, $overrides);
    }
}
