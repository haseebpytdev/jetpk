<?php

namespace App\Support\AgentPortal;

use App\Enums\AccountType;
use App\Models\Agent;
use App\Models\User;
use App\Support\Agencies\AgencyRolePermissionMatrix;
use App\Support\Agencies\AgencyRoleResolver;
use App\Support\Agents\AgentPermission;
use Illuminate\Support\Collection;

/**
 * Agent portal staff management JSON presenter.
 */
class AgentPortalStaffPresenter
{
    /**
     * @param  Collection<int, User>  $staffMembers
     * @return array<string, mixed>
     */
    public function presentIndex(Agent $ownerAgent, Collection $staffMembers, User $actor): array
    {
        return [
            'ok' => true,
            'staff' => $staffMembers
                ->map(fn (User $member): array => $this->presentStaffSummary($member, $ownerAgent))
                ->values()
                ->all(),
            'capabilities' => [
                'can_create' => $actor->isAgentAdmin() || $actor->hasAgentPermission(AgentPermission::StaffManage),
                'can_manage_permissions' => $actor->isAgentAdmin(),
            ],
            'permission_labels' => $this->permissionLabels(),
            'grouped_permissions' => $this->groupedPermissionLabels(),
            'role_templates' => collect(AgencyRolePermissionMatrix::roleLabels())
                ->map(function (string $label, string $value): ?array {
                    $role = \App\Enums\AgencyRole::tryFrom($value);
                    if ($role === null) {
                        return null;
                    }

                    return [
                        'value' => $value,
                        'label' => $label,
                        'summary' => AgencyRolePermissionMatrix::suggestedPermissionSummary($role),
                    ];
                })
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentCreateForm(): array
    {
        return [
            'ok' => true,
            'permission_labels' => $this->permissionLabels(),
            'default_permissions' => [
                AgentPermission::BookingsView,
                AgentPermission::AgencyView,
            ],
            'submit_url' => '/laravel/agent/staff',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentEditForm(User $staff, Agent $ownerAgent, User $actor): array
    {
        $agencyId = (int) $ownerAgent->agency_id;
        $agencyRole = AgencyRoleResolver::resolve($staff, $agencyId);

        return [
            'ok' => true,
            'staff' => $this->presentStaffDetail($staff, $ownerAgent),
            'permission_labels' => $this->permissionLabels(),
            'grouped_permissions' => $this->groupedPermissionLabels(),
            'selected_permissions' => $staff->meta['agent_permissions'] ?? [],
            'agency_role' => [
                'value' => $agencyRole->value,
                'label' => AgencyRoleResolver::labelFor($staff, $agencyId),
            ],
            'capabilities' => [
                'can_update' => $actor->isAgentAdmin() || ($actor->hasAgentPermission(AgentPermission::StaffManage) && (int) $actor->id !== (int) $staff->id),
                'can_update_permissions' => $actor->isAgentAdmin() && (int) $actor->id !== (int) $staff->id,
                'can_apply_template' => $actor->isAgentAdmin() && $agencyRole->value !== 'owner',
                'can_deactivate' => $actor->isAgentAdmin() && (int) $actor->id !== (int) $staff->id,
            ],
            'update_url' => '/laravel/agent/staff/'.$staff->id,
            'permissions_update_url' => '/laravel/agent/staff/'.$staff->id.'/permissions',
            'apply_template_url' => '/laravel/agent/staff/'.$staff->id.'/permissions/apply-template',
            'agency_role_update_url' => '/laravel/agent/staff/'.$staff->id.'/agency-role',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentStaffSummary(User $staff, Agent $ownerAgent): array
    {
        $agencyId = (int) $ownerAgent->agency_id;

        return [
            'id' => $staff->id,
            'name' => $staff->name,
            'email' => $staff->email,
            'status' => $staff->status?->value ?? 'active',
            'role_label' => AgencyRoleResolver::labelFor($staff, $agencyId),
            'permissions_count' => count($staff->meta['agent_permissions'] ?? []),
            'edit_url' => '/agent/staff/'.$staff->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentStaffDetail(User $staff, Agent $ownerAgent): array
    {
        $summary = $this->presentStaffSummary($staff, $ownerAgent);

        return array_merge($summary, [
            'phone' => data_get($staff->meta, 'phone'),
            'permissions' => array_values($staff->meta['agent_permissions'] ?? []),
            'account_type' => AccountType::AgentStaff->value,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function permissionLabels(): array
    {
        return array_intersect_key(
            AgentPermission::labels(),
            array_flip(AgentPermission::staffSelectable()),
        );
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function groupedPermissionLabels(): array
    {
        $labels = $this->permissionLabels();
        $groups = [];
        foreach (\App\Support\Access\RolePermissionMatrix::agentStaffModuleGroups() as $groupName => $keys) {
            $items = [];
            foreach ($keys as $key) {
                if (isset($labels[$key])) {
                    $items[$key] = $labels[$key];
                }
            }
            if ($items !== []) {
                $groups[$groupName] = $items;
            }
        }

        return $groups;
    }
}
