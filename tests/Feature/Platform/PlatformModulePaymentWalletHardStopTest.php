<?php

namespace Tests\Feature\Platform;

use App\Enums\AccountType;
use App\Enums\AgentDepositRequestStatus;
use App\Enums\BookingStatus;
use App\Exceptions\PlatformModuleDisabledException;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentDepositRequest;
use App\Models\AgentWallet;
use App\Models\AgentWalletTransaction;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\DeveloperUser;
use App\Models\PlatformModuleSetting;
use App\Models\User;
use App\Services\Agents\AgentWalletService;
use App\Services\Customer\GuestBookingAccessService;
use App\Services\Platform\PlatformModuleSettingsService;
use Database\Seeders\LedgerChartOfAccountsSeeder;
use Database\Seeders\LedgerPostingRulesSeeder;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class PlatformModulePaymentWalletHardStopTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OtaFoundationSeeder::class);
        (new LedgerChartOfAccountsSeeder)->run();
        (new LedgerPostingRulesSeeder)->run();
        Config::set('ota-developer.enabled', true);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_payment_proofs_off_blocks_guest_payment_proof_post(): void
    {
        $this->planModuleOff('payment_proofs');
        [, $booking] = $this->customerBooking();
        $token = app(GuestBookingAccessService::class)->createTokenForBooking($booking, 'guest@example.test', null);
        $countBefore = BookingPayment::query()->count();

        $this->post(route('guest.bookings.payment-proof', [$booking, $token]), [
            'method' => 'bank_transfer',
            'amount' => 1000,
        ])
            ->assertForbidden()
            ->assertJson([
                'message' => 'This module is disabled for this deployment.',
            ]);

        $this->assertSame($countBefore, BookingPayment::query()->count());
    }

    public function test_payment_proofs_off_blocks_customer_payment_proof_post(): void
    {
        $this->planModuleOff('payment_proofs');
        [$customer, $booking] = $this->customerBooking();
        $countBefore = BookingPayment::query()->count();

        $this->actingAs($customer)->post(route('customer.bookings.payment-proof', $booking), [
            'method' => 'bank_transfer',
            'amount' => 1000,
            'payment_reference' => 'BLOCKED',
        ])
            ->assertForbidden()
            ->assertJson([
                'message' => 'This module is disabled for this deployment.',
            ]);

        $this->assertSame($countBefore, BookingPayment::query()->count());
        $this->assertDatabaseMissing('booking_payments', [
            'booking_id' => $booking->id,
            'payment_reference' => 'BLOCKED',
        ]);
    }

    public function test_payment_proofs_off_blocks_agent_payment_proof_post(): void
    {
        $this->planModuleOff('payment_proofs');
        $agentUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $agent = $agentUser->agent();
        $booking = $this->agentBooking($agentUser, $agent);
        $countBefore = BookingPayment::query()->count();

        $this->actingAs($agentUser)->post(route('agent.bookings.payment-proof', $booking), [
            'method' => 'bank_transfer',
            'amount' => 3000,
            'payment_reference' => 'AGT-BLOCK',
        ])
            ->assertForbidden()
            ->assertJson([
                'message' => 'This module is disabled for this deployment.',
            ]);

        $this->assertSame($countBefore, BookingPayment::query()->count());
    }

    public function test_payment_proofs_on_allows_validation_errors_not_module_block(): void
    {
        [$customer, $booking] = $this->customerBooking();

        $this->actingAs($customer)->post(route('customer.bookings.payment-proof', $booking), [
            'amount' => 1000,
        ])
            ->assertSessionHasErrors('method')
            ->assertStatus(302);
    }

    public function test_agent_deposits_off_blocks_agent_deposit_store(): void
    {
        Storage::fake('local');
        $this->planModuleOff('agent_deposits');
        [$agentUser, $agent] = $this->seededAgent();
        $txCountBefore = AgentWalletTransaction::query()->count();
        $depositCountBefore = AgentDepositRequest::query()->count();

        $this->actingAs($agentUser)->post(route('agent.deposits.store'), [
            'amount' => 5000,
            'payment_method' => 'Bank transfer',
            'reference' => 'TXN-BLOCKED',
            'proof' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
        ])
            ->assertForbidden()
            ->assertJson([
                'message' => 'This module is disabled for this deployment.',
            ]);

        $this->assertSame($depositCountBefore, AgentDepositRequest::query()->count());
        $this->assertSame($txCountBefore, AgentWalletTransaction::query()->count());
        Storage::disk('local')->assertDirectoryEmpty('agent-deposits/proofs');
    }

    public function test_agent_deposits_off_blocks_admin_approve_mutation(): void
    {
        $this->planModuleOff('agent_deposits');
        [$agentUser, $agent] = $this->seededAgent();
        $admin = $this->platformAdmin();
        $wallet = app(AgentWalletService::class)->walletFor($agent);

        $deposit = AgentDepositRequest::query()->create([
            'agency_id' => $agent->agency_id,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'agent_wallet_id' => $wallet->id,
            'amount' => 1200,
            'currency' => 'PKR',
            'status' => AgentDepositRequestStatus::Submitted,
        ]);

        $txCountBefore = AgentWalletTransaction::query()->count();

        $this->actingAs($admin)->patch(route('admin.agent-deposits.approve', $deposit))
            ->assertForbidden();

        $deposit->refresh();
        $this->assertSame(AgentDepositRequestStatus::Submitted, $deposit->status);
        $this->assertSame(0.0, (float) $wallet->fresh()->balance);
        $this->assertSame($txCountBefore, AgentWalletTransaction::query()->count());
    }

    public function test_agent_deposits_off_blocks_admin_reject_mutation(): void
    {
        $this->planModuleOff('agent_deposits');
        [$agentUser, $agent] = $this->seededAgent();
        $admin = $this->platformAdmin();
        $wallet = app(AgentWalletService::class)->walletFor($agent);

        $deposit = AgentDepositRequest::query()->create([
            'agency_id' => $agent->agency_id,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'agent_wallet_id' => $wallet->id,
            'amount' => 900,
            'currency' => 'PKR',
            'status' => AgentDepositRequestStatus::Submitted,
        ]);

        $txCountBefore = AgentWalletTransaction::query()->count();

        $this->actingAs($admin)->patch(route('admin.agent-deposits.reject', $deposit), [
            'admin_note' => 'Should not apply',
        ])->assertForbidden();

        $deposit->refresh();
        $this->assertSame(AgentDepositRequestStatus::Submitted, $deposit->status);
        $this->assertSame($txCountBefore, AgentWalletTransaction::query()->count());
    }

    public function test_agent_wallet_off_blocks_manual_wallet_adjustment_store(): void
    {
        $this->planModuleOff('agent_wallet');
        [$agency, $wallet] = $this->seedAgencyWallet(50);
        $txCountBefore = AgentWalletTransaction::query()->count();

        $this->actingAs($this->platformAdmin())->post(route('admin.finance.adjustments.store'), [
            'agency_id' => $agency->id,
            'wallet_id' => $wallet->id,
            'adjustment_type' => 'manual_credit',
            'amount' => 25,
            'adjustment_reason' => 'bank_correction',
            'idempotency_key' => (string) Str::uuid(),
            'confirmation' => '1',
        ])
            ->assertForbidden()
            ->assertJson([
                'message' => PlatformModuleDisabledException::PUBLIC_MESSAGE,
            ]);

        $this->assertSame(50.0, (float) $wallet->fresh()->balance);
        $this->assertSame($txCountBefore, AgentWalletTransaction::query()->count());
    }

    public function test_agent_wallet_off_does_not_block_agent_booking_show(): void
    {
        $this->planModuleOff('agent_wallet');
        $agentUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $agent = $agentUser->agent();
        $booking = $this->agentBooking($agentUser, $agent);

        $this->actingAs($agentUser)->get(route('agent.bookings.show', $booking))->assertOk();
    }

    public function test_developer_cp_remains_accessible_when_payment_wallet_modules_off(): void
    {
        foreach (['payment_proofs', 'agent_deposits', 'agent_wallet'] as $key) {
            $this->planModuleOff($key);
        }

        $developer = DeveloperUser::query()->create([
            'name' => 'Dev 8M',
            'email' => 'dev-8m@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->get(route('dev.cp.modules.index'))
            ->assertOk();
    }

    public function test_admin_platform_modules_route_remains_404(): void
    {
        $this->actingAs($this->platformAdmin())
            ->get('/admin/platform/modules')
            ->assertNotFound();
    }

    public function test_high_risk_routes_remain_unguarded_when_payment_wallet_modules_off(): void
    {
        foreach (['payment_proofs', 'agent_deposits', 'agent_wallet', 'supplier_search', 'supplier_booking', 'ticketing'] as $key) {
            $this->planModuleOff($key);
        }

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
    }

    public function test_8m_did_not_wire_supplier_booking_or_ticketing_enforcer(): void
    {
        $flightSearch = (string) file_get_contents(base_path('app/Services/FlightSearch/FlightSearchService.php'));
        $payment = (string) file_get_contents(base_path('app/Services/Payments/BookingPaymentService.php'));

        $this->assertStringContainsString('ensurePaymentProofsEnabled', $payment);
        $this->assertStringNotContainsString('ensureSupplierBookingEnabled', $flightSearch);
        $this->assertStringNotContainsString('ensureTicketingEnabled', $flightSearch);
    }

    /**
     * @return array{0: User, 1: Booking}
     */
    protected function customerBooking(): array
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
        ]);
        $agency->users()->attach($customer->id, ['role' => 'customer']);
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'status' => BookingStatus::PaymentPending,
            'payment_status' => 'unpaid',
            'booking_reference' => 'BKG-'.strtoupper((string) fake()->unique()->numberBetween(1000, 9999)),
            'route' => 'LHE-KHI',
        ]);
        $booking->contact()->create([
            'email' => 'customer@example.test',
            'phone' => '03001234567',
            'country' => 'PK',
            'address_line' => 'Street 1',
        ]);

        return [$customer, $booking->fresh()];
    }

    protected function agentBooking(User $agentUser, ?Agent $agent): Booking
    {
        return Booking::factory()->create([
            'agency_id' => $agentUser->current_agency_id,
            'agent_id' => $agent?->id,
            'status' => BookingStatus::PaymentPending,
            'payment_status' => 'unpaid',
            'source_channel' => 'agent_portal',
            'route' => 'LHE-DXB',
        ]);
    }

    /**
     * @return array{0: User, 1: Agent}
     */
    protected function seededAgent(): array
    {
        $agentUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $agent = Agent::query()->where('user_id', $agentUser->id)->firstOrFail();

        return [$agentUser, $agent];
    }

    /**
     * @return array{0: Agency, 1: AgentWallet}
     */
    protected function seedAgencyWallet(float $balance): array
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $agent = Agent::query()->whereHas('user', fn ($q) => $q->where('email', 'agent@ota.demo'))->firstOrFail();
        $wallet = AgentWallet::query()->firstOrCreate(
            ['agent_id' => $agent->id],
            [
                'agency_id' => $agency->id,
                'user_id' => $agent->user_id,
                'balance' => $balance,
                'currency' => 'PKR',
                'status' => 'active',
            ],
        );
        $wallet->update(['balance' => $balance]);

        return [$agency, $wallet->fresh()];
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
