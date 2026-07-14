<?php

namespace Tests\Feature;

use App\Enums\HomepageFeaturedFareRefreshStatus;
use App\Models\Agency;
use App\Models\AgencyHomepageSection;
use App\Models\HomepageFeaturedFare;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageSectionCustomizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_public_homepage_renders_default_sections_when_content_empty(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('24/7 travel support')
            ->assertSee('Popular corridors')
            ->assertSee('Reliable booking support')
            ->assertSee('LHE')
            ->assertSee('DXB');
    }

    public function test_public_homepage_renders_admin_customized_trust_boxes(): void
    {
        $agency = $this->defaultAgency();
        AgencyHomepageSection::query()->updateOrCreate(
            ['agency_id' => $agency->id, 'section_key' => 'trust_metrics'],
            [
                'is_enabled' => true,
                'content' => [
                    'metrics' => [
                        [
                            'item_key' => 'default-0',
                            'value' => 'Always On',
                            'label' => 'Custom support line',
                            'icon' => 'headphones',
                            'is_enabled' => true,
                            'sort_order' => 10,
                        ],
                    ],
                ],
            ],
        );

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Always On')
            ->assertSee('Custom support line')
            ->assertDontSee('Transparent booking process');
    }

    public function test_public_homepage_renders_admin_customized_featured_fares(): void
    {
        $agency = $this->defaultAgency();
        AgencyHomepageSection::query()->updateOrCreate(
            ['agency_id' => $agency->id, 'section_key' => 'feature_cards'],
            [
                'is_enabled' => true,
                'title' => 'Featured sample fares',
                'content' => ['cards' => []],
            ],
        );

        HomepageFeaturedFare::query()->create([
            'agency_id' => $agency->id,
            'origin_code' => 'LHE',
            'destination_code' => 'DXB',
            'date_offset_days' => 7,
            'is_enabled' => true,
            'last_status' => HomepageFeaturedFareRefreshStatus::Success,
            'snapshot' => [
                'origin_code' => 'LHE',
                'destination_code' => 'DXB',
                'airline_name' => 'Custom Air',
                'airline_code' => 'CA',
                'departure_date' => now()->addDays(7)->toDateString(),
                'price_total' => 199999,
                'currency' => 'PKR',
                'refundable_label' => 'Non-refundable',
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
        $agency = $this->defaultAgency();
        AgencyHomepageSection::query()->updateOrCreate(
            ['agency_id' => $agency->id, 'section_key' => 'popular_routes'],
            [
                'is_enabled' => true,
                'title' => 'Top corridors',
                'content' => [
                    'routes' => [
                        [
                            'item_key' => 'default-0',
                            'label' => 'Islamabad to Istanbul',
                            'from' => 'ISB',
                            'to' => 'IST',
                            'button_url' => '',
                            'is_enabled' => true,
                            'sort_order' => 10,
                        ],
                    ],
                ],
            ],
        );

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Top corridors')
            ->assertSee('Islamabad to Istanbul')
            ->assertSee('ISB')
            ->assertSee('IST');
    }

    public function test_public_homepage_renders_admin_customized_why_book_cards(): void
    {
        $agency = $this->defaultAgency();
        AgencyHomepageSection::query()->updateOrCreate(
            ['agency_id' => $agency->id, 'section_key' => 'why_choose_us'],
            [
                'is_enabled' => true,
                'title' => 'Why travelers choose us',
                'content' => [
                    'bullets' => [
                        [
                            'item_key' => 'default-0',
                            'title' => 'Dedicated agents desk',
                            'text' => 'Agents get fast answers on fares and bookings.',
                            'icon' => 'users',
                            'is_enabled' => true,
                            'sort_order' => 10,
                        ],
                    ],
                ],
            ],
        );

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Why travelers choose us')
            ->assertSee('Dedicated agents desk');
    }

    public function test_agency_admin_can_access_homepage_sections_settings_page(): void
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.settings.homepage.edit'))
            ->assertOk()
            ->assertSee('Homepage Sections')
            ->assertSee('Trust / stat boxes')
            ->assertSee('Featured fares');
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
        $agency = $this->defaultAgency();
        AgencyHomepageSection::query()->updateOrCreate(
            ['agency_id' => $agency->id, 'section_key' => 'trust_metrics'],
            ['is_enabled' => false, 'content' => ['metrics' => []]],
        );
        AgencyHomepageSection::query()->updateOrCreate(
            ['agency_id' => $agency->id, 'section_key' => 'why_choose_us'],
            [
                'is_enabled' => true,
                'content' => [
                    'bullets' => [
                        [
                            'item_key' => 'default-0',
                            'title' => 'Visible card',
                            'text' => 'Shown on homepage',
                            'icon' => 'shield',
                            'is_enabled' => true,
                            'sort_order' => 10,
                        ],
                        [
                            'item_key' => 'default-1',
                            'title' => 'Hidden card',
                            'text' => 'Should not appear',
                            'icon' => 'bolt',
                            'is_enabled' => false,
                            'sort_order' => 20,
                        ],
                    ],
                ],
            ],
        );

        $response = $this->get(route('home'))->assertOk();
        $response->assertDontSee('id="metrics"', false);
        $response->assertSee('Visible card');
        $response->assertDontSee('Hidden card');
    }

    public function test_sort_order_is_respected_for_trust_boxes(): void
    {
        $agency = $this->defaultAgency();
        AgencyHomepageSection::query()->updateOrCreate(
            ['agency_id' => $agency->id, 'section_key' => 'trust_metrics'],
            [
                'is_enabled' => true,
                'content' => [
                    'metrics' => [
                        [
                            'item_key' => 'default-0',
                            'value' => 'Second',
                            'label' => 'Second label',
                            'icon' => 'users',
                            'is_enabled' => true,
                            'sort_order' => 20,
                        ],
                        [
                            'item_key' => 'default-1',
                            'value' => 'First',
                            'label' => 'First label',
                            'icon' => 'check-circle',
                            'is_enabled' => true,
                            'sort_order' => 10,
                        ],
                    ],
                ],
            ],
        );

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
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

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

    protected function defaultAgency(): Agency
    {
        return Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();
    }
}
