<?php

namespace Tests\Feature;

use App\Models\CmsPage;
use Database\Seeders\DefaultCmsPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultCmsPagesSeederTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const EXPECTED_SLUGS = [
        'refund-policy',
        'cancellation-policy',
        'privacy-policy',
        'terms-and-conditions',
        'payment-policy',
        'baggage-policy',
        'travel-advisory',
        'agent-terms',
        'wallet-credit-policy',
    ];

    public function test_seeder_creates_nine_default_pages(): void
    {
        $this->seed(DefaultCmsPagesSeeder::class);

        $this->assertDatabaseCount('cms_pages', 9);
    }

    public function test_seeder_is_idempotent_and_does_not_duplicate_pages(): void
    {
        $this->seed(DefaultCmsPagesSeeder::class);
        $this->seed(DefaultCmsPagesSeeder::class);

        $this->assertDatabaseCount('cms_pages', 9);

        foreach (self::EXPECTED_SLUGS as $slug) {
            $this->assertSame(1, CmsPage::query()->where('slug', $slug)->count());
        }
    }

    public function test_active_footer_visible_pages_exist_with_expected_slugs(): void
    {
        $this->seed(DefaultCmsPagesSeeder::class);

        foreach (self::EXPECTED_SLUGS as $slug) {
            $this->assertDatabaseHas('cms_pages', [
                'slug' => $slug,
                'status' => CmsPage::STATUS_ACTIVE,
                'show_in_footer' => true,
            ]);
        }
    }

    public function test_agent_b2b_pages_use_noindex_robots(): void
    {
        $this->seed(DefaultCmsPagesSeeder::class);

        $this->assertDatabaseHas('cms_pages', [
            'slug' => 'agent-terms',
            'robots' => CmsPage::ROBOTS_NOINDEX,
        ]);

        $this->assertDatabaseHas('cms_pages', [
            'slug' => 'wallet-credit-policy',
            'robots' => CmsPage::ROBOTS_NOINDEX,
        ]);
    }

    public function test_seeded_refund_policy_is_index_follow_publicly(): void
    {
        $this->seed(DefaultCmsPagesSeeder::class);

        $this->get(route('pages.show', 'refund-policy'))
            ->assertOk()
            ->assertSee('name="robots" content="index, follow"', false);
    }

    public function test_seeded_agent_terms_and_wallet_credit_policy_are_noindex_publicly(): void
    {
        $this->seed(DefaultCmsPagesSeeder::class);

        $this->get(route('pages.show', 'agent-terms'))
            ->assertOk()
            ->assertSee('name="robots" content="noindex, nofollow"', false);

        $this->get(route('pages.show', 'wallet-credit-policy'))
            ->assertOk()
            ->assertSee('name="robots" content="noindex, nofollow"', false);
    }
}
