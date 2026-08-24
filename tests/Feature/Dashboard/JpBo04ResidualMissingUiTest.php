<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\User;
use App\Support\Dashboard\BookingOperationalCapabilitiesPresenter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JpBo04ResidualMissingUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['suppliers.sabre.void_live_call_enabled' => false]);
    }

    public function test_booking_status_update_json_roundtrip(): void
    {
        $admin = $this->platformAdmin();
        $booking = Booking::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'status' => BookingStatus::Pending,
        ]);

        $response = $this->actingAs($admin)->patchJson(
            route('admin.bookings.status', ['booking' => $booking, 'format' => 'json']),
            [
                'status' => BookingStatus::Confirmed->value,
                'note' => 'jp-bo-04 residual status',
            ],
        );

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('booking.status', BookingStatus::Confirmed->value);

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_prepare_supplier_pnr_context_json_returns_structured_block_when_ineligible(): void
    {
        $admin = $this->platformAdmin();
        $booking = Booking::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'status' => BookingStatus::Pending,
            'pnr' => 'ALREADY1',
            'supplier_booking_status' => 'created',
        ]);

        $response = $this->actingAs($admin)->postJson(
            route('admin.bookings.prepare-supplier-pnr-context', ['booking' => $booking, 'format' => 'json']),
        );

        $response->assertStatus(409)
            ->assertJsonPath('ok', false)
            ->assertJsonStructure(['message', 'code']);
    }

    public function test_sync_pnr_itinerary_json_returns_structured_block_when_ineligible(): void
    {
        $admin = $this->platformAdmin();
        $booking = Booking::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'status' => BookingStatus::Pending,
            'pnr' => null,
        ]);

        $response = $this->actingAs($admin)->postJson(
            route('admin.bookings.sync-pnr-itinerary', ['booking' => $booking, 'format' => 'json']),
        );

        $response->assertStatus(409)
            ->assertJsonPath('ok', false)
            ->assertJsonStructure(['message', 'code']);
    }

    public function test_void_capability_exposes_deferred_support_without_enabling_live_gate(): void
    {
        $admin = $this->platformAdmin();
        $booking = Booking::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'status' => BookingStatus::Ticketed,
            'pnr' => 'VOIDCHK',
        ]);

        $caps = (new BookingOperationalCapabilitiesPresenter)->present($admin, $booking);

        $this->assertFalse($caps['can_void_ticket']);
        $this->assertSame('DEFERRED_PROVIDER_CAPABILITY', $caps['sabre_void_support']);
        $this->assertNotNull($caps['reasons']['can_void_ticket'] ?? null);
        // Without ticket rows the reason is eligibility (no_tickets); with tickets it cites adapter deferral.
        if (($caps['reasons']['can_void_ticket'] ?? '') !== 'no_tickets') {
            $this->assertStringContainsString(
                'Void is not supported by the current Sabre servicing adapter',
                (string) $caps['reasons']['can_void_ticket'],
            );
        }
        $this->assertArrayHasKey('can_update_status', $caps);
        $this->assertArrayHasKey('can_prepare_pnr_context', $caps);
        $this->assertArrayHasKey('can_export_audit', $caps);
        $this->assertTrue($caps['can_export_audit']);
    }

    public function test_booking_audit_and_finance_export_routes_respond(): void
    {
        $admin = $this->platformAdmin();
        $booking = Booking::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'status' => BookingStatus::Confirmed,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.bookings.audit.export', $booking))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($admin)
            ->get(route('admin.accounting.reconciliation.export'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.finance.dashboard.export'))
            ->assertOk();
    }

    public function test_deployment_checklist_redirects_to_go_live(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.deployment-checklist'))
            ->assertRedirect('/admin/dashboard/system/go-live');
    }

    public function test_guest_customer_show_redirects_into_customers(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.customers.guests.show', ['email' => 'guest@example.com']))
            ->assertRedirect('/admin/dashboard/customers?email=guest%40example.com');
    }

    protected function platformAdmin(): User
    {
        $agency = Agency::factory()->create();

        /** @var User $admin */
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'status' => UserAccountStatus::Active,
            'current_agency_id' => $agency->id,
        ]);

        return $admin;
    }
}
