<?php

namespace Tests\Feature\Jetpk;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Jetpk\Concerns\BuildsJetpkPortalTestFixtures;
use Tests\TestCase;

/**
 * JP-PORTAL-2A — Customer resolver migration.
 */
class JetpkCustomerPortalResolverTest extends TestCase
{
    use BuildsJetpkPortalTestFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootJetpkPortalContext();
    }

    /** @return array<int, array{0: string}> */
    public static function customerPageRoutes(): array
    {
        return [
            ['customer.dashboard'],
            ['customer.bookings.index'],
            ['customer.travelers.index'],
            ['customer.travelers.create'],
            ['customer.support.tickets.index'],
            ['customer.support.tickets.create'],
            ['profile.edit'],
        ];
    }

    #[DataProvider('customerPageRoutes')]
    public function test_customer_page_returns_200(string $routeName): void
    {
        $this->actingAs($this->customerUser())
            ->get(route($routeName))
            ->assertOk();
    }

    public function test_customer_views_resolve_through_the_resolver(): void
    {
        $resolver = app(\App\Services\Client\RuntimeViewResolver::class);

        foreach ([
            'support.tickets.index',
            'support.tickets.create',
            'support.tickets.show',
            'travelers.index',
            'travelers.create',
            'travelers.edit',
        ] as $logical) {
            $resolved = $resolver->view($logical, 'customer');

            $this->assertTrue(
                view()->exists($resolved),
                "client_view('{$logical}', 'customer') resolved to a non-existent view: {$resolved}"
            );
        }
    }

    public function test_legacy_customer_views_still_exist_for_default_clients(): void
    {
        foreach ([
            'dashboard.customer.support.tickets.index',
            'dashboard.customer.support.tickets.create',
            'dashboard.customer.support.tickets.show',
            'dashboard.travelers.index',
            'dashboard.travelers.create',
            'dashboard.travelers.edit',
        ] as $legacy) {
            $this->assertTrue(view()->exists($legacy), "fallback view missing: {$legacy}");
        }
    }

    public function test_customer_cannot_reach_agent_routes(): void
    {
        $this->actingAs($this->customerUser())
            ->get(route('agent.dashboard'))
            ->assertForbidden();
    }
}
