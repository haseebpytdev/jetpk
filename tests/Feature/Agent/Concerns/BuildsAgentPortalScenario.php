<?php

namespace Tests\Feature\Agent\Concerns;

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

/**
 * Builds a two-agency agent portal dataset for scenario / isolation tests.
 *
 * @phpstan-type AgentPortalScenario array{
 *     agencyA: Agency,
 *     agencyB: Agency,
 *     adminA: User,
 *     adminB: User,
 *     agentA: Agent,
 *     agentB: Agent,
 *     walletA: AgentWallet,
 *     walletB: AgentWallet,
 *     staff: array<string, User>,
 *     recordsA: array<string, mixed>,
 *     recordsB: array<string, mixed>,
 * }
 */
trait BuildsAgentPortalScenario
{
    /**
     * @return AgentPortalScenario
     */
    protected function buildAgentPortalScenario(): array
    {
        $agencyA = Agency::factory()->create(['name' => 'Agency Alpha', 'slug' => 'agency-alpha-'.uniqid()]);
        $agencyB = Agency::factory()->create(['name' => 'Agency Beta', 'slug' => 'agency-beta-'.uniqid()]);

        $adminA = User::factory()->agent()->create([
            'name' => 'Agent Admin A',
            'email' => 'agent-admin-a@scenario.test',
            'username' => 'agent-admin-a',
            'current_agency_id' => $agencyA->id,
        ]);
        $adminB = User::factory()->agent()->create([
            'name' => 'Agent Admin B',
            'email' => 'agent-admin-b@scenario.test',
            'username' => 'agent-admin-b',
            'current_agency_id' => $agencyB->id,
        ]);

        $agentA = Agent::factory()->create([
            'agency_id' => $agencyA->id,
            'user_id' => $adminA->id,
            'meta' => [
                'agency_name' => 'Alpha Travel Services',
                'license_number' => 'LIC-A-001',
                'city' => 'Lahore',
                'country' => 'Pakistan',
            ],
        ]);
        $agentB = Agent::factory()->create([
            'agency_id' => $agencyB->id,
            'user_id' => $adminB->id,
            'meta' => [
                'agency_name' => 'Beta Travel Services',
                'license_number' => 'LIC-B-001',
            ],
        ]);

        $walletA = $this->createScenarioWallet($agentA, 25000, 50000);
        $walletB = $this->createScenarioWallet($agentB, 8000, null);

        $staff = $this->createScenarioStaff($agentA);

        $recordsA = $this->seedAgentARecords($agencyA, $agentA, $adminA, $walletA, $staff);
        $recordsB = $this->seedAgentBRecords($agencyB, $agentB, $adminB, $walletB);

        return [
            'agencyA' => $agencyA,
            'agencyB' => $agencyB,
            'adminA' => $adminA,
            'adminB' => $adminB,
            'agentA' => $agentA,
            'agentB' => $agentB,
            'walletA' => $walletA->fresh(),
            'walletB' => $walletB->fresh(),
            'staff' => $staff,
            'recordsA' => $recordsA,
            'recordsB' => $recordsB,
        ];
    }

    /**
     * @return array<string, User>
     */
    protected function createScenarioStaff(Agent $agent): array
    {
        $matrix = [
            'A0' => [],
            'A1' => [AgentPermission::BookingsView],
            'A2' => [AgentPermission::BookingsView, AgentPermission::BookingsCreate],
            'A3' => [AgentPermission::WalletView],
            'A4' => [AgentPermission::WalletView, AgentPermission::LedgerView],
            'A5' => [AgentPermission::WalletView, AgentPermission::PaymentsUpload],
            'A6' => [AgentPermission::AgencyView],
            'A7' => [AgentPermission::AgencyView, AgentPermission::AgencyEdit],
            'A8' => [AgentPermission::TravelersManage],
            'A9' => [AgentPermission::SupportManage],
            'A10' => [AgentPermission::StaffManage],
            'A11' => AgentPermission::staffSelectable(),
        ];

        $staff = [];
        foreach ($matrix as $key => $permissions) {
            $email = strtolower($key).'@alpha-staff.test';
            $staff[$key] = $this->createAgentStaffUser($agent, $email, $permissions, 'Staff '.$key);
        }

        return $staff;
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function createAgentStaffUser(Agent $agent, string $email, array $permissions, string $name): User
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

    protected function createScenarioWallet(Agent $agent, float $balance, ?float $creditLimit): AgentWallet
    {
        return AgentWallet::query()->create([
            'agency_id' => $agent->agency_id,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'balance' => $balance,
            'credit_limit' => $creditLimit,
            'currency' => 'PKR',
            'status' => AgentWalletStatus::Active,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function seedAgentARecords(
        Agency $agency,
        Agent $agent,
        User $admin,
        AgentWallet $wallet,
        array $staff,
    ): array {
        $pendingDeposit = AgentDepositRequest::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $admin->id,
            'agent_wallet_id' => $wallet->id,
            'amount' => 5000,
            'currency' => 'PKR',
            'payment_method' => 'bank_transfer',
            'reference' => 'DEP-PENDING-A',
            'proof_path' => 'deposit-proofs/fake-pending-a.pdf',
            'status' => AgentDepositRequestStatus::Submitted,
        ]);

        $approvedDeposit = AgentDepositRequest::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $admin->id,
            'agent_wallet_id' => $wallet->id,
            'amount' => 10000,
            'currency' => 'PKR',
            'payment_method' => 'bank_transfer',
            'reference' => 'DEP-APPROVED-A',
            'proof_path' => 'deposit-proofs/fake-approved-a.pdf',
            'status' => AgentDepositRequestStatus::Approved,
            'reviewed_at' => now(),
        ]);

        $rejectedDeposit = AgentDepositRequest::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $admin->id,
            'agent_wallet_id' => $wallet->id,
            'amount' => 3000,
            'currency' => 'PKR',
            'payment_method' => 'bank_transfer',
            'reference' => 'DEP-REJECTED-A',
            'status' => AgentDepositRequestStatus::Rejected,
            'reviewed_at' => now(),
            'admin_note' => 'Invalid proof',
        ]);

        $transactions = [
            'adminCredit' => $this->createWalletTransaction($wallet, $agent, [
                'type' => AgentWalletTransactionType::AdminCredit,
                'amount' => 10000,
                'balance_before' => 0,
                'balance_after' => 10000,
                'status' => AgentWalletTransactionStatus::Posted,
                'reference' => 'ADMIN-CREDIT-A',
                'description' => 'Opening admin credit',
                'meta' => ['source' => 'admin'],
            ]),
            'depositApproved' => $this->createWalletTransaction($wallet, $agent, [
                'type' => AgentWalletTransactionType::DepositApproved,
                'amount' => 10000,
                'balance_before' => 10000,
                'balance_after' => 20000,
                'status' => AgentWalletTransactionStatus::Posted,
                'reference' => 'DEP-APPROVED-A',
                'description' => 'Approved deposit',
                'agent_deposit_request_id' => $approvedDeposit->id,
            ]),
            'depositRequest' => $this->createWalletTransaction($wallet, $agent, [
                'type' => AgentWalletTransactionType::DepositRequest,
                'amount' => 5000,
                'balance_before' => 20000,
                'balance_after' => 20000,
                'status' => AgentWalletTransactionStatus::Pending,
                'reference' => 'DEP-PENDING-A',
                'agent_deposit_request_id' => $pendingDeposit->id,
            ]),
            'adminDebit' => $this->createWalletTransaction($wallet, $agent, [
                'type' => AgentWalletTransactionType::AdminDebit,
                'amount' => 2000,
                'balance_before' => 20000,
                'balance_after' => 18000,
                'status' => AgentWalletTransactionStatus::Posted,
                'reference' => 'ADMIN-DEBIT-A',
                'description' => 'Admin debit adjustment',
            ]),
            'bookingHold' => $this->createWalletTransaction($wallet, $agent, [
                'type' => AgentWalletTransactionType::BookingHold,
                'amount' => 3000,
                'balance_before' => 18000,
                'balance_after' => 15000,
                'status' => AgentWalletTransactionStatus::Posted,
                'reference' => 'HOLD-BKG-A',
                'description' => 'Booking hold',
            ]),
            'bookingRelease' => $this->createWalletTransaction($wallet, $agent, [
                'type' => AgentWalletTransactionType::BookingRelease,
                'amount' => 1000,
                'balance_before' => 15000,
                'balance_after' => 16000,
                'status' => AgentWalletTransactionStatus::Posted,
                'reference' => 'RELEASE-BKG-A',
            ]),
            'adjustmentNoRef' => $this->createWalletTransaction($wallet, $agent, [
                'type' => AgentWalletTransactionType::Adjustment,
                'amount' => 9000,
                'balance_before' => 16000,
                'balance_after' => 25000,
                'status' => AgentWalletTransactionStatus::Posted,
                'reference' => null,
                'description' => 'Manual adjustment without reference',
                'meta' => null,
            ]),
        ];

        $pendingBooking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'status' => BookingStatus::Pending,
            'booking_reference' => 'BKG-PENDING-A',
            'payment_status' => 'unpaid',
        ]);

        $paidBooking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'status' => BookingStatus::Paid,
            'booking_reference' => 'BKG-PAID-A',
            'payment_status' => 'paid',
            'pnr' => 'PNR-A-123',
            'amount_paid' => 45000,
            'balance_due' => 0,
        ]);

        $paymentPendingBooking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'status' => BookingStatus::PaymentPending,
            'booking_reference' => 'BKG-PROOF-A',
            'payment_status' => 'awaiting_proof',
            'meta' => ['supplier_reference' => 'SUP-REF-A'],
        ]);

        $cancelledBooking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'status' => BookingStatus::Cancelled,
            'booking_reference' => 'BKG-CANCELLED-A',
            'payment_status' => 'unpaid',
            'cancellation_status' => 'cancelled',
        ]);

        $completeTraveler = SavedTraveler::factory()->create([
            'user_id' => $admin->id,
            'agency_id' => $agency->id,
            'first_name' => 'Complete',
            'last_name' => 'Traveler',
            'is_default' => true,
        ]);

        $incompleteTraveler = SavedTraveler::factory()->create([
            'user_id' => $admin->id,
            'agency_id' => $agency->id,
            'first_name' => 'Incomplete',
            'last_name' => 'Traveler',
            'document_number' => null,
            'document_expiry' => null,
            'is_default' => false,
        ]);

        $ticketOpen = SupportTicket::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $paidBooking->id,
            'created_by_user_id' => $admin->id,
            'subject' => 'Ticket open by admin A',
            'category' => SupportTicketCategory::Booking,
            'priority' => 'normal',
            'status' => SupportTicketStatus::Open,
        ]);

        $ticketPending = SupportTicket::query()->create([
            'agency_id' => $agency->id,
            'created_by_user_id' => $staff['A9']->id,
            'subject' => 'Ticket pending by staff A9',
            'category' => SupportTicketCategory::Payment,
            'priority' => 'normal',
            'status' => SupportTicketStatus::Pending,
        ]);

        $ticketResolved = SupportTicket::query()->create([
            'agency_id' => $agency->id,
            'created_by_user_id' => $admin->id,
            'subject' => 'Ticket resolved A',
            'category' => SupportTicketCategory::Other,
            'priority' => 'low',
            'status' => SupportTicketStatus::Resolved,
            'closed_at' => now(),
        ]);

        $ticketClosed = SupportTicket::query()->create([
            'agency_id' => $agency->id,
            'created_by_user_id' => $admin->id,
            'subject' => 'Ticket closed A',
            'category' => SupportTicketCategory::Technical,
            'priority' => 'low',
            'status' => SupportTicketStatus::Closed,
            'closed_at' => now(),
        ]);

        return [
            'deposits' => [
                'pending' => $pendingDeposit,
                'approved' => $approvedDeposit,
                'rejected' => $rejectedDeposit,
            ],
            'transactions' => $transactions,
            'bookings' => [
                'pending' => $pendingBooking,
                'paid' => $paidBooking,
                'paymentPending' => $paymentPendingBooking,
                'cancelled' => $cancelledBooking,
            ],
            'travelers' => [
                'complete' => $completeTraveler,
                'incomplete' => $incompleteTraveler,
            ],
            'tickets' => [
                'open' => $ticketOpen,
                'pending' => $ticketPending,
                'resolved' => $ticketResolved,
                'closed' => $ticketClosed,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function seedAgentBRecords(Agency $agency, Agent $agent, User $admin, AgentWallet $wallet): array
    {
        $deposit = AgentDepositRequest::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'user_id' => $admin->id,
            'agent_wallet_id' => $wallet->id,
            'amount' => 2500,
            'currency' => 'PKR',
            'status' => AgentDepositRequestStatus::Submitted,
            'reference' => 'DEP-B-ONLY',
        ]);

        $transaction = $this->createWalletTransaction($wallet, $agent, [
            'type' => AgentWalletTransactionType::DepositApproved,
            'amount' => 2500,
            'balance_before' => 5500,
            'balance_after' => 8000,
            'status' => AgentWalletTransactionStatus::Posted,
            'reference' => 'LEDGER-B-ONLY',
        ]);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'status' => BookingStatus::Confirmed,
            'booking_reference' => 'BKG-B-ONLY',
        ]);

        $traveler = SavedTraveler::factory()->create([
            'user_id' => $admin->id,
            'agency_id' => $agency->id,
            'first_name' => 'Beta',
            'last_name' => 'Traveler',
        ]);

        $staffB = $this->createAgentStaffUser($agent, 'staff-b@beta-staff.test', [AgentPermission::BookingsView], 'Staff B');

        $ticket = SupportTicket::query()->create([
            'agency_id' => $agency->id,
            'created_by_user_id' => $admin->id,
            'subject' => 'Ticket B isolation',
            'category' => SupportTicketCategory::Other,
            'priority' => 'normal',
            'status' => SupportTicketStatus::Open,
        ]);

        return [
            'deposit' => $deposit,
            'transaction' => $transaction,
            'booking' => $booking,
            'traveler' => $traveler,
            'staff' => $staffB,
            'ticket' => $ticket,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createWalletTransaction(AgentWallet $wallet, Agent $agent, array $overrides): AgentWalletTransaction
    {
        return AgentWalletTransaction::query()->create(array_merge([
            'agency_id' => $agent->agency_id,
            'agent_id' => $agent->id,
            'user_id' => $agent->user_id,
            'agent_wallet_id' => $wallet->id,
            'type' => AgentWalletTransactionType::Adjustment,
            'amount' => 0,
            'balance_before' => 0,
            'balance_after' => 0,
            'status' => AgentWalletTransactionStatus::Posted,
            'reference' => null,
            'description' => null,
            'meta' => null,
        ], $overrides));
    }
}
