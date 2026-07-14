<?php

namespace Tests\Feature\Finance;

use App\Enums\AgentCommissionEntryStatus;
use App\Enums\AgentCommissionEntryType;
use App\Enums\BookingPaymentMethod;
use App\Enums\BookingPaymentStatus;
use App\Enums\BookingRefundStatus;
use App\Enums\BookingStatus;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentCommissionEntry;
use App\Models\AgentDepositRequest;
use App\Models\AgentWallet;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPayment;
use App\Models\BookingRefund;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\Agents\AgentCommissionService;
use App\Services\Agents\AgentWalletService;
use App\Services\Booking\BookingService;
use App\Services\Finance\Ledger\LedgerEventRecorder;
use App\Services\Finance\Ledger\LedgerPostingService;
use App\Services\Payments\BookingPaymentService;
use App\Services\Payments\BookingRefundService;
use App\Support\Identity\ActorIdentifier;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use RuntimeException;
use Tests\Feature\Finance\Concerns\BuildsOtaFinanceScenario;
use Tests\TestCase;

class LedgerLivePostingTest extends TestCase
{
    use BuildsOtaFinanceScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->seedLedgerInfrastructure();
    }

    public function test_approving_deposit_creates_one_posted_ledger_transaction(): void
    {
        [$deposit, $admin] = $this->submittedDeposit();

        app(AgentWalletService::class)->approveDeposit($deposit, $admin);

        $this->assertSame(1, LedgerTransaction::query()->where('transaction_type', LedgerTransactionType::AgencyDepositApproved)->count());
        $tx = LedgerTransaction::query()->where('source_id', $deposit->id)->firstOrFail();
        $this->assertSame(LedgerTransactionStatus::Posted, $tx->status);
        $this->assertSame((float) $deposit->amount, (float) $tx->amount_total);
    }

    public function test_approving_same_deposit_twice_does_not_duplicate_ledger_transaction(): void
    {
        [$deposit, $admin] = $this->submittedDeposit();
        $recorder = app(LedgerEventRecorder::class);
        $walletService = app(AgentWalletService::class);

        $walletService->approveDeposit($deposit, $admin);
        $recorder->recordAgencyDepositApproved($deposit->fresh(), $admin);

        $this->assertSame(1, LedgerTransaction::query()
            ->where('source_type', $deposit->getMorphClass())
            ->where('source_id', $deposit->id)
            ->where('transaction_type', LedgerTransactionType::AgencyDepositApproved)
            ->count());
    }

    public function test_deposit_ledger_transaction_is_balanced(): void
    {
        [$deposit, $admin] = $this->submittedDeposit();
        app(AgentWalletService::class)->approveDeposit($deposit, $admin);

        $tx = LedgerTransaction::query()->where('source_id', $deposit->id)->firstOrFail();
        $debit = round((float) $tx->entries->sum('debit'), 2);
        $credit = round((float) $tx->entries->sum('credit'), 2);

        $this->assertSame($debit, $credit);
        $this->assertSame((float) $deposit->amount, $debit);
    }

    public function test_payment_verification_creates_customer_booking_liability_transaction(): void
    {
        [$payment, $admin] = $this->submittedPayment(BookingPaymentMethod::BankTransfer);

        app(BookingPaymentService::class)->verifyPayment($payment, $admin);

        $tx = LedgerTransaction::query()
            ->where('transaction_type', LedgerTransactionType::BookingPaymentVerified)
            ->where('source_id', $payment->id)
            ->firstOrFail();

        $this->assertSame(LedgerTransactionStatus::Posted, $tx->status);
        $credit = $tx->entries->first(fn ($e) => (float) $e->credit > 0);
        $this->assertNotNull($credit);
        $this->assertSame('CUSTOMER_BOOKING_LIABILITY', $credit->account->code);
    }

    public function test_manual_verified_payment_creates_ledger_transaction(): void
    {
        [$booking, $admin] = $this->bookingWithFare();
        $payment = app(BookingPaymentService::class)->recordManualPayment($booking, $admin, [
            'amount' => 5000,
            'method' => BookingPaymentMethod::Cash,
            'verify_now' => true,
        ]);

        $this->assertDatabaseHas('ledger_transactions', [
            'source_id' => $payment->id,
            'transaction_type' => LedgerTransactionType::BookingPaymentVerified->value,
            'status' => LedgerTransactionStatus::Posted->value,
        ]);
    }

    public function test_refund_approval_creates_refund_liability_transaction(): void
    {
        [$refund, $admin] = $this->pendingRefund();

        app(BookingRefundService::class)->approveRefund($refund, $admin);

        $tx = LedgerTransaction::query()
            ->where('transaction_type', LedgerTransactionType::BookingRefundApproved)
            ->where('source_id', $refund->id)
            ->firstOrFail();

        $this->assertSame(LedgerTransactionStatus::Posted, $tx->status);
        $credit = $tx->entries->first(fn ($e) => (float) $e->credit > 0);
        $this->assertSame('REFUND_LIABILITY', $credit?->account->code);
    }

    public function test_refund_paid_creates_refund_paid_transaction(): void
    {
        [$refund, $admin] = $this->approvedRefund();

        app(BookingRefundService::class)->markRefundPaid($refund, $admin, []);

        $this->assertDatabaseHas('ledger_transactions', [
            'source_id' => $refund->id,
            'transaction_type' => LedgerTransactionType::BookingRefundPaid->value,
            'status' => LedgerTransactionStatus::Posted->value,
        ]);
        $this->assertSame(2, LedgerTransaction::query()->where('source_id', $refund->id)->count());
    }

    public function test_commission_earned_creates_expense_payable_transaction(): void
    {
        [$entry, $admin] = $this->pendingCommissionEntry();

        app(AgentCommissionService::class)->approveEntry($entry, $admin);

        $tx = LedgerTransaction::query()
            ->where('transaction_type', LedgerTransactionType::AgencyCommissionEarned)
            ->where('source_id', $entry->id)
            ->firstOrFail();

        $debit = $tx->entries->first(fn ($e) => (float) $e->debit > 0);
        $credit = $tx->entries->first(fn ($e) => (float) $e->credit > 0);
        $this->assertSame('AGENCY_COMMISSION_EXPENSE', $debit?->account->code);
        $this->assertSame('AGENCY_COMMISSION_PAYABLE', $credit?->account->code);
    }

    public function test_pending_commission_does_not_create_ledger_entry(): void
    {
        [, $admin] = $this->pendingCommissionEntry();
        $entry = AgentCommissionEntry::query()->where('status', AgentCommissionEntryStatus::Pending)->firstOrFail();

        $this->assertNull(app(LedgerEventRecorder::class)->recordAgencyCommissionEarned($entry, $admin));
        $this->assertSame(0, LedgerTransaction::query()->where('transaction_type', LedgerTransactionType::AgencyCommissionEarned)->count());
    }

    public function test_markup_recognition_creates_revenue_transaction(): void
    {
        [$booking, $admin] = $this->bookingWithFare(markup: 1500);

        app(BookingService::class)->changeStatus($booking, BookingStatus::Confirmed, $admin, 'Confirmed for test');

        $fare = $booking->fresh()->fareBreakdown;
        $tx = LedgerTransaction::query()
            ->where('transaction_type', LedgerTransactionType::MarkupRevenueRecognized)
            ->where('source_id', $fare->id)
            ->firstOrFail();

        $credit = $tx->entries->first(fn ($e) => (float) $e->credit > 0);
        $this->assertSame('PLATFORM_MARKUP_REVENUE', $credit?->account->code);
        $this->assertSame(1500.0, (float) $tx->amount_total);
    }

    public function test_confirmed_and_ticketed_markup_hooks_do_not_double_post(): void
    {
        [$booking, $admin] = $this->bookingWithFare(markup: 800, status: BookingStatus::Confirmed);
        $recorder = app(LedgerEventRecorder::class);

        $recorder->recordMarkupRevenueForBooking($booking->fresh(), $admin);
        $recorder->recordMarkupRevenueForBooking($booking->fresh(), $admin);

        $this->assertSame(1, LedgerTransaction::query()
            ->where('transaction_type', LedgerTransactionType::MarkupRevenueRecognized)
            ->where('booking_id', $booking->id)
            ->count());
    }

    public function test_guest_booking_payment_stores_guest_key_and_actor_identifier(): void
    {
        [$booking, $admin] = $this->guestBookingWithPayment();
        $payment = $booking->payments()->first();

        app(BookingPaymentService::class)->verifyPayment($payment, $admin);

        $tx = LedgerTransaction::query()->where('source_id', $payment->id)->firstOrFail();
        $this->assertSame('guest:4242', $tx->guest_key);
        $this->assertSame(ActorIdentifier::forUser($admin), $tx->actor_identifier);
    }

    public function test_agency_id_is_set_on_agency_liability_entries(): void
    {
        [$deposit, $admin] = $this->submittedDeposit();
        app(AgentWalletService::class)->approveDeposit($deposit, $admin);

        $tx = LedgerTransaction::query()->where('source_id', $deposit->id)->firstOrFail();
        $liabilityEntry = $tx->entries->first(fn ($e) => $e->account->code === 'AGENCY_WALLET_LIABILITY');

        $this->assertNotNull($liabilityEntry);
        $this->assertSame($deposit->agency_id, $liabilityEntry->agency_id);
    }

    public function test_ledger_failure_does_not_break_deposit_approval(): void
    {
        $mock = Mockery::mock(LedgerPostingService::class)->makePartial();
        $mock->shouldReceive('postFromRule')->andThrow(new RuntimeException('Simulated ledger failure'));
        $this->app->instance(LedgerPostingService::class, $mock);

        [$deposit, $admin] = $this->submittedDeposit();
        $wallet = AgentWallet::query()->findOrFail($deposit->agent_wallet_id);

        app(AgentWalletService::class)->approveDeposit($deposit, $admin);

        $this->assertSame('approved', $deposit->fresh()->status->value);
        $this->assertSame((float) $deposit->amount, (float) $wallet->fresh()->balance);
        $this->assertSame(0, LedgerTransaction::query()->count());
    }

    public function test_finance_reports_rbac_still_passes(): void
    {
        $scenario = $this->buildOtaFinanceScenario();
        $platform = $scenario['platform'];
        $et = $scenario['agencies']['et'];

        $this->actingAs($platform['admin'])->get(route('admin.ledger.index'))->assertOk();
        $this->actingAs($platform['staffFinance'])->get(route('staff.ledger.index'))->assertOk();
        $this->actingAs($et['owner'])->get(route('agent.ledger.index'))->assertOk();
        $this->actingAs($platform['staffOps'])->get(route('staff.ledger.index'))->assertForbidden();
    }

    public function test_ledger_posting_status_command_works(): void
    {
        [$deposit, $admin] = $this->submittedDeposit();
        app(AgentWalletService::class)->approveDeposit($deposit, $admin);

        $exitCode = Artisan::call('ledger:posting-status', ['--recent' => 5]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('agency_deposit_approved', $output);
        $this->assertStringContainsString('yes', $output);

        Artisan::call('ledger:posting-status', [
            '--agency' => $deposit->agency_id,
            '--type' => 'agency_deposit_approved',
        ]);
        $this->assertStringContainsString('agency_deposit_approved', Artisan::output());
    }

    /**
     * @return array{0: AgentDepositRequest, 1: User}
     */
    protected function submittedDeposit(): array
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $agent = Agent::query()->where('agency_id', $agency->id)->firstOrFail();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $wallet = app(AgentWalletService::class)->walletFor($agent);

        $deposit = AgentDepositRequest::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'agent_wallet_id' => $wallet->id,
            'amount' => 3200,
            'currency' => 'PKR',
            'status' => 'submitted',
            'reference' => 'DEP-LIVE-001',
        ]);

        return [$deposit, $admin];
    }

    /**
     * @return array{0: BookingPayment, 1: User}
     */
    protected function submittedPayment(BookingPaymentMethod $method): array
    {
        [$booking, $admin] = $this->bookingWithFare();
        $payment = BookingPayment::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'payer_user_id' => $booking->customer_id,
            'method' => $method,
            'status' => BookingPaymentStatus::Submitted,
            'amount' => 5000,
            'currency' => 'PKR',
            'submitted_at' => now(),
        ]);

        return [$payment, $admin];
    }

    /**
     * @return array{0: Booking, 1: User}
     */
    protected function bookingWithFare(float $markup = 500, BookingStatus $status = BookingStatus::Pending): array
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'status' => $status,
            'payment_status' => 'unpaid',
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 8000,
            'taxes' => 1000,
            'fees' => 500,
            'markup' => $markup,
            'discount' => 0,
            'total' => 9500 + $markup,
            'currency' => 'PKR',
        ]);

        return [$booking, $admin];
    }

    /**
     * @return array{0: Booking, 1: User}
     */
    protected function guestBookingWithPayment(): array
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'customer_id' => null,
            'status' => BookingStatus::PaymentPending,
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 7000,
            'taxes' => 500,
            'fees' => 0,
            'markup' => 500,
            'total' => 8000,
            'currency' => 'PKR',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'guest@example.com',
            'meta' => ['guest_id' => 4242, 'first_name' => 'Guest'],
        ]);
        BookingPayment::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'method' => BookingPaymentMethod::Easypaisa,
            'status' => BookingPaymentStatus::Submitted,
            'amount' => 8000,
            'currency' => 'PKR',
            'submitted_at' => now(),
        ]);

        return [$booking, $admin];
    }

    /**
     * @return array{0: BookingRefund, 1: User}
     */
    protected function pendingRefund(): array
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'status' => BookingStatus::Cancelled,
            'payment_status' => 'paid',
        ]);
        BookingPayment::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'method' => BookingPaymentMethod::BankTransfer,
            'status' => BookingPaymentStatus::Verified,
            'amount' => 6000,
            'currency' => 'PKR',
            'verified_at' => now(),
        ]);
        $refund = BookingRefund::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'amount' => 3000,
            'currency' => 'PKR',
            'method' => BookingPaymentMethod::BankTransfer,
            'status' => BookingRefundStatus::Pending,
        ]);

        return [$refund, $admin];
    }

    /**
     * @return array{0: BookingRefund, 1: User}
     */
    protected function approvedRefund(): array
    {
        [$refund, $admin] = $this->pendingRefund();
        app(BookingRefundService::class)->approveRefund($refund, $admin);

        return [$refund->fresh(), $admin];
    }

    /**
     * @return array{0: AgentCommissionEntry, 1: User}
     */
    protected function pendingCommissionEntry(): array
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $agent = Agent::query()->where('agency_id', $agency->id)->firstOrFail();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $entry = AgentCommissionEntry::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'type' => AgentCommissionEntryType::Earned,
            'status' => AgentCommissionEntryStatus::Pending,
            'calculation_basis' => 'percentage',
            'rate' => 5,
            'base_amount' => 10000,
            'commission_amount' => 500,
            'currency' => 'PKR',
        ]);

        return [$entry, $admin];
    }
}
