<?php

namespace Tests\Feature\Commerce;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CommerceCheckoutSetting;
use App\Models\GroupInventory;
use App\Models\User;
use App\Services\Commerce\CommerceCheckoutSettingsService;
use App\Services\GroupTicketing\GroupBookingEligibilityService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JP-CLIENT-UI-BOOKING-CONTROL-01 (R7) booking authority matrix.
 * No supplier mutation.
 */
class BookingControlMatrixTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
    }

    public function test_guest_booking_defaults_true(): void
    {
        $gates = app(CommerceCheckoutSettingsService::class)->gates();
        $this->assertTrue($gates['guest_booking_enabled']);
        $this->assertTrue($gates['customer_group_booking_enabled']);
    }

    public function test_admin_can_toggle_guest_and_customer_group_with_audit(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => $this->agency->id,
        ]);

        $this->actingAs($admin)
            ->patchJson('/admin/settings/booking-checkout', [
                'guest_booking_enabled' => false,
                'customer_group_booking_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('settings.guest_booking_enabled', false)
            ->assertJsonPath('settings.customer_group_booking_enabled', false);

        $this->assertTrue(
            AuditLog::query()->where('action', 'commerce_checkout.settings_updated')->exists()
        );

        $this->assertDatabaseHas('commerce_checkout_settings', [
            'agency_id' => $this->agency->id,
            'guest_booking_enabled' => 0,
            'customer_group_booking_enabled' => 0,
        ]);
    }

    public function test_unauthorized_toggle_denied(): void
    {
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $this->agency->id,
        ]);

        $this->actingAs($customer)
            ->patchJson('/admin/settings/booking-checkout', [
                'guest_booking_enabled' => false,
            ])
            ->assertForbidden();
    }

    public function test_guest_off_anonymous_passengers_auth_required(): void
    {
        CommerceCheckoutSetting::query()->updateOrCreate(
            ['agency_id' => null],
            ['guest_booking_enabled' => false, 'card_payment_enabled' => true, 'customer_group_booking_enabled' => true],
        );

        $this->getJson('/booking/passengers?format=json')
            ->assertUnauthorized()
            ->assertJsonPath('status', 'guest_booking_disabled')
            ->assertJsonPath('code', 'AUTH_REQUIRED')
            ->assertJsonPath('continue_as_guest', false);
    }

    public function test_guest_off_anonymous_review_auth_required(): void
    {
        CommerceCheckoutSetting::query()->updateOrCreate(
            ['agency_id' => null],
            ['guest_booking_enabled' => false, 'card_payment_enabled' => true, 'customer_group_booking_enabled' => true],
        );

        $this->getJson('/booking/review?format=json')
            ->assertUnauthorized()
            ->assertJsonPath('status', 'guest_booking_disabled');
    }

    public function test_group_anonymous_always_denied(): void
    {
        $eligibility = app(GroupBookingEligibilityService::class)->evaluate(null);
        $this->assertFalse($eligibility['eligible']);
        $this->assertSame('auth_required', $eligibility['reason']);
    }

    public function test_group_customer_allowed_when_enabled(): void
    {
        CommerceCheckoutSetting::query()->updateOrCreate(
            ['agency_id' => null],
            ['guest_booking_enabled' => true, 'card_payment_enabled' => true, 'customer_group_booking_enabled' => true],
        );

        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $this->agency->id,
        ]);

        $eligibility = app(GroupBookingEligibilityService::class)->evaluate($customer);
        $this->assertTrue($eligibility['eligible']);
        $this->assertSame('customer', $eligibility['reason']);
    }

    public function test_group_customer_denied_when_disabled_agent_still_allowed(): void
    {
        CommerceCheckoutSetting::query()->updateOrCreate(
            ['agency_id' => null],
            ['guest_booking_enabled' => true, 'card_payment_enabled' => true, 'customer_group_booking_enabled' => false],
        );

        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $this->agency->id,
        ]);
        $agent = User::factory()->create([
            'account_type' => AccountType::Agent,
            'current_agency_id' => $this->agency->id,
        ]);

        $service = app(GroupBookingEligibilityService::class);
        $customerResult = $service->evaluate($customer);
        $agentResult = $service->evaluate($agent);

        $this->assertFalse($customerResult['eligible']);
        $this->assertSame('customer_group_booking_disabled', $customerResult['reason']);
        $this->assertTrue($agentResult['eligible']);
        $this->assertSame('agent', $agentResult['reason']);
    }

    public function test_group_passengers_endpoint_blocks_customer_when_disabled(): void
    {
        CommerceCheckoutSetting::query()->updateOrCreate(
            ['agency_id' => null],
            ['guest_booking_enabled' => true, 'card_payment_enabled' => true, 'customer_group_booking_enabled' => false],
        );

        $inventory = GroupInventory::query()->create([
            'public_id' => 'TEST-GRP-R7',
            'supplier_package_id' => 'ext-r7-1',
            'supplier' => GroupInventory::SUPPLIER_MANUAL_LOCAL,
            'title' => 'R7 Test Group',
            'sector' => 'ISB-DXB',
            'departure_date' => now()->addDays(30)->toDateString(),
            'total_seats' => 10,
            'held_seats' => 0,
            'sold_seats' => 0,
            'price' => 50000,
            'currency' => 'PKR',
            'is_active' => true,
            'synced_at' => now(),
        ]);

        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $this->agency->id,
        ]);

        $this->actingAs($customer)
            ->getJson('/groups/'.$inventory->public_id.'/passengers?format=json')
            ->assertForbidden()
            ->assertJsonPath('status', 'customer_group_booking_disabled');
    }

    public function test_commerce_gates_expose_registration_and_group_flags(): void
    {
        $this->getJson('/booking/commerce-gates')
            ->assertOk()
            ->assertJsonStructure([
                'guest_booking_enabled',
                'card_payment_enabled',
                'customer_group_booking_enabled',
                'customer_registration_enabled',
            ]);
    }
}
