<?php

namespace App\Support\AgentPortal;

use App\Support\Agents\AgentPermission;
use App\Support\Platform\PlatformModuleEnforcer;
use App\Models\User;

/**
 * Agent portal navigation and capability flags for Next.js shell.
 */
class AgentPortalCapabilitiesPresenter
{
    public function __construct(
        protected PlatformModuleEnforcer $platformModules,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(User $user): array
    {
        $isOwner = $user->isAgentAdmin();
        $roleLabel = $isOwner ? 'Agency owner' : 'Agency staff';
        $agencyName = $user->agentDisplayAgencyName();

        $permissions = [
            'bookings_view' => $user->hasAgentPermission(AgentPermission::BookingsView),
            'bookings_create' => $user->hasAgentPermission(AgentPermission::BookingsCreate),
            'wallet_view' => $user->hasAgentPermission(AgentPermission::WalletView),
            'ledger_view' => $user->hasAgentPermission(AgentPermission::LedgerView),
            'reports_view' => $user->hasAgentPermission(AgentPermission::ReportsView),
            'payments_upload' => $user->hasAgentPermission(AgentPermission::PaymentsUpload),
            'commissions_view' => $isOwner,
            'travelers_manage' => $user->hasAgentPermission(AgentPermission::TravelersManage),
            'support_manage' => $user->hasAgentPermission(AgentPermission::SupportManage),
            'agency_view' => $user->hasAgentPermission(AgentPermission::AgencyView),
            'agency_edit' => $user->hasAgentPermission(AgentPermission::AgencyEdit),
            'staff_manage' => $user->hasAgentPermission(AgentPermission::StaffManage),
        ];

        $modules = [
            'agent_wallet' => $this->platformModules->routeEnabled('agent_wallet'),
            'agent_deposits' => $this->platformModules->routeEnabled('agent_deposits'),
            'agent_ledger' => $this->platformModules->routeEnabled('agent_ledger'),
            'agent_support' => $this->platformModules->routeEnabled('agent_support'),
            'payment_proofs' => $this->platformModules->routeEnabled('payment_proofs'),
        ];

        return [
            'ok' => true,
            'identity' => [
                'display_name' => $user->name,
                'email' => $user->email,
                'role' => $isOwner ? 'agent' : 'agent_staff',
                'role_label' => $roleLabel,
                'is_owner' => $isOwner,
            ],
            'agency' => [
                'name' => $agencyName,
            ],
            'permissions' => $permissions,
            'modules' => $modules,
            'navigation' => $this->presentNavigation($permissions, $modules),
        ];
    }

    /**
     * @param  array<string, bool>  $permissions
     * @param  array<string, bool>  $modules
     * @return list<array<string, mixed>>
     */
    private function presentNavigation(array $permissions, array $modules): array
    {
        $items = [
            ['code' => 'overview', 'label' => 'Overview', 'href' => '/agent/dashboard', 'available' => true],
        ];

        if ($permissions['bookings_view']) {
            $items[] = ['code' => 'bookings', 'label' => 'Bookings', 'href' => '/agent/bookings', 'available' => true];
        }

        if ($permissions['wallet_view'] && $modules['agent_wallet']) {
            $items[] = ['code' => 'wallet', 'label' => 'Wallet', 'href' => '/agent/wallet', 'available' => true];
        }

        if ($permissions['ledger_view'] && $modules['agent_ledger']) {
            $items[] = ['code' => 'ledger', 'label' => 'Ledger', 'href' => '/agent/wallet/ledger', 'available' => true];
        }

        if ($permissions['wallet_view'] && $modules['agent_deposits']) {
            $items[] = ['code' => 'deposits', 'label' => 'Deposits', 'href' => '/agent/deposits', 'available' => true];
        }

        if ($permissions['wallet_view']) {
            $items[] = ['code' => 'payments', 'label' => 'Payments', 'href' => '/agent/payments', 'available' => true];
            $items[] = ['code' => 'invoices', 'label' => 'Invoices', 'href' => '/agent/invoices', 'available' => true];
        }

        $items[] = ['code' => 'profile', 'label' => 'Profile', 'href' => '/agent/profile', 'available' => true];
        $items[] = ['code' => 'security', 'label' => 'Security', 'href' => '/agent/security', 'available' => true];

        if ($permissions['support_manage'] && $modules['agent_support']) {
            $items[] = ['code' => 'support', 'label' => 'Support', 'href' => '/agent/support', 'available' => true];
        }

        $items[] = ['code' => 'notifications', 'label' => 'Notifications', 'href' => '/agent/notifications', 'available' => true];

        return $items;
    }
}
