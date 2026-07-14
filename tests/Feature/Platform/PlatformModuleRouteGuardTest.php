<?php

namespace Tests\Feature\Platform;

use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\DeveloperUser;
use App\Models\PlatformModuleSetting;
use App\Models\User;
use App\Services\Platform\PlatformModuleSettingsService;
use App\Support\Platform\PlatformModuleGate;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class PlatformModuleRouteGuardTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OtaFoundationSeeder::class);
        Config::set('ota-developer.enabled', true);
    }

    public function test_disabled_customer_booking_lookup_blocks_lookup_get_with_friendly_page(): void
    {
        $this->planModuleOff('customer_booking_lookup');

        $this->get(route('booking.lookup'))
            ->assertForbidden()
            ->assertSee('This module is disabled for this deployment.', false);
    }

    public function test_disabled_customer_booking_lookup_blocks_lookup_post_with_json_403(): void
    {
        $this->planModuleOff('customer_booking_lookup');

        $this->postJson(route('lookup-booking.submit'), [
            'booking_reference' => 'TEST123',
            'email' => 'guest@example.com',
        ])
            ->assertForbidden()
            ->assertJson([
                'message' => 'This module is disabled for this deployment.',
            ]);
    }

    public function test_disabled_support_system_blocks_public_support_route(): void
    {
        $this->planModuleOff('support_system');

        $this->get(route('support'))
            ->assertForbidden()
            ->assertSee('This module is disabled for this deployment.', false);
    }

    public function test_disabled_agent_staff_blocks_agent_staff_route(): void
    {
        $this->planModuleOff('agent_staff');

        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($agent)
            ->get(route('agent.staff.index'))
            ->assertForbidden()
            ->assertSee('This module is disabled for this deployment.', false)
            ->assertSee('Agent staff', false);
    }

    public function test_disabled_saved_travelers_blocks_customer_and_agent_traveler_routes(): void
    {
        $this->planModuleOff('saved_travelers');

        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $this->actingAs($customer)
            ->get(route('customer.travelers.index'))
            ->assertForbidden();

        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $this->actingAs($agent)
            ->get(route('agent.travelers.index'))
            ->assertForbidden();
    }

    public function test_disabled_api_settings_blocks_admin_api_settings_page(): void
    {
        $this->planModuleOff('api_settings');

        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.api-settings'))
            ->assertForbidden()
            ->assertSee('API settings', false);
    }

    public function test_disabled_branding_settings_blocks_branding_page(): void
    {
        $this->planModuleOff('branding_settings');

        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.settings.branding.edit'))
            ->assertForbidden();
    }

    public function test_disabled_markup_settings_blocks_markup_page(): void
    {
        $this->planModuleOff('markup_settings');

        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.markups'))
            ->assertForbidden();
    }

    public function test_disabled_finance_reports_blocks_finance_dashboard(): void
    {
        $this->planModuleOff('finance_reports');

        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.finance.dashboard'))
            ->assertForbidden();
    }

    public function test_disabled_agent_deposits_blocks_admin_deposit_index(): void
    {
        $this->planModuleOff('agent_deposits');

        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.agent-deposits.index'))
            ->assertForbidden();
    }

    public function test_disabled_agent_wallet_blocks_agent_wallet_route(): void
    {
        $this->planModuleOff('agent_wallet');

        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($agent)
            ->get(route('agent.wallet.show'))
            ->assertForbidden()
            ->assertSee('This module is disabled for this deployment.', false);
    }

    public function test_disabled_agent_deposits_blocks_agent_deposits_index(): void
    {
        $this->planModuleOff('agent_deposits');

        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($agent)
            ->get(route('agent.deposits.index'))
            ->assertForbidden()
            ->assertSee('This module is disabled for this deployment.', false);
    }

    public function test_disabled_agent_ledger_blocks_agent_ledger_routes(): void
    {
        $this->planModuleOff('agent_ledger');

        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($agent)
            ->get(route('agent.ledger.index'))
            ->assertForbidden()
            ->assertSee('This module is disabled for this deployment.', false);

        $this->actingAs($agent)
            ->get(route('agent.accounting.ledger.index'))
            ->assertForbidden();
    }

    public function test_agent_bookings_remain_accessible_when_finance_modules_off(): void
    {
        foreach (['agent_wallet', 'agent_deposits', 'agent_ledger'] as $key) {
            $this->planModuleOff($key);
        }

        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agent->current_agency_id,
            'agent_id' => $agent->agent()?->id,
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
            'source_channel' => 'agent_portal',
        ]);

        $this->actingAs($agent)
            ->get(route('agent.bookings.index'))
            ->assertOk();

        $this->actingAs($agent)
            ->get(route('agent.bookings.show', $booking))
            ->assertOk();
    }

    public function test_disabled_payment_proofs_blocks_guest_payment_proof_post_at_route(): void
    {
        $this->planModuleOff('payment_proofs');

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::PaymentPending,
            'payment_status' => 'unpaid',
        ]);

        $this->postJson(route('guest.bookings.payment-proof', [$booking, 'test-token']), [
            'method' => 'bank_transfer',
            'amount' => 1000,
        ])
            ->assertForbidden()
            ->assertJson([
                'message' => 'This module is disabled for this deployment.',
            ]);
    }

    public function test_disabled_payment_proofs_blocks_customer_payment_proof_post_at_route(): void
    {
        $this->planModuleOff('payment_proofs');

        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $customer->current_agency_id,
            'customer_id' => $customer->id,
            'status' => BookingStatus::PaymentPending,
            'payment_status' => 'unpaid',
        ]);

        $this->actingAs($customer)->postJson(route('customer.bookings.payment-proof', $booking), [
            'method' => 'bank_transfer',
            'amount' => 1000,
        ])
            ->assertForbidden()
            ->assertJson([
                'message' => 'This module is disabled for this deployment.',
            ]);
    }

    public function test_disabled_payment_proofs_blocks_agent_payment_proof_post_at_route(): void
    {
        $this->planModuleOff('payment_proofs');

        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agent->current_agency_id,
            'agent_id' => $agent->agent()?->id,
            'status' => BookingStatus::PaymentPending,
            'payment_status' => 'unpaid',
            'source_channel' => 'agent_portal',
        ]);

        $this->actingAs($agent)->postJson(route('agent.bookings.payment-proof', $booking), [
            'method' => 'bank_transfer',
            'amount' => 1000,
        ])
            ->assertForbidden()
            ->assertJson([
                'message' => 'This module is disabled for this deployment.',
            ]);
    }

    public function test_enabled_module_allows_guarded_route(): void
    {
        $this->get(route('booking.lookup'))->assertOk();
    }

    public function test_protected_admin_portal_route_passes_when_db_plans_module_off(): void
    {
        $this->planModuleOff('admin_portal');

        $admin = $this->platformAdmin();

        $this->assertTrue(PlatformModuleGate::routeEnabled('admin_portal'));

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_unknown_middleware_module_key_returns_404(): void
    {
        Route::middleware('platform.module:definitely_not_a_module_key')
            ->get('/_test/platform-module-unknown-key', fn () => response('ok'));

        $this->get('/_test/platform-module-unknown-key')->assertNotFound();
    }

    public function test_dev_cp_accessible_with_developer_session_when_product_modules_disabled(): void
    {
        foreach (['public_flight_search', 'agent_portal', 'customer_portal', 'support_system', 'api_settings'] as $key) {
            $this->planModuleOff($key);
        }

        $developer = DeveloperUser::query()->create([
            'name' => 'Dev Owner',
            'email' => 'dev-guard@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->get(route('dev.cp.modules.index'))
            ->assertOk();
    }

    public function test_platform_admin_cannot_access_dev_cp_without_developer_session(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('dev.cp.index'))
            ->assertRedirect(route('dev.cp.login'));
    }

    public function test_admin_platform_modules_route_remains_404(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get('/admin/platform/modules')
            ->assertNotFound();
    }

    public function test_high_risk_routes_remain_unguarded_when_unrelated_modules_disabled(): void
    {
        $this->planModuleOff('support_system');
        $this->planModuleOff('api_settings');

        $this->get(route('flights.results', [
            'from' => 'LHE',
            'to' => 'DXB',
            'depart' => now()->addDays(30)->format('Y-m-d'),
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ]))->assertOk();

        $this->get(route('booking.review'))->assertRedirect();

        $admin = $this->platformAdmin();
        $this->actingAs($admin)
            ->get(route('admin.bookings'))
            ->assertOk();
    }

    public function test_allows_stays_true_and_route_enabled_false_for_planned_off_module(): void
    {
        $this->planModuleOff('agent_portal');

        $this->assertTrue(PlatformModuleGate::allows('agent_portal'));
        $this->assertFalse(PlatformModuleGate::routeEnabled('agent_portal'));
        $this->assertFalse(PlatformModuleGate::visible('agent_portal'));
    }

    public function test_route_enabled_unknown_key_is_false(): void
    {
        $this->assertFalse(PlatformModuleGate::routeEnabled('not_in_registry'));
    }

    private function planModuleOff(string $key): void
    {
        PlatformModuleSetting::query()->create([
            'module_key' => $key,
            'enabled' => false,
        ]);
        app(PlatformModuleSettingsService::class)->forgetCache();
    }
}
