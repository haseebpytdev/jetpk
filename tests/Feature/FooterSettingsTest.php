<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
use App\Services\Agencies\AgencyBrandingService;
use App\Services\Agencies\FooterSettingsPresenter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FooterSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_public_footer_renders_default_structured_sections(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('ota-footer-pro', false)
            ->assertSee('Company', false)
            ->assertSee('Support', false)
            ->assertSee('Explore', false)
            ->assertSee('Get In Touch', false)
            ->assertSee('24/7 Support', false)
            ->assertSee('Subject to airline confirmation.', false)
            ->assertSee('SSL Secure', false);
    }

    public function test_public_footer_hides_unverified_iata_and_pci_badges(): void
    {
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug', 'asif-travels'))->firstOrFail();
        $settings = app(AgencyBrandingService::class)->getSettingsForAgency($agency);
        $labels = collect(app(FooterSettingsPresenter::class)->presentForPublic($settings)['bottom_bar']['trust_badges'])
            ->pluck('label')
            ->all();

        $this->assertContains('SSL Secure', $labels);
        $this->assertNotContains('IATA', $labels);
        $this->assertNotContains('PCI DSS', $labels);
    }

    public function test_admin_can_access_footer_settings_page(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get(route('admin.settings.branding.footer.edit'))
            ->assertOk()
            ->assertSee('Branding / Footer', false)
            ->assertSee('Menu: Company', false)
            ->assertSee('Add legal link', false);
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

    public function test_admin_can_update_brand_about_and_menu_items(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->adminUser();

        $payload = $this->validFooterPayload([
            'brand' => [
                'name' => 'Asif Travels Custom',
                'description' => 'Custom footer about text for tests.',
                'use_brand_logo' => '1',
                'show_logo' => '1',
            ],
            'menu_sections' => [
                'company' => [
                    'heading' => 'Our Company',
                    'is_enabled' => '1',
                    'sort_order' => '20',
                    'items' => [
                        0 => [
                            'item_key' => 'company-0',
                            'label' => 'About us',
                            'url' => '/about-us',
                            'is_enabled' => '1',
                            'sort_order' => '10',
                        ],
                        1 => [
                            'item_key' => 'company-1',
                            'label' => 'Hidden link',
                            'url' => '/support',
                            'is_enabled' => '0',
                            'sort_order' => '20',
                        ],
                    ],
                ],
            ],
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.settings.branding.footer.update'), $payload)
            ->assertRedirect();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Custom footer about text for tests.', false)
            ->assertSee('Our Company', false)
            ->assertSee('About us', false)
            ->assertDontSee('Hidden link', false);
    }

    public function test_sort_order_is_respected_for_menu_items(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->adminUser();

        $payload = $this->validFooterPayload([
            'menu_sections' => [
                'support' => [
                    'heading' => 'Support',
                    'is_enabled' => '1',
                    'sort_order' => '30',
                    'items' => [
                        0 => ['label' => 'Zebra link', 'url' => '/support', 'is_enabled' => '1', 'sort_order' => '30'],
                        1 => ['label' => 'Alpha link', 'url' => '/about-us', 'is_enabled' => '1', 'sort_order' => '10'],
                    ],
                ],
            ],
        ]);

        $this->actingAs($admin)->patch(route('admin.settings.branding.footer.update'), $payload);

        $html = $this->get(route('home'))->getContent();
        $this->assertNotFalse($html);
        $this->assertLessThan(
            strpos($html, 'Zebra link'),
            strpos($html, 'Alpha link'),
        );
    }

    public function test_admin_can_add_legal_link_for_future_page(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->adminUser();

        $payload = $this->validFooterPayload([
            'bottom_bar' => array_merge($this->validFooterPayload()['bottom_bar'], [
                'legal_links' => [
                    0 => [
                        'label' => 'Privacy Policy',
                        'url' => '/privacy-policy',
                        'is_enabled' => '1',
                        'sort_order' => '10',
                    ],
                ],
            ]),
        ]);

        $this->actingAs($admin)->patch(route('admin.settings.branding.footer.update'), $payload);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Privacy Policy', false);
    }

    public function test_valid_hex_color_is_accepted(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->adminUser();

        $payload = $this->validFooterPayload([
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
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.settings.branding.footer.update'), $payload)
            ->assertRedirect();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('--ota-footer-bg: #E2E8F0', false);
    }

    public function test_invalid_hex_color_is_rejected(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->adminUser();

        $payload = $this->validFooterPayload([
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
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.settings.branding.footer.update'), $payload)
            ->assertSessionHasErrors('style.background_color');
    }

    public function test_javascript_url_is_rejected(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->adminUser();

        $payload = $this->validFooterPayload([
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
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.settings.branding.footer.update'), $payload)
            ->assertSessionHasErrors();
    }

    protected function adminUser(): User
    {
        return User::query()->where('email', 'admin@ota.demo')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validFooterPayload(array $overrides = []): array
    {
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug', 'asif-travels'))->firstOrFail();
        $settings = app(AgencyBrandingService::class)->getSettingsForAgency($agency);
        $presenter = app(FooterSettingsPresenter::class);
        $admin = $presenter->presentForAdmin($settings);

        $base = [
            'brand' => [
                'name' => $admin['brand']['name'] ?? 'Asif Travels',
                'description' => $admin['brand']['description'] ?? 'About',
                'use_brand_logo' => '1',
                'show_logo' => '1',
            ],
            'support_card' => [
                'is_enabled' => '1',
                'title' => $admin['support_card']['title'] ?? '24/7 Support',
                'subtitle' => $admin['support_card']['subtitle'] ?? 'Help',
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
            'style' => $admin['style'],
            'menu_sections' => [],
        ];

        foreach (FooterSettingsPresenter::MENU_SECTION_KEYS as $key) {
            $section = collect($admin['menu_sections'])->firstWhere('section_key', $key);
            $base['menu_sections'][$key] = [
                'heading' => $section['heading'] ?? ucfirst($key),
                'is_enabled' => '1',
                'sort_order' => (string) ($section['sort_order'] ?? 10),
                'items' => collect($section['items'] ?? [])->values()->all(),
            ];
        }

        return array_replace_recursive($base, $overrides);
    }
}
