<?php

namespace App\Support\Finance;

use App\Enums\AccountType;
use App\Enums\AgentCommissionEntryStatus;
use App\Enums\AgentCommissionEntryType;
use App\Enums\AgentDepositRequestStatus;
use App\Enums\AgentWalletStatus;
use App\Enums\AgentWalletTransactionStatus;
use App\Enums\AgentWalletTransactionType;
use App\Enums\BookingPaymentMethod;
use App\Enums\BookingPaymentStatus;
use App\Enums\BookingRefundStatus;
use App\Enums\BookingStatus;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentCommissionEntry;
use App\Models\AgentDepositRequest;
use App\Models\AgentWallet;
use App\Models\AgentWalletTransaction;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPayment;
use App\Models\BookingRefund;
use App\Models\User;
use App\Support\Agencies\AgencyPrefixService;
use App\Support\Agents\AgentPermission;
use App\Support\Staff\StaffPermission;
use Illuminate\Support\Carbon;

/**
 * Realistic OTA finance demo dataset for local QA and Feature tests.
 *
 * Creates 3 agencies (ET, JP, DT), platform users, wallet history, bookings,
 * payments, refunds, and commission rows with manually assertable totals.
 */
final class OtaFinanceDemoScenario
{
    /** @var array<string, float> */
    public const PLATFORM_LEDGER = [
        'total_credits' => 132_000.0,
        'total_debits' => 30_750.0,
        'net_balance' => 101_250.0,
        'pending_deposits' => 18_000.0,
        'approved_deposits' => 80_000.0,
        'refund_liabilities' => 0.0,
        'agency_wallet_exposure' => 101_250.0,
    ];

    /** @var array<string, float> */
    public const AGENCY_ET_LEDGER = [
        'total_credits' => 67_000.0,
        'total_debits' => 17_000.0,
        'net_balance' => 50_000.0,
        'pending_deposits' => 8_000.0,
        'approved_deposits' => 30_000.0,
        'wallet_balance' => 50_000.0,
    ];

    /** @var array<string, float> */
    public const AGENCY_JP_LEDGER = [
        'total_credits' => 40_000.0,
        'total_debits' => 7_500.0,
        'net_balance' => 32_500.0,
        'pending_deposits' => 0.0,
        'approved_deposits' => 40_000.0,
        'wallet_balance' => 32_500.0,
    ];

    /** @var array<string, float> */
    public const AGENCY_DT_LEDGER = [
        'total_credits' => 25_000.0,
        'total_debits' => 6_250.0,
        'net_balance' => 18_750.0,
        'pending_deposits' => 10_000.0,
        'approved_deposits' => 10_000.0,
        'wallet_balance' => 18_750.0,
    ];

    /** @var array<string, float> */
    public const AGENCY_ET_REPORTS = [
        'gross_sales' => 107_500.0,
        'markup_revenue' => 9_500.0,
        'agent_sales' => 34_500.0,
        'direct_customer_sales' => 73_000.0,
    ];

    /** @var array<string, float> Projected AGENCY_WALLET_LIABILITY balances after backfill. */
    public const DOUBLE_ENTRY_WALLET_BALANCES = [
        'et' => 50_000.0,
        'jp' => 32_500.0,
        'dt' => 18_750.0,
        'platform_exposure' => 101_250.0,
    ];

    /**
     * @return array<string, mixed>
     */
    public function build(?Carbon $anchorDate = null): array
    {
        $anchor = $anchorDate ?? Carbon::parse('2026-05-15 10:00:00');

        $platformAdmin = $this->createUser([
            'name' => 'Platform Admin',
            'email' => 'platform-admin@finance-demo.test',
            'username' => 'platform-admin',
            'account_type' => AccountType::PlatformAdmin,
        ]);

        $staffFinance = $this->createUser([
            'name' => 'Finance Staff',
            'email' => 'staff-finance@finance-demo.test',
            'username' => 'staff-finance',
            'account_type' => AccountType::Staff,
            'meta' => [
                'staff_permissions' => [
                    StaffPermission::LedgerView,
                    StaffPermission::ReportsView,
                    StaffPermission::ReportsExport,
                    StaffPermission::BookingsView,
                ],
            ],
        ]);

        $staffOps = $this->createUser([
            'name' => 'Ops Staff',
            'email' => 'staff-ops@finance-demo.test',
            'username' => 'staff-ops',
            'account_type' => AccountType::Staff,
            'meta' => [
                'staff_permissions' => [
                    StaffPermission::BookingsView,
                    StaffPermission::PaymentsVerify,
                ],
            ],
        ]);

        $et = $this->buildAgency(
            name: 'Easy Ticket',
            slug: 'easy-ticket',
            prefix: 'ET',
            ownerName: 'Tariq Mehmood',
            ownerEmail: 'et-owner@finance-demo.test',
            staffFinanceName: 'Ayesha Noor',
            staffFinanceEmail: 'et-staff-finance@finance-demo.test',
            staffOpsEmail: 'et-staff-ops@finance-demo.test',
            walletBalance: 50_000.0,
            ledgerProfile: 'et',
            anchor: $anchor,
        );

        $jp = $this->buildAgency(
            name: 'JetPakistan',
            slug: 'jetpakistan',
            prefix: 'JP',
            ownerName: 'Nadia Ali',
            ownerEmail: 'jp-owner@finance-demo.test',
            staffFinanceName: 'Hassan Raza',
            staffFinanceEmail: 'jp-staff-finance@finance-demo.test',
            staffOpsEmail: 'jp-staff-ops@finance-demo.test',
            walletBalance: 32_500.0,
            ledgerProfile: 'jp',
            anchor: $anchor,
        );

        $dt = $this->buildAgency(
            name: 'Demo Travels',
            slug: 'demo-travels',
            prefix: 'DT',
            ownerName: 'Omar Farooq',
            ownerEmail: 'dt-owner@finance-demo.test',
            staffFinanceName: 'Sana Iqbal',
            staffFinanceEmail: 'dt-staff-finance@finance-demo.test',
            staffOpsEmail: 'dt-staff-ops@finance-demo.test',
            walletBalance: 18_750.0,
            ledgerProfile: 'dt',
            anchor: $anchor,
        );

        $customerEt = $this->createUser([
            'name' => 'Customer Easy',
            'email' => 'customer-et@finance-demo.test',
            'username' => 'customer-et',
            'account_type' => AccountType::Customer,
            'current_agency_id' => $et['agency']->id,
        ]);

        $customerJp = $this->createUser([
            'name' => 'Customer Jet',
            'email' => 'customer-jp@finance-demo.test',
            'username' => 'customer-jp',
            'account_type' => AccountType::Customer,
            'current_agency_id' => $jp['agency']->id,
        ]);

        $this->seedEtBookings($et, $customerEt, $anchor);
        $this->seedJpBookings($jp, $customerJp, $anchor);
        $this->seedDtBookings($dt, $anchor);

        foreach ([$staffFinance, $staffOps] as $staffUser) {
            $staffUser->forceFill(['current_agency_id' => $et['agency']->id])->save();
            $staffUser->agencies()->syncWithoutDetaching([
                $et['agency']->id => ['role' => 'staff'],
            ]);
        }

        return [
            'platform' => [
                'admin' => $platformAdmin,
                'staffFinance' => $staffFinance,
                'staffOps' => $staffOps,
            ],
            'agencies' => [
                'et' => $et,
                'jp' => $jp,
                'dt' => $dt,
            ],
            'customers' => [
                'et' => $customerEt,
                'jp' => $customerJp,
            ],
            'expected' => [
                'platform_ledger' => self::PLATFORM_LEDGER,
                'agencies' => [
                    'et' => [
                        'ledger' => self::AGENCY_ET_LEDGER,
                        'reports' => self::AGENCY_ET_REPORTS,
                    ],
                    'jp' => [
                        'ledger' => self::AGENCY_JP_LEDGER,
                    ],
                    'dt' => [
                        'ledger' => self::AGENCY_DT_LEDGER,
                    ],
                ],
            ],
            'anchor' => $anchor,
        ];
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    protected function createUser(array $attrs): User
    {
        return User::query()->create(array_merge([
            'password' => bcrypt('password'),
            'status' => UserAccountStatus::Active,
            'email_verified_at' => now(),
        ], $attrs));
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildAgency(
        string $name,
        string $slug,
        string $prefix,
        string $ownerName,
        string $ownerEmail,
        string $staffFinanceName,
        string $staffFinanceEmail,
        string $staffOpsEmail,
        float $walletBalance,
        string $ledgerProfile,
        Carbon $anchor,
    ): array {
        $agency = Agency::factory()->create([
            'name' => $name,
            'slug' => $slug.'-'.uniqid(),
            'timezone' => 'Asia/Karachi',
        ]);
        AgencyPrefixService::savePrefix($agency, $prefix);

        $owner = $this->createUser([
            'name' => $ownerName,
            'email' => $ownerEmail,
            'username' => str_replace('@', '-', $ownerEmail),
            'account_type' => AccountType::Agent,
            'current_agency_id' => $agency->id,
        ]);

        $agent = Agent::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $owner->id,
            'commission_percent' => 7.5,
            'is_active' => true,
        ]);

        $wallet = AgentWallet::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $owner->id,
            'balance' => $walletBalance,
            'currency' => 'PKR',
            'status' => AgentWalletStatus::Active,
        ]);

        $staffFinance = $this->createAgentStaff($agent, $staffFinanceEmail, $staffFinanceName, [
            AgentPermission::LedgerView,
            AgentPermission::ReportsView,
            AgentPermission::BookingsView,
            AgentPermission::WalletView,
        ]);

        $staffOps = $this->createAgentStaff($agent, $staffOpsEmail, 'Ops Staff', [
            AgentPermission::BookingsView,
        ]);

        $ledger = match ($ledgerProfile) {
            'et' => $this->seedEtWalletLedger($agency, $agent, $owner, $wallet, $anchor),
            'jp' => $this->seedJpWalletLedger($agency, $agent, $owner, $wallet, $anchor),
            'dt' => $this->seedDtWalletLedger($agency, $agent, $owner, $wallet, $anchor),
            default => [],
        };

        return [
            'agency' => $agency->fresh(),
            'owner' => $owner,
            'agent' => $agent,
            'wallet' => $wallet->fresh(),
            'staffFinance' => $staffFinance,
            'staffOps' => $staffOps,
            'ledger' => $ledger,
        ];
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function createAgentStaff(Agent $agent, string $email, string $name, array $permissions): User
    {
        return User::query()->create([
            'name' => $name,
            'username' => str_replace('@', '-', $email),
            'email' => $email,
            'password' => bcrypt('password'),
            'account_type' => AccountType::AgentStaff,
            'status' => UserAccountStatus::Active,
            'current_agency_id' => $agent->agency_id,
            'meta' => [
                'owner_agent_id' => $agent->id,
                'agent_permissions' => $permissions,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function seedEtWalletLedger(
        Agency $agency,
        Agent $agent,
        User $owner,
        AgentWallet $wallet,
        Carbon $anchor,
    ): array {
        $approvedDeposit1 = AgentDepositRequest::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $owner->id,
            'agent_wallet_id' => $wallet->id,
            'amount' => 20_000,
            'currency' => 'PKR',
            'payment_method' => 'bank_transfer',
            'reference' => 'ET-DEP-APPROVED-1',
            'status' => AgentDepositRequestStatus::Approved,
            'reviewed_at' => $anchor->copy()->subDays(10),
            'created_at' => $anchor->copy()->subDays(10),
        ]);

        $approvedDeposit2 = AgentDepositRequest::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $owner->id,
            'agent_wallet_id' => $wallet->id,
            'amount' => 10_000,
            'currency' => 'PKR',
            'payment_method' => 'bank_transfer',
            'reference' => 'ET-DEP-APPROVED-2',
            'status' => AgentDepositRequestStatus::Approved,
            'reviewed_at' => $anchor->copy()->subDays(3),
            'created_at' => $anchor->copy()->subDays(3),
        ]);

        $pendingDeposit = AgentDepositRequest::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $owner->id,
            'agent_wallet_id' => $wallet->id,
            'amount' => 8_000,
            'currency' => 'PKR',
            'payment_method' => 'easypaisa',
            'reference' => 'ET-DEP-PENDING',
            'status' => AgentDepositRequestStatus::Submitted,
            'created_at' => $anchor->copy()->subDay(),
        ]);

        AgentDepositRequest::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $owner->id,
            'agent_wallet_id' => $wallet->id,
            'amount' => 3_000,
            'currency' => 'PKR',
            'reference' => 'ET-DEP-REJECTED',
            'status' => AgentDepositRequestStatus::Rejected,
            'admin_note' => 'Invalid transfer proof',
            'reviewed_at' => $anchor->copy()->subDays(5),
            'created_at' => $anchor->copy()->subDays(5),
        ]);

        $tx = [];
        $tx['depositApproved1'] = $this->walletTx($wallet, $agent, $owner, [
            'type' => AgentWalletTransactionType::DepositApproved,
            'amount' => 20_000,
            'balance_before' => 0,
            'balance_after' => 20_000,
            'reference' => 'ET-DEP-APPROVED-1',
            'agent_deposit_request_id' => $approvedDeposit1->id,
            'created_at' => $anchor->copy()->subDays(10),
        ]);
        $tx['adminCredit'] = $this->walletTx($wallet, $agent, $owner, [
            'type' => AgentWalletTransactionType::AdminCredit,
            'amount' => 15_000,
            'balance_before' => 20_000,
            'balance_after' => 35_000,
            'reference' => 'ET-ADMIN-CREDIT',
            'description' => 'Opening wallet credit',
            'created_at' => $anchor->copy()->subDays(9),
        ]);
        $tx['bookingHold'] = $this->walletTx($wallet, $agent, $owner, [
            'type' => AgentWalletTransactionType::BookingHold,
            'amount' => 12_000,
            'balance_before' => 35_000,
            'balance_after' => 23_000,
            'reference' => 'ET-BKG-WALLET-001',
            'description' => 'Wallet debit for agency booking',
            'meta' => ['booking_ref' => 'ET-BKG-WALLET-001'],
            'created_at' => $anchor->copy()->subDays(8),
        ]);
        $tx['depositApproved2'] = $this->walletTx($wallet, $agent, $owner, [
            'type' => AgentWalletTransactionType::DepositApproved,
            'amount' => 10_000,
            'balance_before' => 23_000,
            'balance_after' => 33_000,
            'reference' => 'ET-DEP-APPROVED-2',
            'agent_deposit_request_id' => $approvedDeposit2->id,
            'created_at' => $anchor->copy()->subDays(3),
        ]);
        $tx['bookingRelease'] = $this->walletTx($wallet, $agent, $owner, [
            'type' => AgentWalletTransactionType::BookingRelease,
            'amount' => 2_000,
            'balance_before' => 33_000,
            'balance_after' => 35_000,
            'reference' => 'ET-BKG-RELEASE-CORRECTION',
            'description' => 'Partial hold release (correction)',
            'created_at' => $anchor->copy()->subDays(2),
        ]);
        $tx['adminDebit'] = $this->walletTx($wallet, $agent, $owner, [
            'type' => AgentWalletTransactionType::AdminDebit,
            'amount' => 5_000,
            'balance_before' => 35_000,
            'balance_after' => 30_000,
            'reference' => 'ET-ADMIN-DEBIT',
            'created_at' => $anchor->copy()->subDays(2),
        ]);
        $tx['adjustment'] = $this->walletTx($wallet, $agent, $owner, [
            'type' => AgentWalletTransactionType::Adjustment,
            'amount' => 20_000,
            'balance_before' => 30_000,
            'balance_after' => 50_000,
            'reference' => 'ET-ADJ-OPENING',
            'description' => 'Manual wallet adjustment',
            'created_at' => $anchor->copy()->subDay(),
        ]);
        $tx['depositRequestPending'] = $this->walletTx($wallet, $agent, $owner, [
            'type' => AgentWalletTransactionType::DepositRequest,
            'amount' => 8_000,
            'balance_before' => 50_000,
            'balance_after' => 50_000,
            'status' => AgentWalletTransactionStatus::Pending,
            'reference' => 'ET-DEP-PENDING',
            'agent_deposit_request_id' => $pendingDeposit->id,
            'created_at' => $anchor->copy()->subDay(),
        ]);
        $tx['depositRejected'] = $this->walletTx($wallet, $agent, $owner, [
            'type' => AgentWalletTransactionType::DepositRejected,
            'amount' => 3_000,
            'balance_before' => 50_000,
            'balance_after' => 50_000,
            'status' => AgentWalletTransactionStatus::Rejected,
            'reference' => 'ET-DEP-REJECTED',
            'created_at' => $anchor->copy()->subDays(5),
        ]);

        return [
            'transactions' => $tx,
            'deposits' => [
                'approved1' => $approvedDeposit1,
                'approved2' => $approvedDeposit2,
                'pending' => $pendingDeposit,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function seedJpWalletLedger(
        Agency $agency,
        Agent $agent,
        User $owner,
        AgentWallet $wallet,
        Carbon $anchor,
    ): array {
        $dep1 = AgentDepositRequest::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $owner->id,
            'agent_wallet_id' => $wallet->id,
            'amount' => 25_000,
            'currency' => 'PKR',
            'reference' => 'JP-DEP-1',
            'status' => AgentDepositRequestStatus::Approved,
            'reviewed_at' => $anchor->copy()->subDays(7),
            'created_at' => $anchor->copy()->subDays(7),
        ]);
        $dep2 = AgentDepositRequest::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $owner->id,
            'agent_wallet_id' => $wallet->id,
            'amount' => 15_000,
            'currency' => 'PKR',
            'reference' => 'JP-DEP-2',
            'status' => AgentDepositRequestStatus::Approved,
            'reviewed_at' => $anchor->copy()->subDays(2),
            'created_at' => $anchor->copy()->subDays(2),
        ]);

        $tx = [];
        $tx['deposit1'] = $this->walletTx($wallet, $agent, $owner, [
            'type' => AgentWalletTransactionType::DepositApproved,
            'amount' => 25_000,
            'balance_before' => 0,
            'balance_after' => 25_000,
            'reference' => 'JP-DEP-1',
            'agent_deposit_request_id' => $dep1->id,
            'created_at' => $anchor->copy()->subDays(7),
        ]);
        $tx['bookingHold'] = $this->walletTx($wallet, $agent, $owner, [
            'type' => AgentWalletTransactionType::BookingHold,
            'amount' => 7_500,
            'balance_before' => 25_000,
            'balance_after' => 17_500,
            'reference' => 'JP-BKG-WALLET-001',
            'created_at' => $anchor->copy()->subDays(5),
        ]);
        $tx['deposit2'] = $this->walletTx($wallet, $agent, $owner, [
            'type' => AgentWalletTransactionType::DepositApproved,
            'amount' => 15_000,
            'balance_before' => 17_500,
            'balance_after' => 32_500,
            'reference' => 'JP-DEP-2',
            'agent_deposit_request_id' => $dep2->id,
            'created_at' => $anchor->copy()->subDays(2),
        ]);

        return ['transactions' => $tx, 'deposits' => ['dep1' => $dep1, 'dep2' => $dep2]];
    }

    /**
     * @return array<string, mixed>
     */
    protected function seedDtWalletLedger(
        Agency $agency,
        Agent $agent,
        User $owner,
        AgentWallet $wallet,
        Carbon $anchor,
    ): array {
        $approved = AgentDepositRequest::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $owner->id,
            'agent_wallet_id' => $wallet->id,
            'amount' => 10_000,
            'currency' => 'PKR',
            'reference' => 'DT-DEP-APPROVED',
            'status' => AgentDepositRequestStatus::Approved,
            'reviewed_at' => $anchor->copy()->subDays(6),
            'created_at' => $anchor->copy()->subDays(6),
        ]);
        $pending = AgentDepositRequest::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $owner->id,
            'agent_wallet_id' => $wallet->id,
            'amount' => 10_000,
            'currency' => 'PKR',
            'reference' => 'DT-DEP-PENDING',
            'status' => AgentDepositRequestStatus::Submitted,
            'created_at' => $anchor->copy()->subDay(),
        ]);

        $tx = [];
        $tx['depositApproved'] = $this->walletTx($wallet, $agent, $owner, [
            'type' => AgentWalletTransactionType::DepositApproved,
            'amount' => 10_000,
            'balance_before' => 0,
            'balance_after' => 10_000,
            'reference' => 'DT-DEP-APPROVED',
            'agent_deposit_request_id' => $approved->id,
            'created_at' => $anchor->copy()->subDays(6),
        ]);
        $tx['adminCredit'] = $this->walletTx($wallet, $agent, $owner, [
            'type' => AgentWalletTransactionType::AdminCredit,
            'amount' => 5_000,
            'balance_before' => 10_000,
            'balance_after' => 15_000,
            'reference' => 'DT-ADMIN-CREDIT',
            'created_at' => $anchor->copy()->subDays(5),
        ]);
        $tx['bookingHold'] = $this->walletTx($wallet, $agent, $owner, [
            'type' => AgentWalletTransactionType::BookingHold,
            'amount' => 6_250,
            'balance_before' => 15_000,
            'balance_after' => 8_750,
            'reference' => 'DT-BKG-WALLET-001',
            'created_at' => $anchor->copy()->subDays(4),
        ]);
        $tx['adjustment'] = $this->walletTx($wallet, $agent, $owner, [
            'type' => AgentWalletTransactionType::Adjustment,
            'amount' => 10_000,
            'balance_before' => 8_750,
            'balance_after' => 18_750,
            'reference' => 'DT-ADJ-1',
            'created_at' => $anchor->copy()->subDays(2),
        ]);
        $tx['depositRequestPending'] = $this->walletTx($wallet, $agent, $owner, [
            'type' => AgentWalletTransactionType::DepositRequest,
            'amount' => 10_000,
            'balance_before' => 18_750,
            'balance_after' => 18_750,
            'status' => AgentWalletTransactionStatus::Pending,
            'reference' => 'DT-DEP-PENDING',
            'agent_deposit_request_id' => $pending->id,
            'created_at' => $anchor->copy()->subDay(),
        ]);

        return [
            'transactions' => $tx,
            'deposits' => ['approved' => $approved, 'pending' => $pending],
        ];
    }

    /**
     * @param  array<string, mixed>  $et
     */
    protected function seedEtBookings(array $et, User $customer, Carbon $anchor): void
    {
        $agency = $et['agency'];
        $agent = $et['agent'];

        $walletBooking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'status' => BookingStatus::Confirmed,
            'booking_reference' => 'ET-BKG-WALLET-001',
            'payment_status' => 'paid',
            'amount_paid' => 12_000,
            'balance_due' => 0,
            'source_channel' => 'agent_portal',
            'confirmed_at' => $anchor->copy()->subDays(8),
            'created_at' => $anchor->copy()->subDays(8),
            'meta' => ['supplier_total' => 10_500],
        ]);
        $this->fare($walletBooking, base: 9_000, taxes: 1_000, fees: 500, markup: 1_500, total: 12_000);
        $et['ledger']['transactions']['bookingHold']->update([
            'meta' => ['booking_id' => $walletBooking->id, 'booking_ref' => 'ET-BKG-WALLET-001'],
        ]);

        AgentCommissionEntry::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'booking_id' => $walletBooking->id,
            'type' => AgentCommissionEntryType::Earned,
            'status' => AgentCommissionEntryStatus::Approved,
            'calculation_basis' => 'percentage',
            'rate' => 7.5,
            'base_amount' => 9_000,
            'commission_amount' => 675,
            'currency' => 'PKR',
            'description' => 'Commission on ET-BKG-WALLET-001',
        ]);

        $pendingBooking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'status' => BookingStatus::PaymentPending,
            'booking_reference' => 'ET-BKG-PENDING-001',
            'payment_status' => 'partial',
            'amount_paid' => 4_000,
            'balance_due' => 8_500,
            'source_channel' => 'agent_portal',
            'created_at' => $anchor->copy()->subDays(4),
        ]);
        $this->fare($pendingBooking, base: 10_000, taxes: 1_500, fees: 1_000, markup: 0, total: 12_500);

        $customerBooking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'status' => BookingStatus::Paid,
            'booking_reference' => 'ET-BKG-CUST-001',
            'payment_status' => 'paid',
            'amount_paid' => 25_000,
            'balance_due' => 0,
            'source_channel' => 'public_web',
            'created_at' => $anchor->copy()->subDays(6),
        ]);
        $this->fare($customerBooking, base: 20_000, taxes: 2_000, fees: 0, markup: 3_000, total: 25_000);
        BookingPayment::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $customerBooking->id,
            'payer_user_id' => $customer->id,
            'method' => BookingPaymentMethod::BankTransfer,
            'status' => BookingPaymentStatus::Verified,
            'amount' => 25_000,
            'currency' => 'PKR',
            'verified_at' => $anchor->copy()->subDays(5),
            'submitted_at' => $anchor->copy()->subDays(6),
        ]);

        $guestBooking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'customer_id' => null,
            'status' => BookingStatus::Paid,
            'booking_reference' => 'ET-BKG-GUEST-001',
            'payment_status' => 'paid',
            'amount_paid' => 18_000,
            'balance_due' => 0,
            'source_channel' => 'public_guest',
            'created_at' => $anchor->copy()->subDays(5),
        ]);
        $this->fare($guestBooking, base: 14_000, taxes: 1_500, fees: 500, markup: 2_000, total: 18_000);
        BookingContact::query()->create([
            'booking_id' => $guestBooking->id,
            'email' => 'guest.traveler@example.com',
            'phone' => '+923001234567',
            'meta' => ['guest_id' => 9001, 'first_name' => 'Guest', 'last_name' => 'Traveler'],
        ]);
        BookingPayment::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $guestBooking->id,
            'method' => BookingPaymentMethod::Easypaisa,
            'status' => BookingPaymentStatus::Verified,
            'amount' => 18_000,
            'currency' => 'PKR',
            'verified_at' => $anchor->copy()->subDays(4),
            'submitted_at' => $anchor->copy()->subDays(5),
        ]);

        $cancelled = Booking::factory()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'status' => BookingStatus::Cancelled,
            'booking_reference' => 'ET-BKG-CANCEL-001',
            'payment_status' => 'unpaid',
            'cancellation_status' => 'processed',
            'cancelled_at' => $anchor->copy()->subDays(3),
            'created_at' => $anchor->copy()->subDays(7),
            'meta' => ['cancellation_fee' => 1_500],
        ]);
        $this->fare($cancelled, base: 8_000, taxes: 1_000, fees: 500, markup: 500, total: 10_000);

        $refunded = Booking::factory()->create([
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'status' => BookingStatus::Refunded,
            'booking_reference' => 'ET-BKG-REFUND-001',
            'payment_status' => 'paid',
            'refund_status' => 'refunded',
            'amount_paid' => 30_000,
            'balance_due' => 0,
            'source_channel' => 'public_web',
            'created_at' => $anchor->copy()->subDays(9),
        ]);
        $this->fare($refunded, base: 24_000, taxes: 2_500, fees: 1_000, markup: 2_500, total: 30_000);
        $refundPayment = BookingPayment::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $refunded->id,
            'payer_user_id' => $customer->id,
            'method' => BookingPaymentMethod::CardManual,
            'status' => BookingPaymentStatus::Verified,
            'amount' => 30_000,
            'currency' => 'PKR',
            'verified_at' => $anchor->copy()->subDays(8),
        ]);
        BookingRefund::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $refunded->id,
            'booking_payment_id' => $refundPayment->id,
            'amount' => 28_000,
            'currency' => 'PKR',
            'method' => 'bank_transfer',
            'status' => BookingRefundStatus::Paid,
            'reference' => 'ET-REF-PAID-001',
            'paid_at' => $anchor->copy()->subDays(2),
            'meta' => ['cancellation_fee_retained' => 2_000],
        ]);
    }

    /**
     * @param  array<string, mixed>  $jp
     */
    protected function seedJpBookings(array $jp, User $customer, Carbon $anchor): void
    {
        $agency = $jp['agency'];
        $agent = $jp['agent'];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'status' => BookingStatus::Confirmed,
            'booking_reference' => 'JP-BKG-001',
            'payment_status' => 'paid',
            'amount_paid' => 15_000,
            'source_channel' => 'agent_portal',
            'created_at' => $anchor->copy()->subDays(5),
        ]);
        $this->fare($booking, base: 12_000, taxes: 1_500, fees: 500, markup: 1_000, total: 15_000);

        $customerBooking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'status' => BookingStatus::PaymentPending,
            'booking_reference' => 'JP-BKG-CUST-001',
            'payment_status' => 'unpaid',
            'balance_due' => 22_000,
            'source_channel' => 'public_web',
            'created_at' => $anchor->copy()->subDays(3),
        ]);
        $this->fare($customerBooking, base: 18_000, taxes: 2_000, fees: 2_000, markup: 0, total: 22_000);
    }

    /**
     * @param  array<string, mixed>  $dt
     */
    protected function seedDtBookings(array $dt, Carbon $anchor): void
    {
        $agency = $dt['agency'];

        $guestBooking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Pending,
            'booking_reference' => 'DT-BKG-GUEST-001',
            'payment_status' => 'unpaid',
            'source_channel' => 'public_guest',
            'created_at' => $anchor->copy()->subDays(2),
        ]);
        $this->fare($guestBooking, base: 11_000, taxes: 1_000, fees: 500, markup: 1_500, total: 14_000);
        BookingContact::query()->create([
            'booking_id' => $guestBooking->id,
            'email' => 'dt.guest@example.com',
            'meta' => ['guest_id' => 9002, 'first_name' => 'Danish', 'last_name' => 'Guest'],
        ]);
    }

    protected function fare(
        Booking $booking,
        float $base,
        float $taxes,
        float $fees,
        float $markup,
        float $total,
    ): BookingFareBreakdown {
        return BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => $base,
            'taxes' => $taxes,
            'fees' => $fees,
            'markup' => $markup,
            'discount' => 0,
            'total' => $total,
            'currency' => 'PKR',
            'breakdown' => ['supplier_total' => $base + $taxes],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function walletTx(
        AgentWallet $wallet,
        Agent $agent,
        User $owner,
        array $overrides,
    ): AgentWalletTransaction {
        return AgentWalletTransaction::query()->create(array_merge([
            'agency_id' => $agent->agency_id,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'agent_wallet_id' => $wallet->id,
            'status' => AgentWalletTransactionStatus::Posted,
            'created_by' => $owner->id,
        ], $overrides));
    }
}
