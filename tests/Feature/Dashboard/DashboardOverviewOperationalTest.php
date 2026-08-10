<?php

namespace Tests\Feature\Dashboard;

use App\Models\Booking;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class DashboardOverviewOperationalTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_admin_overview_returns_operational_sections(): void
    {
        $admin = $this->platformAdmin();
        Booking::factory()->create();

        $this->actingAs($admin)
            ->getJson(route('api.dashboard.overview'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summaryStats',
                    'operationalQueues',
                    'bookingPipeline',
                    'supplierStatus',
                    'paymentOperations',
                    'supportOperations',
                    'systemHealth',
                    'recentBookings',
                ],
            ])
            ->assertJsonPath('data.hasLiveData', true);
    }

    public function test_admin_session_identity_is_not_preview_fixture(): void
    {
        $admin = $this->platformAdmin();

        $response = $this->actingAs($admin)
            ->getJson(route('api.dashboard.session', ['portal' => 'admin']))
            ->assertOk();

        $displayName = $response->json('data.displayName');
        $this->assertNotSame('Preview Admin', $displayName);
        $this->assertSame($admin->name, $displayName);
    }

    public function test_dashboard_search_returns_booking_matches_for_admin(): void
    {
        $admin = $this->platformAdmin();
        $booking = Booking::factory()->create([
            'booking_reference' => 'JPSEARCHTEST01',
        ]);

        $this->actingAs($admin)
            ->getJson(route('api.dashboard.search', ['q' => 'JPSEARCHTEST01']))
            ->assertOk()
            ->assertJsonPath('data.results.0.type', 'booking')
            ->assertJsonPath('data.results.0.label', 'JPSEARCHTEST01');
    }

    public function test_customer_cannot_access_dashboard_search(): void
    {
        $customer = User::factory()->create([
            'account_type' => \App\Enums\AccountType::Customer,
        ]);

        $this->actingAs($customer)
            ->getJson(route('api.dashboard.search', ['q' => 'test']))
            ->assertForbidden();
    }
}
