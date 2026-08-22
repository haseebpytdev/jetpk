<?php

namespace App\Support\Dashboard;

/**
 * Dashboard permission catalogue aligned with Next.js PERMISSION_CATALOG.
 */
final class DashboardPermissionCatalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return array_map(
            static fn (array $row): array => $row + [
                'assignedRoleCount' => 0,
                'applicableAccountTypes' => ['platform_admin', 'staff', 'agent', 'agent_staff'],
            ],
            self::seeds(),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $key): ?array
    {
        foreach (self::seeds() as $seed) {
            if ($seed['key'] === $key) {
                return $seed + [
                    'assignedRoleCount' => DashboardRoleCatalog::rolesWithPermission($key),
                    'applicableAccountTypes' => ['platform_admin', 'staff', 'agent', 'agent_staff'],
                ];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function highRiskKeys(): array
    {
        return [
            'bookings.cancel.approve',
            'payments.refund.approve',
            'pnrs.cancel.approve',
            'tickets.issue.approve',
            'users.assignRoles',
            'roles.assignPermissions',
            'settings.update',
            'cms.publish.approve',
            'users.suspend',
            'audit.export',
            'integrations.activate',
            'integrations.test-payment',
        ];
    }

    public static function isHighRisk(string $key): bool
    {
        return in_array($key, self::highRiskKeys(), true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function seeds(): array
    {
        return [
            ['id' => 'dashboard.view', 'key' => 'dashboard.view', 'label' => 'View dashboard', 'category' => 'dashboard', 'scope' => 'allRecords', 'channel' => 'all', 'highRisk' => false, 'description' => 'Access the operations dashboard overview.', 'risk' => 'standard'],
            ['id' => 'bookings.view', 'key' => 'bookings.view', 'label' => 'View bookings', 'category' => 'bookings', 'scope' => 'allRecords', 'channel' => 'all', 'highRisk' => false, 'description' => 'View booking records and summaries.', 'risk' => 'standard'],
            ['id' => 'payments.view', 'key' => 'payments.view', 'label' => 'View payments', 'category' => 'payments', 'scope' => 'allRecords', 'channel' => 'all', 'highRisk' => false, 'description' => 'View payment ledger and transactions.', 'risk' => 'standard'],
            ['id' => 'customers.view', 'key' => 'customers.view', 'label' => 'View customers', 'category' => 'customers', 'scope' => 'allRecords', 'channel' => 'all', 'highRisk' => false, 'description' => 'View customer profiles.', 'risk' => 'standard'],
            ['id' => 'suppliers.view', 'key' => 'suppliers.view', 'label' => 'View suppliers', 'category' => 'suppliers', 'scope' => 'allRecords', 'channel' => 'all', 'highRisk' => false, 'description' => 'View supplier connection metadata.', 'risk' => 'standard'],
            ['id' => 'agents.view', 'key' => 'agents.view', 'label' => 'View agents', 'category' => 'agents', 'scope' => 'allRecords', 'channel' => 'all', 'highRisk' => false, 'description' => 'View agent accounts.', 'risk' => 'standard'],
            ['id' => 'pnrs.view', 'key' => 'pnrs.view', 'label' => 'View PNRs and orders', 'category' => 'pnrs', 'scope' => 'allRecords', 'channel' => 'all', 'highRisk' => false, 'description' => 'View PNR and NDC order records.', 'risk' => 'standard'],
            ['id' => 'tickets.view', 'key' => 'tickets.view', 'label' => 'View tickets', 'category' => 'tickets', 'scope' => 'allRecords', 'channel' => 'all', 'highRisk' => false, 'description' => 'View ticket and document records.', 'risk' => 'standard'],
            ['id' => 'reports.view', 'key' => 'reports.view', 'label' => 'View reports', 'category' => 'reports', 'scope' => 'allRecords', 'channel' => 'all', 'highRisk' => false, 'description' => 'Access analytics and reports.', 'risk' => 'standard'],
            ['id' => 'cms.view', 'key' => 'cms.view', 'label' => 'View CMS', 'category' => 'cms', 'scope' => 'allRecords', 'channel' => 'all', 'highRisk' => false, 'description' => 'View CMS content records.', 'risk' => 'standard'],
            ['id' => 'users.view', 'key' => 'users.view', 'label' => 'View users', 'category' => 'users', 'scope' => 'allRecords', 'channel' => 'all', 'highRisk' => false, 'description' => 'View dashboard user directory.', 'risk' => 'standard'],
            ['id' => 'users.assignRoles', 'key' => 'users.assignRoles', 'label' => 'Assign user roles', 'category' => 'users', 'scope' => 'allRecords', 'channel' => 'all', 'highRisk' => true, 'description' => 'Assign roles to users.', 'risk' => 'high'],
            ['id' => 'roles.view', 'key' => 'roles.view', 'label' => 'View roles', 'category' => 'roles', 'scope' => 'allRecords', 'channel' => 'all', 'highRisk' => false, 'description' => 'View role definitions.', 'risk' => 'standard'],
            ['id' => 'roles.assignPermissions', 'key' => 'roles.assignPermissions', 'label' => 'Assign role permissions', 'category' => 'roles', 'scope' => 'allRecords', 'channel' => 'all', 'highRisk' => true, 'description' => 'Assign permissions to roles.', 'risk' => 'high'],
            ['id' => 'settings.view', 'key' => 'settings.view', 'label' => 'View settings', 'category' => 'settings', 'scope' => 'allRecords', 'channel' => 'all', 'highRisk' => false, 'description' => 'View system settings metadata.', 'risk' => 'standard'],
            ['id' => 'settings.update', 'key' => 'settings.update', 'label' => 'Update settings', 'category' => 'settings', 'scope' => 'allRecords', 'channel' => 'all', 'highRisk' => true, 'description' => 'Update settings metadata.', 'risk' => 'high'],
            ['id' => 'integrations.view', 'key' => 'integrations.view', 'label' => 'View integrations', 'category' => 'integrations', 'scope' => 'allRecords', 'channel' => 'all', 'highRisk' => false, 'description' => 'View Integration Hub status and configuration summaries.', 'risk' => 'standard'],
            ['id' => 'integrations.manage', 'key' => 'integrations.manage', 'label' => 'Manage integrations', 'category' => 'integrations', 'scope' => 'allRecords', 'channel' => 'all', 'highRisk' => false, 'description' => 'Update integration settings and replace secrets.', 'risk' => 'elevated'],
            ['id' => 'integrations.test', 'key' => 'integrations.test', 'label' => 'Test integrations', 'category' => 'integrations', 'scope' => 'allRecords', 'channel' => 'all', 'highRisk' => false, 'description' => 'Run non-commercial connection tests.', 'risk' => 'elevated'],
            ['id' => 'integrations.activate', 'key' => 'integrations.activate', 'label' => 'Activate integrations', 'category' => 'integrations', 'scope' => 'allRecords', 'channel' => 'all', 'highRisk' => true, 'description' => 'Enable or disable runtime integrations.', 'risk' => 'high'],
            ['id' => 'integrations.test-payment', 'key' => 'integrations.test-payment', 'label' => 'Integration test payments', 'category' => 'integrations', 'scope' => 'allRecords', 'channel' => 'all', 'highRisk' => true, 'description' => 'Create test-mode diagnostic payment transactions.', 'risk' => 'high'],
            ['id' => 'integrations.audit', 'key' => 'integrations.audit', 'label' => 'View integration audit', 'category' => 'integrations', 'scope' => 'allRecords', 'channel' => 'all', 'highRisk' => false, 'description' => 'View sanitized integration audit history.', 'risk' => 'standard'],
            ['id' => 'audit.view', 'key' => 'audit.view', 'label' => 'View audit log', 'category' => 'audit', 'scope' => 'allRecords', 'channel' => 'all', 'highRisk' => false, 'description' => 'View audit event history.', 'risk' => 'standard'],
            ['id' => 'audit.export', 'key' => 'audit.export', 'label' => 'Export audit log', 'category' => 'audit', 'scope' => 'allRecords', 'channel' => 'all', 'highRisk' => true, 'description' => 'Export audit events.', 'risk' => 'high'],
        ];
    }
}
