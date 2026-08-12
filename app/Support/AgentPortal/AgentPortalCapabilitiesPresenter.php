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
        $access = AgentPortalAccess::evaluate($user);
        $isOwner = $user->isAgentAdmin();
        $roleLabel = $isOwner ? 'Agency owner' : 'Agency staff';
        $agencyName = $user->agentDisplayAgencyName();
        $agent = $user->agent();

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
            'agent_reports' => $this->platformModules->routeEnabled('agent_reports'),
            'agent_staff' => $this->platformModules->routeEnabled('agent_staff'),
            'payment_proofs' => $this->platformModules->routeEnabled('payment_proofs'),
            'saved_travelers' => $this->platformModules->routeEnabled('saved_travelers'),
        ];

        $capabilities = $this->presentCapabilityFlags($user, $permissions, $modules, $isOwner, $access);

        return [
            'ok' => ($access['ok'] ?? false) === true,
            'session_usable' => ($access['ok'] ?? false) === true,
            'denial_reason' => $access['denial_reason'] ?? null,
            'identity' => [
                'display_name' => $user->name,
                'email' => $user->email,
                'role' => $isOwner ? 'agent' : 'agent_staff',
                'role_label' => $roleLabel,
                'is_owner' => $isOwner,
                'status' => $user->status?->value ?? 'active',
            ],
            'agency' => [
                'name' => $agencyName,
                'status' => $agent !== null && $agent->is_active ? 'active' : 'inactive',
            ],
            'permissions' => $permissions,
            'modules' => $modules,
            'capabilities' => $capabilities,
            'navigation' => $this->presentNavigation($permissions, $modules, $isOwner),
        ];
    }

    /**
     * @param  array<string, bool>  $permissions
     * @param  array<string, bool>  $modules
     * @param  array{ok: bool, denial_reason?: string|null}  $access
     * @return array<string, mixed>
     */
    private function presentCapabilityFlags(
        User $user,
        array $permissions,
        array $modules,
        bool $isOwner,
        array $access,
    ): array {
        $usable = ($access['ok'] ?? false) === true;

        return [
            'can_view_booking' => $usable && ($permissions['bookings_view'] ?? false),
            'can_create_booking' => $usable && ($permissions['bookings_create'] ?? false),
            'can_view_wallet' => $usable && ($permissions['wallet_view'] ?? false) && ($modules['agent_wallet'] ?? false),
            'can_view_ledger' => $usable && ($permissions['ledger_view'] ?? false) && ($modules['agent_ledger'] ?? false),
            'can_submit_deposit' => $usable && ($permissions['payments_upload'] ?? false) && ($modules['agent_deposits'] ?? false),
            'can_view_commission' => $usable && $isOwner,
            'can_manage_staff' => $usable && ($permissions['staff_manage'] ?? false) && ($modules['agent_staff'] ?? false),
            'can_manage_staff_permissions' => $usable && $isOwner && ($permissions['staff_manage'] ?? false),
            'can_view_reports' => $usable && ($permissions['reports_view'] ?? false) && ($modules['agent_reports'] ?? false),
            'can_export_reports' => $usable && ($permissions['reports_view'] ?? false) && ($modules['agent_reports'] ?? false),
            'can_contact_support' => $usable && ($permissions['support_manage'] ?? false) && ($modules['agent_support'] ?? false),
            'can_view_agency' => $usable && ($permissions['agency_view'] ?? false),
            'can_edit_agency' => $usable && $isOwner,
            'can_download_document' => $usable && ($permissions['wallet_view'] ?? false),
            'reason_codes' => $usable ? [] : [
                'session' => $access['denial_reason'] ?? 'permission_required',
            ],
        ];
    }

    /**
     * @param  array<string, bool>  $permissions
     * @param  array<string, bool>  $modules
     * @return list<array<string, mixed>>
     */
    private function presentNavigation(array $permissions, array $modules, bool $isOwner): array
    {
        $items = [
            [
                'code' => 'overview',
                'label' => 'Overview',
                'href' => '/agent/dashboard',
                'available' => true,
                'group' => 'Overview',
            ],
        ];

        if ($permissions['bookings_view']) {
            $items[] = [
                'code' => 'bookings',
                'label' => 'Bookings',
                'href' => '/agent/bookings',
                'available' => true,
                'group' => 'Bookings',
            ];
        }

        if ($permissions['bookings_create']) {
            $items[] = [
                'code' => 'booking_create',
                'label' => 'New booking',
                'href' => '/agent/bookings/create',
                'available' => true,
                'group' => 'Bookings',
            ];
        }

        if ($permissions['wallet_view'] && $modules['agent_wallet']) {
            $items[] = [
                'code' => 'wallet',
                'label' => 'Wallet',
                'href' => '/agent/wallet',
                'available' => true,
                'group' => 'Finance',
            ];
        }

        if ($permissions['wallet_view'] && $modules['agent_deposits']) {
            $items[] = [
                'code' => 'deposits',
                'label' => 'Deposits',
                'href' => '/agent/deposits',
                'available' => true,
                'group' => 'Finance',
            ];
        }

        if ($permissions['ledger_view'] && $modules['agent_ledger']) {
            $items[] = [
                'code' => 'ledger',
                'label' => 'Ledger',
                'href' => '/agent/wallet/ledger',
                'available' => true,
                'group' => 'Finance',
            ];
        }

        if (($permissions['reports_view'] || $permissions['ledger_view']) && $modules['agent_reports']) {
            $items[] = [
                'code' => 'finance_statement',
                'label' => 'Statement',
                'href' => '/agent/finance/statement',
                'available' => true,
                'group' => 'Finance',
            ];
        }

        if ($permissions['wallet_view']) {
            $items[] = [
                'code' => 'invoices',
                'label' => 'Invoices',
                'href' => '/agent/invoices',
                'available' => true,
                'group' => 'Finance',
            ];
        }

        if ($isOwner) {
            $items[] = [
                'code' => 'commissions',
                'label' => 'Commissions',
                'href' => '/agent/commissions',
                'available' => true,
                'group' => 'Finance',
            ];
        }

        if ($permissions['reports_view'] && $modules['agent_reports']) {
            $items[] = [
                'code' => 'reports',
                'label' => 'Reports',
                'href' => '/agent/reports',
                'available' => true,
                'group' => 'Finance',
            ];
        }

        if ($permissions['agency_view']) {
            $items[] = [
                'code' => 'agency',
                'label' => 'Agency',
                'href' => '/agent/agency',
                'available' => true,
                'group' => 'Agency',
            ];
        }

        if ($permissions['travelers_manage'] && $modules['saved_travelers']) {
            $items[] = [
                'code' => 'travelers',
                'label' => 'Travelers',
                'href' => '/agent/travelers',
                'available' => true,
                'group' => 'Agency',
            ];
        }

        if ($permissions['staff_manage'] && $modules['agent_staff']) {
            $items[] = [
                'code' => 'staff',
                'label' => 'Staff',
                'href' => '/agent/staff',
                'available' => true,
                'group' => 'Agency',
            ];
        }

        if ($permissions['support_manage'] && $modules['agent_support']) {
            $items[] = [
                'code' => 'support',
                'label' => 'Support',
                'href' => '/agent/support',
                'available' => true,
                'group' => 'Support',
            ];
        }

        $items[] = [
            'code' => 'notifications',
            'label' => 'Notifications',
            'href' => '/agent/notifications',
            'available' => true,
            'group' => 'Support',
        ];

        $items[] = [
            'code' => 'profile',
            'label' => 'Profile',
            'href' => '/agent/profile',
            'available' => true,
            'group' => 'Account',
        ];
        $items[] = [
            'code' => 'security',
            'label' => 'Security',
            'href' => '/agent/security',
            'available' => true,
            'group' => 'Account',
        ];

        return $items;
    }
}
