<?php

namespace Tests\Feature\Onboarding;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\User;
use App\Support\Onboarding\DashboardTourAuthority;
use App\Support\Staff\StaffPermission;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class DashboardTourStateTest extends TestCase
{
    use BuildsAgentPortalScenario;
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_customer_first_visit_complete_skip_and_restart(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->customer()->create([
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);
        $agency->users()->attach($customer->id, ['role' => 'customer']);

        $show = $this->actingAs($customer)
            ->getJson(route('customer.dashboard-tours.show'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('tour_key', DashboardTourAuthority::CUSTOMER_TOUR)
            ->assertJsonPath('should_auto_start', true);

        $stepIds = collect($show->json('steps'))->pluck('id')->all();
        $this->assertContains('welcome', $stepIds);
        $this->assertContains('bookings', $stepIds);
        $this->assertContains('support', $stepIds);

        $this->actingAs($customer)
            ->patchJson(route('customer.dashboard-tours.update'), [
                'tour_key' => DashboardTourAuthority::CUSTOMER_TOUR,
                'status' => 'completed',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('tours.'.DashboardTourAuthority::CUSTOMER_TOUR.'.status', 'completed');

        $this->actingAs($customer)
            ->getJson(route('customer.dashboard-tours.show'))
            ->assertOk()
            ->assertJsonPath('should_auto_start', false);

        $this->actingAs($customer)
            ->patchJson(route('customer.dashboard-tours.update'), [
                'tour_key' => DashboardTourAuthority::CUSTOMER_TOUR,
                'status' => 'skipped',
            ])
            ->assertOk()
            ->assertJsonPath('tours.'.DashboardTourAuthority::CUSTOMER_TOUR.'.status', 'skipped');

        $this->actingAs($customer)
            ->patchJson(route('customer.dashboard-tours.update'), [
                'tour_key' => DashboardTourAuthority::CUSTOMER_TOUR,
                'restart' => true,
            ])
            ->assertOk();

        $this->actingAs($customer)
            ->getJson(route('customer.dashboard-tours.show'))
            ->assertOk()
            ->assertJsonPath('should_auto_start', true);

        $customer->refresh();
        $this->assertArrayNotHasKey(
            DashboardTourAuthority::CUSTOMER_TOUR,
            $customer->meta[DashboardTourAuthority::META_KEY] ?? [],
        );
    }

    public function test_customer_cannot_patch_another_users_tour_via_user_id(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        $customerA = User::factory()->customer()->create([
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);
        $customerB = User::factory()->customer()->create([
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
            'meta' => [
                DashboardTourAuthority::META_KEY => [
                    DashboardTourAuthority::CUSTOMER_TOUR => [
                        'status' => 'completed',
                        'at' => now()->toIso8601String(),
                    ],
                ],
            ],
        ]);
        $agency->users()->attach($customerA->id, ['role' => 'customer']);
        $agency->users()->attach($customerB->id, ['role' => 'customer']);

        $this->actingAs($customerA)
            ->patchJson(route('customer.dashboard-tours.update'), [
                'tour_key' => DashboardTourAuthority::CUSTOMER_TOUR,
                'status' => 'skipped',
                'user_id' => $customerB->id,
            ])
            ->assertStatus(422);

        $customerB->refresh();
        $this->assertSame(
            'completed',
            $customerB->meta[DashboardTourAuthority::META_KEY][DashboardTourAuthority::CUSTOMER_TOUR]['status'] ?? null,
        );

        $this->actingAs($customerA)
            ->patchJson(route('customer.dashboard-tours.update'), [
                'tour_key' => DashboardTourAuthority::CUSTOMER_TOUR,
                'status' => 'skipped',
            ])
            ->assertOk();

        $customerA->refresh();
        $this->assertSame(
            'skipped',
            $customerA->meta[DashboardTourAuthority::META_KEY][DashboardTourAuthority::CUSTOMER_TOUR]['status'] ?? null,
        );
        $customerB->refresh();
        $this->assertSame(
            'completed',
            $customerB->meta[DashboardTourAuthority::META_KEY][DashboardTourAuthority::CUSTOMER_TOUR]['status'] ?? null,
        );
    }

    public function test_agent_steps_are_permission_aware(): void
    {
        $scenario = $this->buildAgentPortalScenario();
        $restricted = $scenario['staff']['A0'];

        $response = $this->actingAs($restricted)
            ->getJson(route('agent.dashboard-tours.show'))
            ->assertOk();

        $stepIds = collect($response->json('steps'))->pluck('id')->all();
        $this->assertContains('welcome', $stepIds);
        $this->assertContains('overview', $stepIds);
        $this->assertContains('profile', $stepIds);

        // Restricted staff from scenario should not receive wallet/bookings unless granted.
        if (! $restricted->hasAgentPermission(\App\Support\Agents\AgentPermission::WalletView)) {
            $this->assertNotContains('wallet', $stepIds);
        }
        if (! $restricted->hasAgentPermission(\App\Support\Agents\AgentPermission::BookingsView)) {
            $this->assertNotContains('bookings', $stepIds);
        }
    }

    public function test_staff_steps_exclude_admin_only_targets(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill([
            'account_type' => AccountType::Staff,
            'meta' => [
                'staff_permissions' => [
                    StaffPermission::BookingsView,
                    StaffPermission::SupportView,
                ],
            ],
        ])->save();

        $response = $this->actingAs($staff->fresh())
            ->getJson('/api/dashboard/tours?portal=staff')
            ->assertOk();

        $data = $response->json('data');
        $this->assertSame(DashboardTourAuthority::STAFF_TOUR, $data['tour_key'] ?? null);
        $stepIds = collect($data['steps'] ?? [])->pluck('id')->all();
        $this->assertContains('welcome', $stepIds);
        $this->assertNotContains('agent-applications', $stepIds);
        $this->assertNotContains('roles-permissions', $stepIds);
        $this->assertNotContains('system-health', $stepIds);
        $this->assertNotContains('markups', $stepIds);
        $this->assertNotContains('go-live', $stepIds);
        $this->assertNotContains('staff', $stepIds);
    }

    public function test_admin_tour_includes_api_settings_step(): void
    {
        $admin = $this->platformAdmin();

        $response = $this->actingAs($admin)
            ->getJson('/api/dashboard/tours?portal=admin')
            ->assertOk();

        $stepIds = collect($response->json('data.steps'))->pluck('id')->all();
        $this->assertContains('api-modules', $stepIds);
        $this->assertSame(DashboardTourAuthority::ADMIN_TOUR, $response->json('data.tour_key'));
    }

    public function test_public_and_guest_cannot_access_tour_endpoints(): void
    {
        $this->getJson(route('customer.dashboard-tours.show'))->assertUnauthorized();
        $this->getJson(route('agent.dashboard-tours.show'))->assertUnauthorized();
        $this->getJson('/api/dashboard/tours')->assertUnauthorized();

        $this->get('/')->assertOk();
        // Public homepage must not embed dashboard tour host markers.
        $home = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-testid="customer-dashboard-tour-overlay"', $home);
        $this->assertStringNotContainsString('data-testid="agent-dashboard-tour-overlay"', $home);
        $this->assertStringNotContainsString('data-testid="backoffice-dashboard-tour-overlay"', $home);
        $this->assertStringNotContainsString('jp-dashboard-tour-restart', $home);
    }

    public function test_wrong_account_type_cannot_use_foreign_tour_routes(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->customer()->create([
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);
        $agency->users()->attach($customer->id, ['role' => 'customer']);

        $this->actingAs($customer)
            ->getJson(route('agent.dashboard-tours.show'))
            ->assertForbidden();

        $this->actingAs($customer)
            ->getJson('/api/dashboard/tours?portal=admin')
            ->assertForbidden();
    }
}
