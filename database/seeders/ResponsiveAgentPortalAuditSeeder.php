<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\AgentDepositRequestStatus;
use App\Enums\AgentWalletStatus;
use App\Enums\AgentWalletTransactionStatus;
use App\Enums\AgentWalletTransactionType;
use App\Enums\BookingStatus;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketStatus;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentDepositRequest;
use App\Models\AgentWallet;
use App\Models\AgentWalletTransaction;
use App\Models\Booking;
use App\Models\SavedTraveler;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\Agents\AgentPermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local-only dataset for Playwright Agent / Agent Staff responsive audits.
 *
 * Run manually: php artisan db:seed --class=ResponsiveAgentPortalAuditSeeder
 *
 * Does not run from DatabaseSeeder. Idempotent via updateOrCreate.
 */
class ResponsiveAgentPortalAuditSeeder extends Seeder
{
    private const PASSWORD = 'Password123!';

    public function run(): void
    {
        $jet = $this->seedJetPakistanAgency();
        $this->seedEasyTicketAgency();

        $this->command?->info('Responsive agent portal audit data ready.');
        $this->command?->info('Agent admin: '.$jet['admin']->email);
        $this->command?->info('Staff restricted: staff.restricted@ota.demo');
        $this->command?->info('Staff broad: staff.full@ota.demo');
        $this->command?->info('Password: '.self::PASSWORD);
    }

    /**
     * @return array{agency: Agency, agent: Agent, admin: User, wallet: AgentWallet}
     */
    private function seedJetPakistanAgency(): array
    {
        $agency = Agency::query()->updateOrCreate(
            ['slug' => 'jetpakistan-audit'],
            [
                'name' => 'JetPakistan',
                'timezone' => 'Asia/Karachi',
                'settings' => ['audit_seeded' => true],
            ],
        );

        $admin = User::query()->updateOrCreate(
            ['email' => 'agent.jetpakistan@example.test'],
            [
                'name' => 'Asif Khalil',
                'username' => 'agent-jetpakistan',
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
                'current_agency_id' => $agency->id,
                'account_type' => AccountType::Agent,
                'status' => UserAccountStatus::Active,
            ],
        );

        $admin->agencies()->syncWithoutDetaching([
            $agency->id => ['role' => 'agent'],
        ]);

        $agent = Agent::query()->updateOrCreate(
            [
                'agency_id' => $agency->id,
                'user_id' => $admin->id,
            ],
            [
                'code' => 'AGT-JetPakistan-Asif',
                'commission_percent' => 7.5,
                'is_active' => true,
                'meta' => [
                    'agency_name' => 'JetPakistan',
                    'license_number' => 'JP-LIC-2026',
                    'city' => 'Lahore',
                    'country' => 'Pakistan',
                ],
            ],
        );

        $wallet = AgentWallet::query()->updateOrCreate(
            [
                'agency_id' => $agency->id,
                'agent_id' => $agent->id,
            ],
            [
                'user_id' => $admin->id,
                'balance' => 75000,
                'credit_limit' => 150000,
                'currency' => 'PKR',
                'status' => AgentWalletStatus::Active,
            ],
        );

        $this->seedJetPakistanFinance($agency, $agent, $admin, $wallet);
        $this->seedJetPakistanBookings($agency, $agent);
        $this->seedJetPakistanTravelers($agency, $admin);
        $this->seedJetPakistanSupport($agency, $admin);
        $this->seedJetPakistanStaff($agent);

        return compact('agency', 'agent', 'admin', 'wallet');
    }

    private function seedEasyTicketAgency(): void
    {
        $agency = Agency::query()->updateOrCreate(
            ['slug' => 'easyticket-audit'],
            [
                'name' => 'EasyTicket International Travel Services',
                'timezone' => 'Asia/Karachi',
                'settings' => ['audit_seeded' => true],
            ],
        );

        $admin = User::query()->updateOrCreate(
            ['email' => 'agent.easyticket@example.test'],
            [
                'name' => 'Easy Admin',
                'username' => 'agent-easyticket',
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
                'current_agency_id' => $agency->id,
                'account_type' => AccountType::Agent,
                'status' => UserAccountStatus::Active,
            ],
        );

        $admin->agencies()->syncWithoutDetaching([
            $agency->id => ['role' => 'agent'],
        ]);

        $agent = Agent::query()->updateOrCreate(
            [
                'agency_id' => $agency->id,
                'user_id' => $admin->id,
            ],
            [
                'code' => 'AGT-EasyTicket-Admin',
                'is_active' => true,
                'meta' => ['agency_name' => 'EasyTicket International Travel Services'],
            ],
        );

        AgentWallet::query()->updateOrCreate(
            [
                'agency_id' => $agency->id,
                'agent_id' => $agent->id,
            ],
            [
                'user_id' => $admin->id,
                'balance' => 12500.50,
                'credit_limit' => 40000,
                'currency' => 'PKR',
                'status' => AgentWalletStatus::Active,
            ],
        );

        Booking::query()->updateOrCreate(
            ['booking_reference' => 'BKG-EASY-CONFIRMED'],
            [
                'agency_id' => $agency->id,
                'agent_id' => $agent->id,
                'status' => BookingStatus::Confirmed,
                'payment_status' => 'paid',
                'pnr' => 'EASY-PNR-99',
            ],
        );
    }

    private function seedJetPakistanFinance(Agency $agency, Agent $agent, User $admin, AgentWallet $wallet): void
    {
        $pending = AgentDepositRequest::query()->updateOrCreate(
            ['reference' => 'DEP-JP-PENDING'],
            [
                'agency_id' => $agency->id,
                'agent_id' => $agent->id,
                'user_id' => $admin->id,
                'agent_wallet_id' => $wallet->id,
                'amount' => 5000,
                'currency' => 'PKR',
                'payment_method' => 'bank_transfer',
                'proof_path' => 'deposit-proofs/audit-pending.pdf',
                'status' => AgentDepositRequestStatus::Submitted,
            ],
        );

        $approved = AgentDepositRequest::query()->updateOrCreate(
            ['reference' => 'DEP-JP-APPROVED'],
            [
                'agency_id' => $agency->id,
                'agent_id' => $agent->id,
                'user_id' => $admin->id,
                'agent_wallet_id' => $wallet->id,
                'amount' => 10000,
                'currency' => 'PKR',
                'payment_method' => 'bank_transfer',
                'proof_path' => 'deposit-proofs/audit-approved.pdf',
                'status' => AgentDepositRequestStatus::Approved,
                'reviewed_at' => now(),
            ],
        );

        AgentDepositRequest::query()->updateOrCreate(
            ['reference' => 'DEP-JP-REJECTED'],
            [
                'agency_id' => $agency->id,
                'agent_id' => $agent->id,
                'user_id' => $admin->id,
                'agent_wallet_id' => $wallet->id,
                'amount' => 2500,
                'currency' => 'PKR',
                'payment_method' => 'bank_transfer',
                'status' => AgentDepositRequestStatus::Rejected,
                'reviewed_at' => now(),
                'admin_note' => 'Proof unreadable (audit seed)',
            ],
        );

        $this->upsertWalletTxn($wallet, $agent, 'ADMIN-CREDIT-JP', [
            'type' => AgentWalletTransactionType::AdminCredit,
            'amount' => 50000,
            'balance_before' => 0,
            'balance_after' => 50000,
            'status' => AgentWalletTransactionStatus::Posted,
            'description' => 'Opening credit (audit seed)',
        ]);

        $this->upsertWalletTxn($wallet, $agent, 'DEP-JP-APPROVED', [
            'type' => AgentWalletTransactionType::DepositApproved,
            'amount' => 10000,
            'balance_before' => 50000,
            'balance_after' => 60000,
            'status' => AgentWalletTransactionStatus::Posted,
            'agent_deposit_request_id' => $approved->id,
        ]);

        $this->upsertWalletTxn($wallet, $agent, 'DEP-JP-PENDING', [
            'type' => AgentWalletTransactionType::DepositRequest,
            'amount' => 5000,
            'balance_before' => 60000,
            'balance_after' => 60000,
            'status' => AgentWalletTransactionStatus::Pending,
            'agent_deposit_request_id' => $pending->id,
        ]);

        $this->upsertWalletTxn($wallet, $agent, 'LEDGER-JP-HOLD-001', [
            'type' => AgentWalletTransactionType::BookingHold,
            'amount' => 3500,
            'balance_before' => 60000,
            'balance_after' => 56500,
            'status' => AgentWalletTransactionStatus::Posted,
            'description' => 'Booking hold BKG-JP-PENDING',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function upsertWalletTxn(AgentWallet $wallet, Agent $agent, string $reference, array $overrides): void
    {
        AgentWalletTransaction::query()->updateOrCreate(
            ['reference' => $reference],
            array_merge([
                'agency_id' => $agent->agency_id,
                'agent_id' => $agent->id,
                'user_id' => $agent->user_id,
                'agent_wallet_id' => $wallet->id,
                'type' => AgentWalletTransactionType::Adjustment,
                'amount' => 0,
                'balance_before' => 0,
                'balance_after' => 0,
                'status' => AgentWalletTransactionStatus::Posted,
                'description' => null,
                'meta' => null,
            ], $overrides),
        );
    }

    private function seedJetPakistanBookings(Agency $agency, Agent $agent): void
    {
        $refs = [
            ['ref' => 'BKG-JP-PENDING', 'status' => BookingStatus::Pending, 'payment' => 'unpaid'],
            ['ref' => 'BKG-JP-PAID', 'status' => BookingStatus::Paid, 'payment' => 'paid', 'pnr' => 'JP-PNR-445'],
            ['ref' => 'BKG-JP-PROOF', 'status' => BookingStatus::PaymentPending, 'payment' => 'awaiting_proof'],
            ['ref' => 'BKG-JP-CANCELLED', 'status' => BookingStatus::Cancelled, 'payment' => 'unpaid'],
        ];

        foreach ($refs as $row) {
            Booking::query()->updateOrCreate(
                ['booking_reference' => $row['ref']],
                [
                    'agency_id' => $agency->id,
                    'agent_id' => $agent->id,
                    'status' => $row['status'],
                    'payment_status' => $row['payment'],
                    'pnr' => $row['pnr'] ?? null,
                    'amount_paid' => $row['status'] === BookingStatus::Paid ? 42000 : 0,
                    'balance_due' => $row['status'] === BookingStatus::Paid ? 0 : 42000,
                ],
            );
        }
    }

    private function seedJetPakistanTravelers(Agency $agency, User $admin): void
    {
        SavedTraveler::query()->updateOrCreate(
            [
                'agency_id' => $agency->id,
                'user_id' => $admin->id,
                'first_name' => 'Ayesha',
                'last_name' => 'Khan',
            ],
            ['is_default' => true, 'document_number' => 'AB1234567'],
        );

        SavedTraveler::query()->updateOrCreate(
            [
                'agency_id' => $agency->id,
                'user_id' => $admin->id,
                'first_name' => 'Hassan',
                'last_name' => 'Raza',
            ],
            ['is_default' => false, 'document_number' => 'CD9876543'],
        );

        SavedTraveler::query()->updateOrCreate(
            [
                'agency_id' => $agency->id,
                'user_id' => $admin->id,
                'first_name' => 'Incomplete',
                'last_name' => 'Traveler',
            ],
            ['is_default' => false, 'document_number' => null],
        );
    }

    private function seedJetPakistanSupport(Agency $agency, User $admin): void
    {
        SupportTicket::query()->updateOrCreate(
            ['subject' => 'Audit ticket — open'],
            [
                'agency_id' => $agency->id,
                'created_by_user_id' => $admin->id,
                'category' => SupportTicketCategory::Booking,
                'priority' => 'normal',
                'status' => SupportTicketStatus::Open,
            ],
        );

        SupportTicket::query()->updateOrCreate(
            ['subject' => 'Audit ticket — pending reply'],
            [
                'agency_id' => $agency->id,
                'created_by_user_id' => $admin->id,
                'category' => SupportTicketCategory::Payment,
                'priority' => 'normal',
                'status' => SupportTicketStatus::Pending,
            ],
        );

        SupportTicket::query()->updateOrCreate(
            ['subject' => 'Audit ticket — closed'],
            [
                'agency_id' => $agency->id,
                'created_by_user_id' => $admin->id,
                'category' => SupportTicketCategory::Other,
                'priority' => 'low',
                'status' => SupportTicketStatus::Closed,
                'closed_at' => now(),
            ],
        );
    }

    private function seedJetPakistanStaff(Agent $agent): void
    {
        $this->upsertStaffUser(
            $agent,
            'staff.restricted@ota.demo',
            'Ali Restricted',
            'staff-restricted',
            [],
        );

        $this->upsertStaffUser(
            $agent,
            'staff.full@ota.demo',
            'Sara Full Access',
            'staff-full',
            [
                AgentPermission::BookingsView,
                AgentPermission::BookingsCreate,
                AgentPermission::WalletView,
                AgentPermission::LedgerView,
                AgentPermission::PaymentsUpload,
                AgentPermission::TravelersManage,
                AgentPermission::SupportManage,
                AgentPermission::AgencyView,
            ],
        );
    }

    /**
     * @param  list<string>  $permissions
     */
    private function upsertStaffUser(Agent $agent, string $email, string $name, string $username, array $permissions): void
    {
        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'username' => $username,
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
                'account_type' => AccountType::AgentStaff,
                'status' => UserAccountStatus::Active,
                'current_agency_id' => $agent->agency_id,
                'meta' => [
                    'owner_agent_id' => $agent->id,
                    'agent_permissions' => $permissions,
                ],
            ],
        );
    }
}
