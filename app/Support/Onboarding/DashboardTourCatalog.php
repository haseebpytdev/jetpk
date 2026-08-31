<?php

namespace App\Support\Onboarding;

use App\Models\User;
use App\Support\AgentPortal\AgentPortalCapabilitiesPresenter;
use App\Support\BackOffice\BackOfficeCapabilitiesPresenter;

/**
 * Role-aware guided-tour step catalogs. Staff steps are filtered from live navigation.
 */
final class DashboardTourCatalog
{
    public function __construct(
        protected AgentPortalCapabilitiesPresenter $agentCapabilities,
        protected BackOfficeCapabilitiesPresenter $backOfficeCapabilities,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function stepsForCustomer(): array
    {
        return [
            $this->step('welcome', 'Welcome to your dashboard', 'A quick tour of bookings, payments, travelers, and support.', null),
            $this->step('bookings', 'My Bookings', 'Track upcoming trips, resumes, and booking details here.', 'bookings'),
            $this->step('payments', 'Payments', 'Review payment status and proofs for your bookings.', 'payments'),
            $this->step('travelers', 'Saved Travelers', 'Reuse passenger details for faster checkout next time.', 'travelers'),
            $this->step('profile', 'Profile & Security', 'Keep contact details and password settings up to date.', 'profile'),
            $this->step('support', 'Support', 'Open a support case when you need help with a booking.', 'support'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function stepsForAgent(User $user): array
    {
        $capabilities = $this->agentCapabilities->present($user);
        $availableCodes = collect($capabilities['navigation'] ?? [])
            ->filter(static fn (array $item): bool => (bool) ($item['available'] ?? false))
            ->pluck('code')
            ->filter(static fn ($code): bool => is_string($code) && $code !== '')
            ->values()
            ->all();

        $candidates = [
            $this->step('welcome', 'Welcome to the agent portal', 'Learn the essentials: bookings, wallet, travelers, and support.', null),
            $this->step('overview', 'Dashboard', 'Your agency overview and quick actions live here.', 'overview'),
            $this->step('bookings', 'Bookings', 'Search, create, and manage agency bookings.', 'bookings', requires: 'bookings'),
            $this->step('wallet', 'Wallet', 'Check balances, deposits, and payment activity.', 'wallet', requires: 'wallet'),
            $this->step('travelers', 'Travelers', 'Manage saved travelers for agency bookings.', 'travelers', requires: 'travelers'),
            $this->step('profile', 'Profile', 'Update your personal profile and security settings.', 'profile'),
            $this->step('support', 'Support', 'Raise and track support tickets for your agency.', 'support', requires: 'support'),
        ];

        return array_values(array_filter(
            $candidates,
            static function (array $step) use ($availableCodes): bool {
                $requires = $step['requires_nav'] ?? null;
                if ($requires === null) {
                    return true;
                }

                return in_array($requires, $availableCodes, true);
            },
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function stepsForAdmin(): array
    {
        return [
            $this->step('welcome', 'Welcome to Admin', 'A short orientation for the JetPakistan back-office console.', null),
            $this->step('dashboard', 'Operations overview', 'Start here for live bookings, payments, and work queues.', 'dashboard'),
            $this->step('bookings', 'Bookings', 'Inspect and action customer and agent bookings.', 'bookings'),
            $this->step('customers', 'Customers & agents', 'Manage customer accounts and agency partners.', 'customers'),
            $this->step(
                'api-modules',
                'API Settings',
                'Supplier connections and Google OAuth live under API & Modules (Integrations).',
                'api-modules',
            ),
            $this->step('settings', 'Settings', 'Platform settings, notifications, and system health.', 'settings'),
            $this->step('support', 'Support', 'Handle support tickets from customers and agents.', 'support'),
        ];
    }

    /**
     * Staff steps are derived from permission-filtered navigation — never inject Admin-only targets.
     *
     * @return list<array<string, mixed>>
     */
    public function stepsForStaff(User $user): array
    {
        $capabilities = $this->backOfficeCapabilities->present($user, 'staff');
        $navigation = $capabilities['navigation'] ?? [];

        $adminOnlyKeys = [
            'agent-applications',
            'deposits',
            'markups',
            'commissions',
            'staff',
            'roles-permissions',
            'system-health',
            'go-live',
            'cms-pages',
            'media',
            'homepage',
        ];

        $steps = [
            $this->step('welcome', 'Welcome to Staff', 'This tour only highlights areas you can access.', null),
        ];

        foreach ($navigation as $item) {
            if (! is_array($item)) {
                continue;
            }
            $key = (string) ($item['key'] ?? '');
            if ($key === '' || in_array($key, $adminOnlyKeys, true)) {
                continue;
            }

            $label = (string) ($item['label'] ?? $key);
            $steps[] = $this->step(
                $key,
                $label,
                'Open '.$label.' from the sidebar when you need this workspace.',
                $key,
            );
        }

        return $steps;
    }

    /**
     * Optional admin mini-guide for API settings / Google OAuth location.
     *
     * @return list<array<string, mixed>>
     */
    public function stepsForAdminApiSettingsMini(): array
    {
        return [
            $this->step(
                'api-modules',
                'Google OAuth location',
                'Configure Google customer OAuth under Admin → API & Modules (Integrations / API Settings).',
                'api-modules',
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function step(
        string $id,
        string $title,
        string $body,
        ?string $target,
        ?string $requires = null,
    ): array {
        $step = [
            'id' => $id,
            'title' => $title,
            'body' => $body,
            'target' => $target,
        ];

        if ($requires !== null) {
            $step['requires_nav'] = $requires;
        }

        return $step;
    }
}
