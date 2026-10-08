<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountType;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Server-side Laravel agent portal RBAC (Blade /agent/* routes).
 * Next.js /agent/dashboard redirects are covered by frontend portal guard tests.
 */
class CrossPortalAgentDashboardRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_customer_cannot_access_laravel_agent_dashboard(): void
    {
        $customer = User::query()->where('account_type', AccountType::Customer)->firstOrFail();

        $this->actingAs($customer)
            ->get(route('agent.dashboard'))
            ->assertForbidden();
    }

    public function test_customer_cannot_access_laravel_agent_bookings(): void
    {
        $customer = User::query()->where('account_type', AccountType::Customer)->firstOrFail();

        $this->actingAs($customer)
            ->get(route('agent.bookings.index'))
            ->assertForbidden();
    }

    public function test_staff_cannot_access_laravel_agent_dashboard(): void
    {
        $staff = User::query()->where('account_type', AccountType::Staff)->firstOrFail();

        $this->actingAs($staff)
            ->get(route('agent.dashboard'))
            ->assertForbidden();
    }

    public function test_customer_session_bootstrap_points_to_customer_dashboard(): void
    {
        $customer = User::query()->where('account_type', AccountType::Customer)->firstOrFail();

        $response = $this->actingAs($customer)->getJson('/api/public/auth/session');

        $response->assertOk();
        $response->assertJsonPath('authenticated', true);
        $dashboardUrl = (string) $response->json('dashboard_url');
        $this->assertTrue(
            str_starts_with($dashboardUrl, '/customer'),
            'Expected customer dashboard path, got: '.$dashboardUrl,
        );
    }
}
