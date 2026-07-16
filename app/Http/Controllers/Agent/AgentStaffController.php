<?php

namespace App\Http\Controllers\Agent;

use App\Enums\AccountType;
use App\Enums\AgencyRole;
use App\Enums\UserAccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StoreAgentStaffRequest;
use App\Http\Requests\Agent\UpdateAgentStaffRequest;
use App\Models\AgencyUser;
use App\Models\Agent;
use App\Models\User;
use App\Policies\AgentStaffPolicy;
use App\Support\Access\RolePermissionMatrix;
use App\Support\Agencies\AgencyRoleAssignment;
use App\Support\Agencies\AgencyRolePermissionMatrix;
use App\Support\Agencies\AgencyRoleResolver;
use App\Support\Agents\AgentPermission;
use App\Support\Ui\MobileViewPreference;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Agent portal staff user management (scoped to the authenticated agent business).
 */
class AgentStaffController extends Controller
{
    public function __construct(
        protected MobileViewPreference $mobileViewPreference,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeStaff('viewAny');

        $ownerAgent = $this->ownerAgent();
        $staffMembers = $this->staffQuery($ownerAgent)->orderBy('name')->get();
        $agencyId = (int) $ownerAgent->agency_id;
        $staffAgencyRoles = $staffMembers->mapWithKeys(
            fn (User $member): array => [
                $member->id => AgencyRoleResolver::labelFor($member, $agencyId),
            ],
        );
        $staffAgencyRoleValues = $staffMembers->mapWithKeys(
            fn (User $member): array => [
                $member->id => AgencyRoleResolver::resolve($member, $agencyId)->value,
            ],
        );
        $actor = auth()->user();

        $viewData = [
            'staffMembers' => $staffMembers,
            'staffAgencyRoles' => $staffAgencyRoles,
            'staffAgencyRoleValues' => $staffAgencyRoleValues,
            'agencyRoleOptions' => $actor !== null
                ? AgencyRoleAssignment::roleOptionsForActor($actor)
                : [],
            'permissionLabels' => $this->staffPermissionLabels(),
        ];

        if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
            return view('mobile.agent.staff.index', $viewData);
        }

        return view(client_view('staff.index', 'agent'), $viewData);
    }

    public function create(Request $request): View
    {
        $this->authorizeStaff('create');

        $viewData = [
            'permissionLabels' => $this->staffPermissionLabels(),
            'defaultPermissions' => [
                AgentPermission::BookingsView,
                AgentPermission::AgencyView,
            ],
        ];

        if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
            return view('mobile.agent.staff.create', $viewData);
        }

        return view(client_view('staff.create', 'agent'), $viewData);
    }

    public function store(StoreAgentStaffRequest $request): RedirectResponse
    {
        $ownerAgent = $this->ownerAgent();

        $permissions = $this->normalizePermissions($request->input('permissions', []));

        $email = $request->string('email')->toString();

        $staff = User::query()->create([
            'name' => $request->string('name')->toString(),
            'username' => $this->uniqueUsername($email),
            'email' => $email,
            'password' => Hash::make($request->string('password')->toString()),
            'account_type' => AccountType::AgentStaff,
            'status' => UserAccountStatus::Active,
            'current_agency_id' => $ownerAgent->agency_id,
            'meta' => [
                'phone' => $request->string('phone')->toString() ?: null,
                'owner_agent_id' => $ownerAgent->id,
                'agent_permissions' => $permissions,
            ],
        ]);

        AgencyUser::query()->updateOrCreate(
            ['agency_id' => $ownerAgent->agency_id, 'user_id' => $staff->id],
            ['role' => AccountType::AgentStaff->value],
        );

        return redirect()
            ->route('agent.staff.index')
            ->with('status', 'staff-created');
    }

    public function edit(Request $request, User $staff): View
    {
        $this->authorizeStaff('view', $staff);

        $ownerAgent = $this->ownerAgent();
        $agencyId = (int) $ownerAgent->agency_id;
        $agencyRole = AgencyRoleResolver::resolve($staff, $agencyId);

        $viewData = [
            'staff' => $staff,
            'permissionLabels' => $this->staffPermissionLabels(),
            'selectedPermissions' => $staff->meta['agent_permissions'] ?? [],
            'groupedAgentPermissions' => $this->groupedStaffPermissionLabels(),
            'permissionsUpdateRoute' => route('agent.staff.permissions.update', $staff),
            'agentPermissionsApplyTemplateRoute' => route('agent.staff.permissions.apply-template', $staff),
            'canApplyAgentStaffRoleTemplate' => $agencyRole !== AgencyRole::Owner,
            'agentStaffRoleTemplateSummary' => AgencyRolePermissionMatrix::suggestedPermissionSummary($agencyRole),
            'agencyRoleLabel' => AgencyRoleResolver::labelFor($staff, $agencyId),
            'showAgentStaffOwnerLabelWarning' => $agencyRole === AgencyRole::Owner,
        ];

        if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
            return view('mobile.agent.staff.edit', $viewData);
        }

        return view(client_view('staff.edit', 'agent'), $viewData);
    }

    public function update(UpdateAgentStaffRequest $request, User $staff): RedirectResponse
    {
        $meta = is_array($staff->meta) ? $staff->meta : [];
        $meta['phone'] = $request->string('phone')->toString() ?: null;
        if ($request->has('permissions')) {
            $meta['agent_permissions'] = $this->normalizePermissions($request->input('permissions', []));
        }

        $staff->forceFill([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'status' => $request->enum('status', UserAccountStatus::class),
            'meta' => $meta,
        ]);

        if ($request->filled('password')) {
            $staff->password = Hash::make($request->string('password')->toString());
        }

        $staff->save();

        return redirect()
            ->route('agent.staff.index')
            ->with('status', 'staff-updated');
    }

    public function destroy(User $staff): RedirectResponse
    {
        $this->authorizeStaff('delete', $staff);

        $staff->forceFill(['status' => UserAccountStatus::Inactive])->save();

        return redirect()
            ->route('agent.staff.index')
            ->with('status', 'staff-disabled');
    }

    protected function ownerAgent(): Agent
    {
        $agent = auth()->user()?->agent();
        abort_if($agent === null, 403);

        return $agent;
    }

    /**
     * @return Builder<User>
     */
    protected function staffQuery(Agent $ownerAgent)
    {
        return User::query()
            ->where('account_type', AccountType::AgentStaff)
            ->where('current_agency_id', $ownerAgent->agency_id)
            ->where('meta->owner_agent_id', $ownerAgent->id);
    }

    protected function authorizeStaff(string $ability, ?User $staff = null): void
    {
        $policy = app(AgentStaffPolicy::class);
        $user = auth()->user();
        abort_if($user === null, 403);

        $allowed = match ($ability) {
            'viewAny' => $policy->viewAny($user),
            'create' => $policy->create($user),
            'view' => $staff !== null && $policy->view($user, $staff),
            'update' => $staff !== null && $policy->update($user, $staff),
            'delete' => $staff !== null && $policy->delete($user, $staff),
            default => false,
        };

        abort_unless($allowed, 403);
    }

    /**
     * @param  list<mixed>|null  $permissions
     * @return list<string>
     */
    protected function normalizePermissions(?array $permissions): array
    {
        if ($permissions === null) {
            return [];
        }

        $allowed = AgentPermission::staffSelectable();
        $normalized = [];
        foreach ($permissions as $permission) {
            if (is_string($permission) && in_array($permission, $allowed, true)) {
                $normalized[] = $permission;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return array<string, string>
     */
    protected function staffPermissionLabels(): array
    {
        return array_intersect_key(
            AgentPermission::labels(),
            array_flip(AgentPermission::staffSelectable()),
        );
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected function groupedStaffPermissionLabels(): array
    {
        $labels = $this->staffPermissionLabels();
        $groups = [];
        foreach (RolePermissionMatrix::agentStaffModuleGroups() as $groupName => $keys) {
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

    protected function uniqueUsername(string $email): string
    {
        $base = Str::slug(Str::before($email, '@')) ?: 'agent-staff';
        $candidate = $base;
        $suffix = 1;
        while (User::query()->where('username', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
