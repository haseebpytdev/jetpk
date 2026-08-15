<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JetpkHomepageFixture;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

/**
 * JetPakistan homepage section customization via Client Page Settings CMS.
 * Legacy AgencyHomepageSection markup is no longer rendered on the JP home.
 */
class HomepageSectionCustomizationTest extends TestCase
{
    use JetpkHomepageFixture;
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->makeJetpkProfile();
        $this->seedJetpkAirports();
    }

    public function test_public_homepage_renders_default_sections_when_content_empty(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('id="jp-flight-search"', $html);
        $this->assertStringContainsString('data-jp-search', $html);
        $this->assertStringContainsString('Book flights across Pakistan', $html);
    }

    public function test_public_homepage_renders_admin_customized_trust_boxes(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'trust' => [
                'enabled' => '1',
                'eyebrow' => 'Trust',
                'title' => 'Why fly with us',
                'cards' => [
                    [
                        'enabled' => '1',
                        'icon' => 'headphones',
                        'title' => 'Always On',
                        'text' => 'Custom support line',
                        'sort_order' => 10,
                    ],
                ],
            ],
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Always On')
            ->assertSee('Custom support line');
    }

    public function test_public_homepage_renders_admin_customized_featured_fares(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'featured_deals' => [
                'enabled' => '1',
                'title' => 'Featured sample fares',
                'cta_text' => 'View fares',
                'cta_url' => '/flights/results',
                'card_count' => 3,
                'items' => [
                    [
                        'enabled' => '1',
                        'airline' => 'Custom Air',
                        'from' => 'LHE',
                        'to' => 'DXB',
                        'depart' => '10:00',
                        'arrive' => '13:00',
                        'dur' => '3h',
                        'stops' => 0,
                        'price' => 199999,
                        'sort_order' => 10,
                    ],
                ],
            ],
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Featured sample fares')
            ->assertSee('Custom Air')
            ->assertSee('View fares');
    }

    public function test_public_homepage_renders_admin_customized_popular_routes(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'routes' => [
                'enabled' => '1',
                'title' => 'Top corridors',
                'items' => [
                    [
                        'id' => 'route-isb-ist',
                        'from' => 'ISB',
                        'to' => 'IST',
                        'enabled' => '1',
                        'sort_order' => 10,
                        'trip_type' => 'one_way',
                        'manual_fallback_price' => 55000,
                        'dynamic_fare_enabled' => '0',
                    ],
                ],
            ],
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Top corridors')
            ->assertSee('ISB')
            ->assertSee('IST');
    }

    public function test_public_homepage_renders_admin_customized_why_book_cards(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'why_book' => [
                'enabled' => '1',
                'title' => 'Why travelers choose us',
                'subtitle' => 'Practical reasons to book with JetPakistan.',
                'cards' => [
                    [
                        'enabled' => '1',
                        'num' => '01',
                        'title' => 'Dedicated agents desk',
                        'text' => 'Agents get fast answers on fares and bookings.',
                        'sort_order' => 10,
                    ],
                ],
            ],
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Why travelers choose us')
            ->assertSee('Dedicated agents desk');
    }

    public function test_platform_admin_homepage_settings_redirect_to_cms(): void
    {
        $admin = $this->platformAdmin();

        $location = (string) $this->actingAs($admin)
            ->get(route('admin.settings.homepage.edit'))
            ->assertRedirect()
            ->headers
            ->get('Location');

        $this->assertTrue(
            str_contains($location, '/admin/dashboard/cms')
            || str_contains($location, 'page-settings')
            || str_contains($location, 'cms'),
            'Expected homepage settings GET to redirect into CMS. Got: '.$location
        );
    }

    public function test_non_admin_roles_cannot_access_homepage_sections_edit(): void
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();

        $this->actingAs($staff)->get(route('admin.settings.homepage.edit'))->assertForbidden();
        $this->actingAs($agent)->get(route('admin.settings.homepage.edit'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.settings.homepage.edit'))->assertForbidden();
        $this->get(route('admin.settings.homepage.edit'))->assertStatus(403);
    }

    public function test_disabled_section_and_item_are_hidden_on_homepage(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'trust' => [
                'enabled' => '0',
                'cards' => [],
            ],
            'why_book' => [
                'enabled' => '1',
                'title' => 'Why book',
                'cards' => [
                    [
                        'enabled' => '1',
                        'num' => '01',
                        'title' => 'Visible card',
                        'text' => 'Shown on homepage',
                        'sort_order' => 10,
                    ],
                    [
                        'enabled' => '0',
                        'num' => '02',
                        'title' => 'Hidden card',
                        'text' => 'Should not appear',
                        'sort_order' => 20,
                    ],
                ],
            ],
        ]);

        $response = $this->get(route('home'))->assertOk();
        $response->assertDontSee('class="trust-grid"', false);
        $response->assertSee('Visible card');
        $response->assertDontSee('Hidden card');
    }

    public function test_sort_order_is_respected_for_trust_boxes(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'trust' => [
                'enabled' => '1',
                'title' => 'Trust order',
                'cards' => [
                    [
                        'enabled' => '1',
                        'icon' => 'users',
                        'title' => 'Second label',
                        'text' => 'Second',
                        'sort_order' => 20,
                    ],
                    [
                        'enabled' => '1',
                        'icon' => 'check-circle',
                        'title' => 'First label',
                        'text' => 'First',
                        'sort_order' => 10,
                    ],
                ],
            ],
        ]);

        $html = (string) $this->get(route('home'))->assertOk()->getContent();
        $firstPos = strpos($html, 'First label');
        $secondPos = strpos($html, 'Second label');
        $this->assertNotFalse($firstPos);
        $this->assertNotFalse($secondPos);
        $this->assertTrue($firstPos < $secondPos);
    }

    public function test_unsafe_button_url_is_rejected_on_admin_save(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $this->makeJetpkProfile();

        $this->actingAs($admin)
            ->patch(route('admin.settings.homepage.update', 'popular_routes'), [
                'is_enabled' => 1,
                'items' => [
                    [
                        'item_key' => 'default-0',
                        'from' => 'LHE',
                        'to' => 'DXB',
                        'label' => 'Test route',
                        'button_url' => 'javascript:alert(1)',
                        'is_enabled' => 1,
                        'sort_order' => 10,
                    ],
                ],
            ])
            ->assertSessionHasErrors();
    }

}
